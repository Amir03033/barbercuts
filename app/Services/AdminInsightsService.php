<?php

namespace App\Services;

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Enums\WaitlistStatus;
use App\Models\Barbershop;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Cijfers voor de pagina Statistieken in /admin, over een gekozen periode (7, 30, 90 of 365 dagen). Live uit de
 * database (zonder demozaken) en vijf minuten bewaard, zodat de pagina snel blijft. Geen ruwe alias "returning":
 * dat is een gereserveerd woord in MariaDB (§15).
 */
class AdminInsightsService
{
    public const string TIMEZONE = 'Europe/Amsterdam';

    public const array PERIODS = [7 => '7 dagen', 30 => '30 dagen', 90 => '90 dagen', 365 => 'Een jaar'];

    private const int CACHE_SECONDS = 300;

    public static function days(mixed $period): int
    {
        return array_key_exists((int) $period, self::PERIODS) ? (int) $period : 30;
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable} begin en eind in UTC
     */
    public function range(int $days): array
    {
        $now = CarbonImmutable::now(self::TIMEZONE);

        return [$now->subDays($days - 1)->startOfDay()->utc(), $now->endOfDay()->utc()];
    }

    /**
     * Groei: aanmeldingen, afgerond, via pitchers, demo's en hoeveel demo's tot een aanmelding leidden.
     *
     * @return array{signups: int, finished: int, via_pitcher: int, demos: int, personal_demos: int, demo_signups: int, previous_signups: int}
     */
    public function growth(int $days): array
    {
        return $this->remember(__FUNCTION__, $days, function () use ($days): array {
            [$from, $to] = $this->range($days);
            $shops = fn () => Barbershop::query()->real()->whereBetween('created_at', [$from, $to]);

            return [
                'signups' => $shops()->count(),
                'finished' => $shops()->whereNull('onboarding_step')->count(),
                'via_pitcher' => $shops()->whereNotNull('referred_by_user_id')->count(),
                'demos' => DB::table('demo_visits')->whereBetween('created_at', [$from, $to])->count(),
                'personal_demos' => DB::table('demo_visits')->whereBetween('created_at', [$from, $to])->whereNotNull('demo_invite_id')->count(),
                'demo_signups' => DB::table('demo_visits')->whereBetween('created_at', [$from, $to])->whereNotNull('signed_up_barbershop_id')->count(),
                'previous_signups' => Barbershop::query()->real()->whereBetween('created_at', [$from->subDays($days), $from])->count(),
            ];
        });
    }

    /**
     * Waar zaken afhaken tijdens het aanmelden: per stap hoeveel zaken (aangemaakt in de periode) daar nog staan.
     *
     * @return array<string, int> label => aantal
     */
    public function onboardingFunnel(int $days): array
    {
        return $this->remember(__FUNCTION__, $days, function () use ($days): array {
            [$from, $to] = $this->range($days);
            $steps = Barbershop::query()->real()->whereBetween('created_at', [$from, $to])
                ->selectRaw('COALESCE(onboarding_step, 0) as step, COUNT(*) as shops')->groupBy('step')->pluck('shops', 'step');

            return [
                'Kappers' => (int) ($steps[3] ?? 0),
                'Openingstijden' => (int) ($steps[4] ?? 0),
                'Diensten' => (int) ($steps[5] ?? 0),
                'Afgerond' => (int) ($steps[0] ?? 0),
            ];
        });
    }

