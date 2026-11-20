<?php

namespace App\Support;

use App\Models\Barbershop;
use App\Models\DemoVisit;
use App\Services\Demo\DemoShopService;
use Illuminate\Support\Facades\DB;

/**
 * "Maak hier je echte zaak van": wat iemand in de demo heeft ingesteld, gaat mee naar het aanmelden van de eigen zaak.
 * Alleen instellingen (naam, plaats en kleur als die van de bezoeker zijn, diensten met prijzen, openingstijden), nooit
 * de verzonnen kappers, klanten of afspraken. Wordt in de sessie bewaard tot het aanmelden klaar is.
 *
 * @phpstan-type Carryover array{visit_id: int|null, shop_name: string|null, city: string|null, accent: string|null, services: list<array{name: string, duration: int, price_cents: int}>, week: array<int, array{open: bool, blocks: list<array{from: string, to: string}>}>}
 */
class DemoCarryover
{
    public const string SESSION = 'demo_carryover';

    /**
     * Lees de instellingen uit een demozaak.
     *
     * @return Carryover
     */
    public static function fromShop(Barbershop $shop, ?DemoVisit $visit): array
    {
        $personal = $visit?->demo_invite_id !== null;

        $services = DB::table('services')->where('barbershop_id', $shop->id)->where('is_active', true)
            ->orderBy('sort_order')->limit(20)->get(['name', 'duration', 'price_cents'])
            ->map(fn (object $row): array => ['name' => (string) $row->name, 'duration' => (int) $row->duration, 'price_cents' => (int) $row->price_cents])
            ->values()->all();

        $hours = DB::table('opening_hours')->where('barbershop_id', $shop->id)->get();

        return [
            'visit_id' => $visit?->id,
            // De standaardnaam "Studio Fade" is niet van de bezoeker; een persoonlijke of zelf gewijzigde naam wel.
            'shop_name' => $personal || $shop->name !== DemoShopService::DEFAULT_NAME ? $shop->name : null,
            'city' => $personal || $shop->city !== DemoShopService::DEFAULT_CITY ? $shop->city : null,
            'accent' => $personal || $shop->accent_color !== DemoShopService::DEFAULT_ACCENT ? $shop->accent_color : null,
            'services' => $services,
            'week' => $hours->isEmpty() ? WeekSchedule::preset() : WeekSchedule::fromRows($hours, 'opens_at', 'closes_at'),
        ];
    }

    /**
     * @return Carryover|null
     */
    public static function current(): ?array
    {
        $carryover = session(self::SESSION);

        return is_array($carryover) ? $carryover : null;
    }

    public static function forget(): void
    {
        session()->forget(self::SESSION);
    }
}
