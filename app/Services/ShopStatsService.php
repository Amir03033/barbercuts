<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Barbershop;
use App\Models\Customer;
use App\Models\StaffMember;
use App\Models\User;
use App\Services\Booking\AvailabilityService;
use App\Support\Period;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

/**
 * Omzet en statistieken van een zaak (docs/BOUWOPDRACHT.md §7.5).
 *
 * Elke methode krijgt de kijker mee. De eigenaar ziet het totaal en mag op één kapper filteren; een kapper
 * ziet alleen de eigen cijfers, wat hij ook meestuurt (§6.4, B9). Gerekend wordt in de database, per zaak,
 * in de tijdzone van de zaak en op basis van starts_at.
 *
 * @phpstan-type Kpis array{
 *     revenue_cents: int, tips_cents: int, appointments: int, average_cents: int, no_shows: int,
 *     no_show_rate: float|null, booked_minutes: int, available_minutes: int, occupancy: float|null,
 *     new_customers: int, return_rate: float|null, by_payment_method: array<string, int>
 * }
 */
class ShopStatsService
{
    /** Kapper zonder eigen agenda: een scope die met niets overeenkomt. */
    private const int NOTHING = 0;

    public function __construct(private AvailabilityService $availability) {}

    /**
     * Wiens cijfers mag deze kijker zien? null = de hele zaak, anders het id van één kapper (§6.4).
     */
    public function scopeFor(Barbershop $shop, User $viewer, ?int $requestedStaffId = null): ?int
    {
        if ($viewer->isOwnerOf($shop)) {
            return $requestedStaffId;
        }

        if (! $viewer->isMemberOf($shop)) {
            return self::NOTHING;
        }

        // Een kapper ziet alleen de eigen omzet, ook als hij het id van een collega meestuurt.
        return StaffMember::withoutGlobalScopes()
            ->where('barbershop_id', $shop->id)
            ->where('user_id', $viewer->id)
            ->value('id') ?? self::NOTHING;
    }

