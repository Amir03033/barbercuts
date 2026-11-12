<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Barbershop;
use App\Models\Customer;
use App\Models\ShopSetting;
use App\Models\WinbackMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/**
 * Klanten terughalen (fase 8): wie X weken niet is geweest en geen afspraak heeft staan, krijgt één bericht met de boeklink.
 * Alleen klanten die berichten van de zaak willen ontvangen (marketing_opt_in), hooguit één keer per 90 dagen.
 */
class WinbackService
{
    public const int COOLDOWN_DAYS = 90;

    /** Een boeking binnen zoveel dagen na het bericht telt als "teruggehaald". */
    public const int ATTRIBUTION_DAYS = 14;

    public const array WEEK_OPTIONS = [4, 6, 8, 12];

    public function __construct(private CustomerMessenger $messenger) {}

    /**
     * Klanten die nu een bericht zouden krijgen.
     *
     * @return Builder<Customer>
     */
    public function eligible(Barbershop $shop, int $weeks): Builder
    {
        $sms = $this->messenger->smsEnabled($shop);

        return Customer::withoutGlobalScopes()
            ->where('customers.barbershop_id', $shop->id)
            ->where('marketing_opt_in', true)
            ->whereNotNull('last_visit_at')
            ->where('last_visit_at', '<=', now()->subWeeks($weeks))
            // Wie al meer dan een jaar weg is, laten we met rust.
            ->where('last_visit_at', '>=', now()->subYear())
            ->when(! $sms, fn (Builder $query) => $query->whereNotNull('email'))
            ->whereNotExists(fn ($sub) => $sub->selectRaw('1')->from('appointments')
                ->whereColumn('appointments.customer_id', 'customers.id')
                ->where('appointments.status', AppointmentStatus::Scheduled->value)
                ->where('appointments.starts_at', '>', now()))
            ->whereNotExists(fn ($sub) => $sub->selectRaw('1')->from('winback_messages')
                ->whereColumn('winback_messages.customer_id', 'customers.id')
                ->where('winback_messages.sent_at', '>', now()->subDays(self::COOLDOWN_DAYS)));
    }

    /**
     * Verstuur de berichten van vandaag voor één zaak; geeft het aantal verstuurde berichten terug.
     */
    public function sendFor(Barbershop $shop, int $limit = 200): int
    {
        $settings = ShopSetting::withoutGlobalScopes()->where('barbershop_id', $shop->id)->first();

        if ($settings?->winback_weeks === null || $shop->is_demo || ! $shop->accessState()->canTakeBookings()) {
            return 0;
        }

        $sent = 0;

        foreach ($this->eligible($shop, $settings->winback_weeks)->orderBy('last_visit_at')->limit($limit)->get() as $customer) {
            $channel = $this->messenger->send($shop, $customer, $this->message($shop, $customer, $settings->winback_message));

            if ($channel === null) {
                continue;
            }

            $row = new WinbackMessage;
            $row->barbershop_id = $shop->id;
            $row->customer_id = $customer->id;
            $row->channel = $channel;
            $row->sent_at = now();
            $row->save();
            $sent++;
        }

        return $sent;
    }

    /**
     * @return array{subject: string, greeting: string, body: string, actionLabel: string, actionUrl: string, footer: string, footerUrl: string}
     */
    public function message(Barbershop $shop, Customer $customer, ?string $custom = null): array
    {
        $firstName = explode(' ', trim($customer->name))[0];

        return [
            'subject' => "Tijd voor een nieuwe afspraak bij {$shop->name}?",
            'greeting' => "Hoi {$firstName},",
            'body' => $custom !== null && trim($custom) !== '' ? trim($custom) : self::defaultText($shop),
            'actionLabel' => 'Afspraak maken',
            'actionUrl' => route('shop.book', ['slug' => $shop->slug]),
            'footer' => 'Geen berichten meer van '.$shop->name,
            'footerUrl' => URL::signedRoute('marketing.unsubscribe', ['customer' => $customer->id]),
        ];
    }

    public static function defaultText(Barbershop $shop): string
    {
        return "Het is alweer even geleden dat je bij {$shop->name} was. Tijd voor een nieuwe knipbeurt? Je boekt in een paar tikken.";
    }

    /**
     * Wat het de afgelopen periode heeft opgeleverd: verstuurd, teruggekomen klanten en hun omzet.
     *
     * @return array{sent: int, returned: int, revenue_cents: int}
     */
    public function results(Barbershop $shop, int $days = 90): array
    {
        $since = now()->subDays($days);
        $sent = DB::table('winback_messages')->where('barbershop_id', $shop->id)->where('sent_at', '>=', $since)->count();

        $back = DB::table('winback_messages as w')
            ->join('appointments as a', fn ($join) => $join->on('a.customer_id', '=', 'w.customer_id')
                ->whereColumn('a.created_at', '>=', 'w.sent_at')
                ->whereRaw('a.created_at <= DATE_ADD(w.sent_at, INTERVAL ? DAY)', [self::ATTRIBUTION_DAYS]))
            ->where('w.barbershop_id', $shop->id)
            ->where('w.sent_at', '>=', $since)
            ->where('a.status', '!=', AppointmentStatus::Cancelled->value)
            ->selectRaw('COUNT(DISTINCT w.customer_id) as returned, COALESCE(SUM(CASE WHEN a.status = ? THEN a.amount_paid_cents ELSE 0 END), 0) as revenue', [AppointmentStatus::Completed->value])
            ->first();

        return ['sent' => $sent, 'returned' => (int) ($back->returned ?? 0), 'revenue_cents' => (int) ($back->revenue ?? 0)];
    }
}
