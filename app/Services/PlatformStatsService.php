<?php

namespace App\Services;

use App\Enums\AccessState;
use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Appointment;
use App\Models\Barbershop;
use App\Models\Customer;
use App\Models\MessageLog;
use App\Models\Payment;
use App\Models\PlatformDailyStat;
use App\Models\StaffMember;
use App\Models\Subscription;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Platformstatistiek voor het adminpanel (docs/BOUWOPDRACHT.md §11).
 *
 * De zware cijfers worden dagelijks voorberekend in platform_daily_stats (command stats:compute), zodat het
 * dashboard snel blijft. Per dag staat er één waarde per sleutel; momentopnamen (aantal zaken, MRR, ...) gelden
 * voor het moment van berekenen, tellingen (afspraken, berichten, ...) voor de dag zelf.
 */
class PlatformStatsService
{
    public const string TIMEZONE = 'Europe/Amsterdam';

    /**
     * Bereken en bewaar alle cijfers voor één dag.
     */
    public function compute(CarbonInterface $date): void
    {
        $day = CarbonImmutable::instance($date)->setTimezone(self::TIMEZONE)->startOfDay();

        foreach ($this->values($day) as $key => $value) {
            PlatformDailyStat::query()->updateOrCreate(['date' => $day->toDateString(), 'key' => $key], ['value' => (int) round($value)]);
        }
    }

    /**
     * De laatst bekende waarde van een sleutel (momentopnamen), of 0.
     */
    public function latest(string $key): int
    {
        return (int) (PlatformDailyStat::query()->where('key', $key)->orderByDesc('date')->value('value') ?? 0);
    }

    /**
     * Som van een telling over een periode (bv. deze maand).
     */
    public function sum(string $key, CarbonInterface $from, CarbonInterface $to): int
    {
        return (int) PlatformDailyStat::query()->where('key', $key)->whereBetween('date', [$from->toDateString(), $to->toDateString()])->sum('value');
    }

    /**
     * Waarde per dag voor de laatste N dagen, ontbrekende dagen als 0.
     *
     * @return array<string, int> 'Y-m-d' => waarde
     */
    public function series(string $key, int $days): array
    {
        $to = CarbonImmutable::now(self::TIMEZONE)->startOfDay();
        $from = $to->subDays($days - 1);
        $stored = PlatformDailyStat::query()->where('key', $key)->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->pluck('value', 'date')->mapWithKeys(fn (mixed $value, mixed $date): array => [CarbonImmutable::parse($date)->toDateString() => (int) $value])->all();

        $series = [];

        for ($day = $from; $day <= $to; $day = $day->addDay()) {
            $series[$day->toDateString()] = $stored[$day->toDateString()] ?? 0;
        }

        return $series;
    }

    public function hasData(): bool
    {
        return PlatformDailyStat::query()->exists();
    }

    /**
     * Zorg dat het dashboard iets heeft om te tonen, ook vóór de eerste nachtelijke berekening.
     */
    public function ensureToday(): void
    {
        $today = CarbonImmutable::now(self::TIMEZONE);

        if (! PlatformDailyStat::query()->where('date', $today->toDateString())->exists()) {
            $this->compute($today);
        }
    }