    /**
     * Afspraken in de periode (op de dag van de afspraak): aantal, online, gemiddelde bon, fooi, annuleringen, no-shows.
     *
     * @return array{total: int, online: int, completed: int, cancelled: int, no_show: int, revenue_cents: int, tips_cents: int, avg_ticket_cents: int}
     */
    public function bookings(int $days): array
    {
        return $this->remember(__FUNCTION__, $days, function () use ($days): array {
            [$from, $to] = $this->range($days);
            $row = $this->appointments($from, $to)->selectRaw(
                'COUNT(*) as total,
                SUM(CASE WHEN source = ? THEN 1 ELSE 0 END) as online,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as cancelled,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as no_show,
                SUM(CASE WHEN status = ? THEN COALESCE(amount_paid_cents, price_cents) ELSE 0 END) as revenue_cents,
                SUM(CASE WHEN status = ? THEN COALESCE(tip_cents, 0) ELSE 0 END) as tips_cents',
                [AppointmentSource::Online->value, AppointmentStatus::Completed->value, AppointmentStatus::Cancelled->value, AppointmentStatus::NoShow->value, AppointmentStatus::Completed->value, AppointmentStatus::Completed->value],
            )->first();

            $completed = (int) ($row->completed ?? 0);
            $revenue = (int) ($row->revenue_cents ?? 0);

            return [
                'total' => (int) ($row->total ?? 0),
                'online' => (int) ($row->online ?? 0),
                'completed' => $completed,
                'cancelled' => (int) ($row->cancelled ?? 0),
                'no_show' => (int) ($row->no_show ?? 0),
                'revenue_cents' => $revenue,
                'tips_cents' => (int) ($row->tips_cents ?? 0),
                'avg_ticket_cents' => $completed > 0 ? (int) round($revenue / $completed) : 0,
            ];
        });
    }

    /**
     * Wanneer er geknipt wordt: afspraken per uur van de dag en per weekdag, in Nederlandse tijd.
     *
     * @return array{hours: array<int, int>, weekdays: array<int, int>}
     */
    public function busiest(int $days): array
    {
        return $this->remember(__FUNCTION__, $days, function () use ($days): array {
            [$from, $to] = $this->range($days);
            // Per uur in UTC groeperen en daarna per groep omrekenen: klopt ook rond zomer- en wintertijd.
            $groups = $this->appointments($from, $to)->whereIn('status', [AppointmentStatus::Scheduled->value, AppointmentStatus::Completed->value])
                ->selectRaw("DATE_FORMAT(starts_at, '%Y-%m-%d %H:00:00') as slot, COUNT(*) as appointments")
                ->groupBy('slot')->pluck('appointments', 'slot');

            $hours = array_fill(7, 15, 0);
            $weekdays = array_fill(1, 7, 0);

            foreach ($groups as $slot => $count) {
                $local = CarbonImmutable::parse((string) $slot, 'UTC')->setTimezone(self::TIMEZONE);
                $hour = max(7, min(21, $local->hour));
                $hours[$hour] += (int) $count;
                $weekdays[$local->dayOfWeekIso] += (int) $count;
            }

            return ['hours' => $hours, 'weekdays' => $weekdays];
        });
    }

    /**
     * Omzet die zaken via het platform verwerkten, per dag (of per week bij een jaar).
     *
     * @return array<string, int> label => centen
     */
    public function revenueSeries(int $days): array
    {
        return $this->remember(__FUNCTION__, $days, function () use ($days): array {
            [$from, $to] = $this->range($days);
            $byDay = $this->appointments($from, $to)->where('status', AppointmentStatus::Completed->value)
                ->selectRaw('DATE(starts_at) as day, SUM(COALESCE(amount_paid_cents, price_cents)) as cents')
                ->groupBy('day')->pluck('cents', 'day');

            $series = [];
            $weekly = $days > 90;

            for ($day = $from->setTimezone(self::TIMEZONE)->startOfDay(); $day <= $to; $day = $day->addDay()) {
                $label = $weekly ? 'wk '.$day->isoWeek : $day->format('d-m');
                $series[$label] = ($series[$label] ?? 0) + (int) ($byDay[$day->toDateString()] ?? 0);
            }

            return $series;
        });
    }

