<?php

namespace App\Livewire\App;

use App\Enums\AppointmentStatus;
use App\Livewire\Concerns\HandlesAppointmentActions;
use App\Livewire\Concerns\InShop;
use App\Models\Appointment;
use App\Models\Barbershop;
use App\Services\ShopStatsService;
use App\Support\Period;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

/**
 * Vandaag (§9.1): omzet en aantal afspraken, de eerstvolgende afspraak uitgelicht en de tijdlijn van vandaag.
 * De eigenaar ziet alle kappers; een kapper ziet de eigen dag en de eigen omzet (§6.4).
 */
#[Layout('layouts::app', ['active' => 'today', 'wide' => true])]
#[Title('Vandaag')]
class Today extends Component
{
    use HandlesAppointmentActions;
    use InShop;

    public function markLinkShared(): void
    {
        $this->authorizeOwner();

        $shop = $this->shop();

        if ($shop->link_shared_at === null) {
            $shop->link_shared_at = now();
            $shop->save();
        }
    }

    /**
     * @return list<array{label: string, done: bool, href: string|null}>
     */
    private function checklist(Barbershop $shop): array
    {
        return [
            ['label' => 'Logo uploaden', 'done' => $shop->logo_path !== null, 'href' => route('app.appearance')],
            ['label' => "Foto's toevoegen", 'done' => $shop->images()->exists(), 'href' => route('app.appearance')],
            [
                'label' => 'Thema kiezen',
                'done' => $shop->accent_color !== Barbershop::DEFAULT_ACCENT || $shop->theme_mode->value === 'dark',
                'href' => route('app.appearance'),
            ],
            ['label' => 'Link in je Instagram-bio zetten', 'done' => $shop->link_shared_at !== null, 'href' => null],
            ['label' => 'Eerste afspraak', 'done' => $shop->appointments()->exists(), 'href' => null],
        ];
    }

    /** Periode van de omzetgrafiek: 7 (dagen), 30 (dagen) of year (dit jaar). */
    public string $range = '7';

    public function updatedRange(): void
    {
        if (! in_array($this->range, ['7', '30', 'year'], true)) {
            $this->range = '7';
        }
    }

    private function chartPeriod(Barbershop $shop): Period
    {
        $today = CarbonImmutable::now($shop->timezone);

        return match ($this->range) {
            'year' => Period::make('year', $today->toDateString(), $shop->timezone),
            '30' => Period::make('custom', $today->subDays(29)->toDateString(), $shop->timezone, $today->toDateString()),
            default => Period::make('custom', $today->subDays(6)->toDateString(), $shop->timezone, $today->toDateString()),
        };
    }

    /**
     * Stuur de bevestigingsmail opnieuw, direct vanaf het overzicht (hooguit drie keer per minuut).
     */
    public function resendVerification(): void
    {
        $user = $this->user();

        if ($user->hasVerifiedEmail()) {
            return;
        }

        $key = 'verify-email:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, 3)) {
            $this->dispatch('toast', message: 'Even geduld — probeer het over een minuut opnieuw.', type: 'error');

            return;
        }

        RateLimiter::hit($key, 60);

        try {
            $user->sendEmailVerificationNotification();
        } catch (Throwable $exception) {
            report($exception);
            $this->dispatch('toast', message: 'De mail versturen is niet gelukt. Probeer het later opnieuw.', type: 'error');

            return;
        }

        $this->dispatch('toast', message: 'We hebben een nieuwe link gestuurd naar '.$user->email);
    }

    public function render(ShopStatsService $stats): View
    {
        $shop = $this->shop();
        $user = $this->user();
        $isOwner = $this->isOwner();
        $scope = $stats->scopeFor($shop, $user);

        $today = CarbonImmutable::now($shop->timezone);
        $day = Period::make('day', $today->toDateString(), $shop->timezone);
        $month = Period::make('month', $today->toDateString(), $shop->timezone);
        $chartPeriod = $this->chartPeriod($shop);

        $appointments = Appointment::query()
            ->with(['customer', 'staffMember'])
            ->when($scope !== null, fn ($query) => $query->where('staff_member_id', $scope))
            ->where('starts_at', '>=', $day->from->utc())
            ->where('starts_at', '<', $day->to->utc())
            ->where('status', '!=', AppointmentStatus::Cancelled->value)
            ->orderBy('starts_at')
            ->get();

        $next = $appointments->first(
            fn (Appointment $appointment): bool => $appointment->status === AppointmentStatus::Scheduled && $appointment->ends_at->isFuture()
        );

        $selected = $this->selectedAppointment();
        $checklist = $isOwner ? $this->checklist($shop) : [];
        $revenueToday = $stats->revenueWithComparison($shop, $user, $day);
        $revenueMonth = $stats->revenueWithComparison($shop, $user, $month);
        $kpisMonth = $stats->kpis($shop, $user, $month);
        $chart = $stats->revenueWithComparison($shop, $user, $chartPeriod);
        $status = $stats->statusBreakdown($shop, $user, $month);

        return view('livewire.app.today', [
            'shop' => $shop,
            'isOwner' => $isOwner,
            'revenue' => $revenueToday['revenue_cents'],
            'revenueTodayChange' => $revenueToday['change'],
            'revenueMonth' => $revenueMonth,
            'newCustomers' => $kpisMonth['new_customers'],
            'appointmentsMonth' => $stats->appointmentCount($shop, $user, $month),
            'remaining' => $appointments->filter(fn (Appointment $appointment): bool => $appointment->status === AppointmentStatus::Scheduled && $appointment->ends_at->isFuture())->count(),
            'appointments' => $appointments,
            'next' => $next,
            'chartSeries' => array_map(fn (array $point): array => ['label' => $point['label'], 'value' => $point['cents']], $stats->series($shop, $user, $chartPeriod)),
            'chart' => $chart,
            'chartLabel' => $this->range === 'year' ? 'Dit jaar' : 'Laatste '.$this->range.' dagen',
            'status' => $status,
            'activity' => $stats->recentActivity($shop, $user),
            'selected' => $selected,
            'canUpdateSelected' => $selected !== null && $user->can('update', $selected),
            'checklist' => collect($checklist)->every('done') ? [] : $checklist,
            'emailVerified' => $user->hasVerifiedEmail(),
            'greeting' => match (true) {
                $today->hour < 12 => 'Goedemorgen',
                $today->hour < 18 => 'Goedemiddag',
                default => 'Goedenavond',
            },
        ]);
    }
}