    /**
     * @return array<string, int|float>
     */
    private function values(CarbonImmutable $day): array
    {
        $from = $day->utc();
        $to = $day->addDay()->utc();
        $monthFrom = $day->startOfMonth()->utc();
        $monthTo = $day->startOfMonth()->addMonth()->utc();
        $since30 = $day->addDay()->subDays(30)->utc();

        $values = [];

        // Zaken per toegangsstatus (momentopname) en aanmeldingen
        $values['shops_total'] = Barbershop::query()->count();

        foreach (AccessState::cases() as $state) {
            $values['shops_'.$state->value] = Barbershop::query()->inAccessState($state)->count();
        }

        $values['shops_onboarded'] = Barbershop::query()->whereNull('onboarding_step')->count();
        $values['signups'] = Barbershop::query()->where('created_at', '>=', $from)->where('created_at', '<', $to)->count();

        // Geld
        $recurring = Subscription::query()->whereIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::PastDue->value]);
        $values['mrr_cents'] = (int) $recurring->sum('price_cents');
        $values['revenue_month_cents'] = (int) Payment::query()->where('status', 'paid')
            ->where('payable_type', Subscription::class)->where('paid_at', '>=', $monthFrom)->where('paid_at', '<', $monthTo)->sum('amount_cents');

        // Churn: opzeggingen deze maand / abonnementen die aan het begin van de maand liepen (in basispunten: 100 = 1%)
        $atStart = Subscription::query()->where('created_at', '<', $monthFrom)
            ->where(fn ($query) => $query->whereNull('cancelled_at')->orWhere('cancelled_at', '>=', $monthFrom))
            ->whereIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::PastDue->value, SubscriptionStatus::Cancelled->value])->count();
        $cancelled = Subscription::query()->where('cancelled_at', '>=', $monthFrom)->where('cancelled_at', '<', $monthTo)
            ->whereNotNull('mollie_subscription_id')->count();
        $values['churn_bp'] = $atStart > 0 ? $cancelled / $atStart * 10000 : 0;

        // Conversie trial → betaald: zaken met minstens één betaald abonnement / zaken die een proefperiode hebben gehad
        $trialled = Barbershop::query()->whereNotNull('trial_ends_at')->count();
        $paying = Barbershop::query()->whereNotNull('trial_ends_at')
            ->whereExists(fn ($sub) => $sub->selectRaw('1')->from('payments')->whereColumn('payments.barbershop_id', 'barbershops.id')
                ->where('payments.status', 'paid')->where('payments.payable_type', Subscription::class))->count();
        $values['conversion_bp'] = $trialled > 0 ? $paying / $trialled * 10000 : 0;

        // Gebruik: afspraken op deze dag
        $created = fn () => Appointment::withoutGlobalScopes()->where('created_at', '>=', $from)->where('created_at', '<', $to);
        $values['appointments_created'] = $created()->count();
        $values['appointments_online'] = $created()->where('source', AppointmentSource::Online->value)->count();
        $values['appointments_staff'] = $created()->where('source', '!=', AppointmentSource::Online->value)->count();

        $held = fn () => Appointment::withoutGlobalScopes()->where('starts_at', '>=', $from)->where('starts_at', '<', $to);
        $values['appointments_done'] = $held()->where('status', AppointmentStatus::Completed->value)->count();
        $values['revenue_cents'] = (int) $held()->where('status', AppointmentStatus::Completed->value)->sum('amount_paid_cents');

        // No-show over de laatste 30 dagen (momentopname)
        $last30 = fn () => Appointment::withoutGlobalScopes()->where('starts_at', '>=', $since30)->where('starts_at', '<', $to);
        $done30 = $last30()->where('status', AppointmentStatus::Completed->value)->count();
        $noShow30 = $last30()->where('status', AppointmentStatus::NoShow->value)->count();
        $values['no_show_bp'] = $done30 + $noShow30 > 0 ? $noShow30 / ($done30 + $noShow30) * 10000 : 0;

        // Kappers en klanten (momentopname)
        $values['active_barbers'] = StaffMember::withoutGlobalScopes()->where('is_active', true)->count();
        $values['customer_accounts'] = Customer::withoutGlobalScopes()->whereNotNull('user_id')->distinct()->count('user_id');
        $values['guest_customers'] = Customer::withoutGlobalScopes()->whereNull('user_id')->count();

        // Berichten op deze dag
        foreach (['mail', 'push', 'sms', 'whatsapp'] as $channel) {
            $values['messages_'.$channel] = MessageLog::query()->where('channel', $channel)->where('created_at', '>=', $from)->where('created_at', '<', $to)->count();
        }

        $values['messages_failed'] = MessageLog::query()->where('status', 'failed')->where('created_at', '>=', $from)->where('created_at', '<', $to)->count();
        $values['message_cost_cents'] = (int) MessageLog::query()->where('created_at', '>=', $from)->where('created_at', '<', $to)->sum('cost_cents');

        return $values;
    }
}