    /**
     * Klanten: nieuw, terugkerend, accounts, reviews, wachtlijst en klanten terughalen.
     *
     * @return array{new_customers: int, returning_pct: int|null, new_accounts: int, reviews: int, rating: float|null, waitlist_booked: int, waitlist_total: int, winback_sent: int}
     */
    public function customers(int $days): array
    {
        return $this->remember(__FUNCTION__, $days, function () use ($days): array {
            [$from, $to] = $this->range($days);
            $demo = Barbershop::query()->where('is_demo', true)->select('id');

            $visits = $this->appointments($from, $to)->where('status', AppointmentStatus::Completed->value)
                ->selectRaw('customer_id, COUNT(*) as visits')->groupBy('customer_id')->pluck('visits');
            $repeat = $visits->filter(fn ($count): bool => (int) $count >= 2)->count();

            $reviews = DB::table('reviews')->whereNotIn('barbershop_id', $demo)->whereBetween('created_at', [$from, $to])->whereNull('hidden_at');
            $waitlist = DB::table('waitlist_entries')->whereNotIn('barbershop_id', $demo)->whereBetween('created_at', [$from, $to]);

            return [
                'new_customers' => DB::table('customers')->whereNotIn('barbershop_id', $demo)->whereBetween('created_at', [$from, $to])->count(),
                'returning_pct' => $visits->isNotEmpty() ? (int) round($repeat / $visits->count() * 100) : null,
                'new_accounts' => DB::table('users')->where('is_demo_visitor', false)->where('is_admin', false)->where('is_pitcher', false)
                    ->whereNotExists(fn (Builder $query) => $query->from('shop_user')->whereColumn('shop_user.user_id', 'users.id'))
                    ->whereBetween('created_at', [$from, $to])->count(),
                'reviews' => (clone $reviews)->count(),
                'rating' => ($average = (clone $reviews)->avg('rating')) !== null ? round((float) $average, 1) : null,
                'waitlist_booked' => (clone $waitlist)->where('status', WaitlistStatus::Booked->value)->count(),
                'waitlist_total' => (clone $waitlist)->count(),
                'winback_sent' => DB::table('winback_messages')->whereNotIn('barbershop_id', $demo)->where('status', 'sent')->whereBetween('created_at', [$from, $to])->count(),
            ];
        });
    }

    /**
     * De meest geboekte diensten (op naam, over alle zaken).
     *
     * @return list<array{name: string, appointments: int, avg_price_cents: int}>
     */
    public function topServices(int $days, int $limit = 8): array
    {
        return $this->remember(__FUNCTION__, $days, function () use ($days, $limit): array {
            [$from, $to] = $this->range($days);

            return $this->appointments($from, $to)->whereIn('status', [AppointmentStatus::Scheduled->value, AppointmentStatus::Completed->value])
                ->selectRaw('service_name, COUNT(*) as appointments, AVG(price_cents) as avg_price')
                ->groupBy('service_name')->orderByDesc('appointments')->limit($limit)->get()
                ->map(fn (object $row): array => ['name' => (string) $row->service_name, 'appointments' => (int) $row->appointments, 'avg_price_cents' => (int) round((float) $row->avg_price)])
                ->all();
        });
    }

    /**
     * Per pitcher: demo's gestart, aanmeldingen en hoeveel daarvan uit een demo kwamen.
     *
     * @return list<array{name: string, demos: int, signups: int, demo_signups: int}>
     */
    public function pitchers(int $days): array
    {
        return $this->remember(__FUNCTION__, $days, function () use ($days): array {
            [$from, $to] = $this->range($days);

            return DB::table('users')->where('is_pitcher', true)->orderBy('name')->get(['id', 'name'])
                ->map(fn (object $pitcher): array => [
                    'name' => (string) $pitcher->name,
                    'demos' => DB::table('demo_visits')->where('pitcher_id', $pitcher->id)->whereBetween('created_at', [$from, $to])->count(),
                    'signups' => Barbershop::query()->real()->where('referred_by_user_id', $pitcher->id)->whereBetween('created_at', [$from, $to])->count(),
                    'demo_signups' => DB::table('demo_visits')->where('pitcher_id', $pitcher->id)->whereBetween('created_at', [$from, $to])->whereNotNull('signed_up_barbershop_id')->count(),
                ])
                ->sortByDesc(fn (array $row): int => $row['signups'] * 1000 + $row['demos'])
                ->values()->all();
        });
    }

    /**
     * Afspraken van echte zaken met de starttijd in de periode.
     */
    private function appointments(CarbonImmutable $from, CarbonImmutable $to): Builder
    {
        return DB::table('appointments')
            ->whereNotIn('barbershop_id', Barbershop::query()->where('is_demo', true)->select('id'))
            ->whereBetween('starts_at', [$from, $to]);
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function remember(string $name, int $days, callable $callback): mixed
    {
        return Cache::remember("admin-insights:{$name}:{$days}", self::CACHE_SECONDS, $callback);
    }
}
