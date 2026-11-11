<?php

namespace App\Services;

use App\Enums\ShopRole;
use App\Models\Barbershop;
use App\Models\OpeningHour;
use App\Models\Plan;
use App\Models\Service;
use App\Models\ShopSetting;
use App\Models\StaffMember;
use App\Models\User;
use App\Rules\AvailableSlug;
use App\Support\Color;
use App\Support\Features;
use App\Support\WeekSchedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Aanmaken en inrichten van een zaak: gebruikt door de onboarding (§8) en het beheer in /app.
 */
class ShopSetupService
{
    /** Voorgestelde diensten in de onboarding. */
    public const array SUGGESTED_SERVICES = [
        ['name' => 'Knippen', 'duration' => 30, 'price_cents' => 2500],
        ['name' => 'Knippen + baard', 'duration' => 45, 'price_cents' => 3500],
        ['name' => 'Baard', 'duration' => 15, 'price_cents' => 1500],
        ['name' => 'Kinderen', 'duration' => 20, 'price_cents' => 1800],
    ];

    /**
     * Stel een vrije slug voor op basis van de naam (en zo nodig de plaats).
     */
    public function suggestSlug(string $name, ?string $city = null): string
    {
        $base = Str::limit(Str::slug($name), 36, '');

        if (strlen($base) < 3) {
            $base = trim($base.'-kapper', '-');
        }

        $candidates = [$base];

        if ($city !== null && Str::slug($city) !== '') {
            $candidates[] = Str::limit($base.'-'.Str::slug($city), 40, '');
        }

        foreach (range(2, 50) as $number) {
            $candidates[] = Str::limit($base, 36, '').'-'.$number;
        }

        foreach ($candidates as $candidate) {
            $candidate = trim($candidate, '-');

            if (! AvailableSlug::isReserved($candidate) && ! Barbershop::where('slug', $candidate)->exists()) {
                return $candidate;
            }
        }

        return $base.'-'.Str::lower(Str::random(5));
    }

    /**
     * Maak de zaak aan met de eigenaar, standaardinstellingen en proefperiode (§8, na stap 2).
     */
    public function createShop(User $owner, string $name, string $city, ?string $phone, string $slug): Barbershop
    {
        return DB::transaction(function () use ($owner, $name, $city, $phone, $slug): Barbershop {
            $shop = new Barbershop([
                'name' => $name,
                'city' => $city,
                'phone' => $phone,
                'slug' => $slug,
                'email' => $owner->email,
            ]);
            $shop->is_public = false;
            $shop->onboarding_step = 3;
            // Facturering aan: een proefperiode uit het actieve plan. Uit (gratis start): geen proefperiode nodig.
            $plan = Features::billing() ? Plan::current() : null;
            $shop->plan_id = $plan?->id;
            $shop->trial_ends_at = Features::billing()
                ? now()->addDays($plan->trial_days ?? (int) config('barbercuts.billing.trial_days'))
                : null;
            $shop->save();

            $shop->users()->attach($owner->id, ['role' => ShopRole::Owner->value]);
            $owner->forgetShopRoles();

            $settings = new ShopSetting;
            $settings->barbershop_id = $shop->id;
            $settings->save();

            return $shop;
        });
    }

    /**
     * Voeg een kapper toe aan de zaak, met de volgende vrije kleur.
     */
    public function addStaffMember(Barbershop $shop, string $name, ?User $user = null): StaffMember
    {
        $count = StaffMember::withoutGlobalScopes()->where('barbershop_id', $shop->id)->count();

        $staff = new StaffMember([
            'name' => $name,
            'color' => Color::STAFF_COLORS[$count % count(Color::STAFF_COLORS)],
            'sort_order' => $count,
        ]);
        $staff->barbershop_id = $shop->id;
        $staff->user_id = $user?->id;
        $staff->save();

        return $staff;
    }

    /**
     * Vervang de openingstijden van de zaak door dit weekrooster.
     *
     * @param  array<int|string, mixed>  $week
     */
    public function saveOpeningHours(Barbershop $shop, array $week): void
    {
        DB::transaction(function () use ($shop, $week): void {
            OpeningHour::withoutGlobalScopes()->where('barbershop_id', $shop->id)->delete();

            foreach (WeekSchedule::toRows($week, 'opens_at', 'closes_at') as $row) {
                $hour = new OpeningHour($row);
                $hour->barbershop_id = $shop->id;
                $hour->save();
            }
        });
    }

    /**
     * Vervang de werktijden van een kapper door dit weekrooster.
     *
     * @param  array<int|string, mixed>  $week
     */
    public function saveWorkingHours(StaffMember $staff, array $week): void
    {
        DB::transaction(function () use ($staff, $week): void {
            $staff->workingHours()->delete();
            $staff->workingHours()->createMany(WeekSchedule::toRows($week, 'starts_at', 'ends_at'));
        });
    }

    /**
     * @param  array{name: string, duration: int, price_cents: int, description?: string|null, buffer_after?: int, is_active?: bool}  $data
     */
    public function addService(Barbershop $shop, array $data): Service
    {
        $service = new Service($data);
        $service->barbershop_id = $shop->id;
        $service->sort_order = Service::withoutGlobalScopes()->where('barbershop_id', $shop->id)->count();
        $service->save();

        return $service;
    }
}