    /**
     * Alle KPI's voor een periode.
     *
     * @return Kpis
     */
    public function kpis(Barbershop $shop, User $viewer, Period $period, ?int $requestedStaffId = null): array
    {
        $scope = $this->scopeFor($shop, $viewer, $requestedStaffId);

        $totals = $this->inPeriod($shop, $scope, $period)
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'completed' THEN amount_paid_cents ELSE 0 END), 0) AS revenue")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'completed' THEN tip_cents ELSE 0 END), 0) AS tips")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END), 0) AS completed")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END), 0) AS no_shows")
            ->selectRaw("COALESCE(SUM(CASE WHEN status IN ('scheduled', 'completed') THEN duration ELSE 0 END), 0) AS booked_minutes")
            ->toBase()
            ->first();

        $revenue = (int) $totals->revenue;
        $completed = (int) $totals->completed;
        $noShows = (int) $totals->no_shows;
        $bookedMinutes = (int) $totals->booked_minutes;

        $byMethod = $this->inPeriod($shop, $scope, $period)
            ->where('status', AppointmentStatus::Completed->value)
            ->selectRaw("COALESCE(payment_method, 'other') AS method, COALESCE(SUM(amount_paid_cents), 0) AS total")
            ->groupBy('method')
            ->toBase()
            ->pluck('total', 'method')
            ->map(fn (mixed $total): int => (int) $total)
            ->all();

        $availableMinutes = $this->availability->workMinutes($shop, $this->staffInScope($shop, $scope), $period->fromDate(), $period->toDate());

        return [
            'revenue_cents' => $revenue,
            'tips_cents' => (int) $totals->tips,
            'appointments' => $completed,
            'average_cents' => $completed > 0 ? (int) round($revenue / $completed) : 0,
            'no_shows' => $noShows,
            'no_show_rate' => $completed + $noShows > 0 ? $noShows / ($completed + $noShows) : null,
            'booked_minutes' => $bookedMinutes,
            'available_minutes' => $availableMinutes,
            'occupancy' => $availableMinutes > 0 ? min(1, $bookedMinutes / $availableMinutes) : null,
            'new_customers' => $this->newCustomers($shop, $scope, $period),
            'return_rate' => $this->returnRate($shop, $scope, $period),
            'by_payment_method' => $byMethod,
        ];
    }

    /**
     * Omzet van een periode en het verschil met de periode ervoor.
     *
     * @return array{revenue_cents: int, previous_cents: int, change: float|null}
     */
    public function revenueWithComparison(Barbershop $shop, User $viewer, Period $period, ?int $requestedStaffId = null): array
    {
        $scope = $this->scopeFor($shop, $viewer, $requestedStaffId);
        $current = $this->revenue($shop, $scope, $period);
        $previous = $this->revenue($shop, $scope, $period->previous());

        return [
            'revenue_cents' => $current,
            'previous_cents' => $previous,
            'change' => $previous > 0 ? (float) (($current - $previous) / $previous) : null,
        ];
    }

    /**
     * Omzet per staaf van de grafiek, in één query.
     *
     * @return list<array{label: string, cents: int}>
     */
    public function series(Barbershop $shop, User $viewer, Period $period, ?int $requestedStaffId = null): array
    {
        $scope = $this->scopeFor($shop, $viewer, $requestedStaffId);
        $buckets = $period->buckets();
        $query = $this->inPeriod($shop, $scope, $period)->where('status', AppointmentStatus::Completed->value);

        foreach ($buckets as $index => $bucket) {
            $query->selectRaw(
                "COALESCE(SUM(CASE WHEN starts_at >= ? AND starts_at < ? THEN amount_paid_cents ELSE 0 END), 0) AS b{$index}",
                [$bucket['from']->utc(), $bucket['to']->utc()],
            );
        }

        $row = (array) $query->toBase()->first();

        return array_map(
            fn (array $bucket, int $index): array => ['label' => $bucket['label'], 'cents' => (int) ($row["b{$index}"] ?? 0)],
            $buckets,
            array_keys($buckets),
        );
    }

    /**
     * Uitsplitsing per kapper. Alleen voor de eigenaar; een kapper krijgt een lege lijst (B9).
     *
     * @return list<array{staff: StaffMember, revenue_cents: int, tips_cents: int, appointments: int}>
     */
    public function perStaff(Barbershop $shop, User $viewer, Period $period): array
    {
        if (! $viewer->isOwnerOf($shop)) {
            return [];
        }

        $rows = $this->inPeriod($shop, null, $period)
            ->where('status', AppointmentStatus::Completed->value)
            ->selectRaw('staff_member_id, COALESCE(SUM(amount_paid_cents), 0) AS revenue, COALESCE(SUM(tip_cents), 0) AS tips, COUNT(*) AS appointments')
            ->groupBy('staff_member_id')
            ->toBase()
            ->get()
            ->keyBy('staff_member_id');

        return StaffMember::withoutGlobalScopes()
            ->where('barbershop_id', $shop->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (StaffMember $staff): bool => $staff->is_active || $rows->has($staff->id))
            ->map(fn (StaffMember $staff): array => [
                'staff' => $staff,
                'revenue_cents' => (int) ($rows[$staff->id]->revenue ?? 0),
                'tips_cents' => (int) ($rows[$staff->id]->tips ?? 0),
                'appointments' => (int) ($rows[$staff->id]->appointments ?? 0),
            ])
            ->values()
            ->all();
    }

    /**
     * Aantal afspraken per status in een periode (voor de statusverdeling op het dashboard).
     *
     * @return array<string, int> status => aantal (scheduled, completed, cancelled, no_show)
     */
    public function statusBreakdown(Barbershop $shop, User $viewer, Period $period, ?int $requestedStaffId = null): array
    {
        $scope = $this->scopeFor($shop, $viewer, $requestedStaffId);

        $counts = $this->inPeriod($shop, $scope, $period)
            ->selectRaw('status, COUNT(*) AS total')
            ->groupBy('status')
            ->toBase()
            ->pluck('total', 'status')
            ->map(fn (mixed $total): int => (int) $total)
            ->all();

        return [
            'scheduled' => $counts[AppointmentStatus::Scheduled->value] ?? 0,
            'completed' => $counts[AppointmentStatus::Completed->value] ?? 0,
            'cancelled' => $counts[AppointmentStatus::Cancelled->value] ?? 0,
            'no_show' => $counts[AppointmentStatus::NoShow->value] ?? 0,
        ];
    }

    /**
     * Alle afspraken in een periode, behalve geannuleerde (voor "afspraken deze maand").
     */
    public function appointmentCount(Barbershop $shop, User $viewer, Period $period): int
    {
        return $this->inPeriod($shop, $this->scopeFor($shop, $viewer), $period)
            ->where('status', '!=', AppointmentStatus::Cancelled->value)
            ->count();
    }

    /**
     * Recente activiteit: nieuwe afspraken, voltooide, geannuleerde en no-shows, de nieuwste eerst.
     * Een kapper ziet alleen de eigen afspraken, de eigenaar die van de hele zaak.
     *
     * @return list<array{appointment: Appointment, event: string, tone: string, icon: string, at: CarbonImmutable}>
     */
    public function recentActivity(Barbershop $shop, User $viewer, int $limit = 8): array
    {
        return $this->base($shop, $this->scopeFor($shop, $viewer))
            ->with(['customer', 'staffMember'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(function (Appointment $appointment): array {
                [$event, $tone, $icon] = match ($appointment->status) {
                    AppointmentStatus::Completed => ['Voltooid · '.money((int) $appointment->amount_paid_cents), 'completed', 'check'],
                    AppointmentStatus::Cancelled => ['Geannuleerd'.($appointment->cancelled_by?->value === 'customer' ? ' door de klant' : ''), 'cancelled', 'x'],
                    AppointmentStatus::NoShow => ['No-show', 'no_show', 'alert'],
                    default => [
                        $appointment->updated_at?->equalTo($appointment->created_at) === true
                            ? match ($appointment->source->value) {
                                'online' => 'Nieuwe online afspraak',
                                'walk_in' => 'Inloop',
                                default => 'Nieuwe afspraak',
                            }
                            : 'Afspraak gewijzigd',
                        'scheduled',
                        'calendar',
                    ],
                };

                return [
                    'appointment' => $appointment,
                    'event' => $event,
                    'tone' => $tone,
                    'icon' => $icon,
                    'at' => CarbonImmutable::instance($appointment->updated_at ?? $appointment->created_at),
                ];
            })
            ->all();
    }

    /**
     * Totale besteding van een klant: de eigenaar ziet alles, een kapper alleen wat bij hem is besteed.
     */
    public function customerSpend(Barbershop $shop, User $viewer, Customer $customer): int
    {
        $scope = $this->scopeFor($shop, $viewer);

        return (int) $this->base($shop, $scope)
            ->where('customer_id', $customer->id)
            ->where('status', AppointmentStatus::Completed->value)
            ->sum('amount_paid_cents');
    }

    /**
     * Regels voor de CSV-export van een maand: voltooide afspraken, de eigenaar alles, een kapper de eigen regels.
     *
     * @return LazyCollection<int, array{string, string, string, string, string, string, string, string}>
     */
    public function exportRows(Barbershop $shop, User $viewer, Period $period): LazyCollection
    {
        $scope = $this->scopeFor($shop, $viewer);
        $timezone = $shop->timezone;

        return $this->inPeriod($shop, $scope, $period)
            ->where('status', AppointmentStatus::Completed->value)
            ->with(['customer', 'staffMember'])
            ->orderBy('starts_at')
            ->orderBy('id')
            ->lazy(500)
            ->map(function (Appointment $appointment) use ($timezone): array {
                $start = $appointment->starts_at->toImmutable()->setTimezone($timezone);

                return [
                    $start->format('d-m-Y'),
                    $start->format('H:i'),
                    (string) $appointment->customer?->name,
                    $appointment->service_name,
                    (string) $appointment->staffMember?->name,
                    number_format((int) $appointment->amount_paid_cents / 100, 2, ',', ''),
                    number_format((int) $appointment->tip_cents / 100, 2, ',', ''),
                    self::paymentMethodLabel($appointment->payment_method?->value),
                ];
            });
    }

    public static function paymentMethodLabel(?string $method): string
    {
        return match ($method) {
            'pin' => 'Pin',
            'cash' => 'Contant',
            'tikkie' => 'Tikkie',
            'online' => 'Online',
            default => 'Anders',
        };
    }

    private function revenue(Barbershop $shop, ?int $scope, Period $period): int
    {
        return (int) $this->inPeriod($shop, $scope, $period)
            ->where('status', AppointmentStatus::Completed->value)
            ->sum('amount_paid_cents');
    }

    /**
     * Klanten met hun eerste voltooide afspraak in de periode.
     */
    private function newCustomers(Barbershop $shop, ?int $scope, Period $period): int
    {
        $firstVisits = $this->base($shop, $scope)
            ->where('status', AppointmentStatus::Completed->value)
            ->selectRaw('customer_id, MIN(starts_at) AS first_visit')
            ->groupBy('customer_id')
            ->toBase();

        return DB::query()
            ->fromSub($firstVisits, 'first_visits')
            ->where('first_visit', '>=', $period->from->utc())
            ->where('first_visit', '<', $period->to->utc())
            ->count();
    }

    /**
     * Klanten met ≥ 2 voltooide afspraken in de laatste 90 dagen / unieke klanten in die 90 dagen.
     * De 90 dagen eindigen aan het einde van de periode, of nu als de periode nog loopt.
     */
    private function returnRate(Barbershop $shop, ?int $scope, Period $period): ?float
    {
        $end = min($period->to->utc(), CarbonImmutable::now('UTC'));

        $visits = $this->base($shop, $scope)
            ->where('status', AppointmentStatus::Completed->value)
            ->where('starts_at', '>=', $end->subDays(90))
            ->where('starts_at', '<', $end)
            ->selectRaw('customer_id, COUNT(*) AS visits')
            ->groupBy('customer_id')
            ->toBase();

        $row = DB::query()
            ->fromSub($visits, 'visits')
            ->selectRaw('COUNT(*) AS customers, COALESCE(SUM(CASE WHEN visits >= 2 THEN 1 ELSE 0 END), 0) AS returning_customers')
            ->first();

        return $row !== null && (int) $row->customers > 0 ? (int) $row->returning_customers / (int) $row->customers : null;
    }

    /**
     * @return Collection<int, StaffMember>
     */
    private function staffInScope(Barbershop $shop, ?int $scope): Collection
    {
        return StaffMember::withoutGlobalScopes()
            ->where('barbershop_id', $shop->id)
            ->when($scope === null, fn ($query) => $query->where('is_active', true))
            ->when($scope !== null, fn ($query) => $query->whereKey($scope))
            ->get()
            ->keyBy('id');
    }

    /**
     * Afspraken van de zaak, beperkt tot wat de kijker mag zien.
     *
     * @return Builder<Appointment>
     */
    private function base(Barbershop $shop, ?int $scope): Builder
    {
        return Appointment::withoutGlobalScopes()
            ->where('barbershop_id', $shop->id)
            ->when($scope !== null, fn ($query) => $query->where('staff_member_id', $scope));
    }

    /**
     * @return Builder<Appointment>
     */
    private function inPeriod(Barbershop $shop, ?int $scope, Period $period): Builder
    {
        return $this->base($shop, $scope)
            ->where('starts_at', '>=', $period->from->utc())
            ->where('starts_at', '<', $period->to->utc());
    }
}
