<?php

namespace App\Services\Demo;

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Enums\CancelledBy;
use App\Enums\PaymentMethod;
use App\Enums\ShopRole;
use App\Models\ActivityLog;
use App\Models\Barbershop;
use App\Models\ShopSetting;
use App\Models\User;
use App\Services\ReviewService;
use App\Support\Color;
use App\Support\ImageStore;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Demozaken voor pitchers (fase 6): een verse, gevulde, fictieve zaak per sessie, die na een dag weer verdwijnt.
 * Alles gaat rechtstreeks de database in, zodat er geen events afgaan en er dus nooit een bericht wordt verstuurd.
 */
class DemoShopService
{
    private const array STAFF = [
        ['name' => 'Sam', 'days' => [2, 3, 4, 5, 6], 'from' => 9, 'to' => 18],
        ['name' => 'Mo', 'days' => [2, 3, 4, 5, 6], 'from' => 9, 'to' => 18],
        ['name' => 'Daan', 'days' => [3, 4, 5, 6], 'from' => 10, 'to' => 18],
    ];

    /** Diensten met een gewicht: hoe vaak ze geboekt worden. */
    private const array SERVICES = [
        ['name' => 'Knippen', 'duration' => 30, 'price_cents' => 2500, 'weight' => 45],
        ['name' => 'Knippen + baard', 'duration' => 45, 'price_cents' => 3500, 'weight' => 25],
        ['name' => 'Skin fade', 'duration' => 45, 'price_cents' => 3200, 'weight' => 15],
        ['name' => 'Baard', 'duration' => 15, 'price_cents' => 1500, 'weight' => 10],
        ['name' => 'Kinderen', 'duration' => 20, 'price_cents' => 1800, 'weight' => 5],
    ];

    private const array FIRST_NAMES = [
        'Jesse', 'Lars', 'Thijs', 'Ruben', 'Noah', 'Finn', 'Sem', 'Luuk', 'Milan', 'Bram', 'Youssef', 'Bilal', 'Kevin', 'Stefan',
        'Rick', 'Tom', 'Joost', 'Niels', 'Emre', 'Omar', 'Jayden', 'Dylan', 'Sven', 'Koen', 'Wouter', 'Pim', 'Hugo', 'Tim',
    ];

    private const array LAST_NAMES = [
        'de Vries', 'Jansen', 'Bakker', 'Visser', 'Smit', 'Meijer', 'de Boer', 'Mulder', 'Bos', 'Vos', 'Peters', 'Hendriks',
        'van Dijk', 'Yilmaz', 'El Amrani', 'Dekker', 'Brouwer', 'de Groot', 'Kok', 'Jacobs', 'Vermeulen', 'Kaya',
    ];

    private const array REVIEW_TEXTS = [
        5 => ['Strakke fade, precies zoals ik wilde.', 'Altijd op tijd en altijd goed. Aanrader.', 'Beste kapper van Utrecht, en online boeken werkt super.', 'Top geknipt en gezellig.', 'Baard nog nooit zo strak gehad.'],
        4 => ['Goed geknipt, volgende keer iets korter bovenop.', 'Prima zaak, fijne sfeer.', 'Netjes en snel geholpen.'],
        3 => ['Oké, maar het was erg druk.', 'Knipbeurt was goed, wel wat gehaast.'],
    ];

    private const array NOTES = [
        'Fade 1,5 aan de zijkant, bovenop op lengte.', 'Wil altijd bij Sam.', 'Baard kort en strak, lijnen scheren.',
        'Komt meestal 5 minuten te laat.', 'Scheiding links, niet te kort bovenop.',
    ];

    /**
     * Start een verse demozaak voor deze pitcher; een eerdere demo van dezelfde pitcher wordt gewist.
     */
    public function start(User $pitcher): Barbershop
    {
        foreach (Barbershop::query()->where('is_demo', true)->where('demo_pitcher_id', $pitcher->id)->get() as $previous) {
            $this->delete($previous);
        }

        $shop = DB::transaction(fn (): Barbershop => $this->build($pitcher));

        $pitcher->forceFill(['last_demo_at' => now(), 'demo_count' => $pitcher->demo_count + 1])->save();
        $pitcher->forgetShopRoles();

        ActivityLog::record('demo.started', null, $pitcher);

        return $shop;
    }

    /**
     * De lopende demozaak van een pitcher, als die er is en nog niet verlopen is.
     */
    public function current(User $pitcher): ?Barbershop
    {
        return Barbershop::query()
            ->where('is_demo', true)
            ->where('demo_pitcher_id', $pitcher->id)
            ->where('demo_expires_at', '>', now())
            ->latest('id')
            ->first();
    }

    /**
     * Wis een demozaak met alles erin. De enige plek waar een zaak echt verwijderd wordt (§7.7 geldt voor echte zaken).
     */
    public function delete(Barbershop $shop): void
    {
        abort_unless($shop->is_demo, 403);

        DB::transaction(function () use ($shop): void {
            // Afspraken eerst: die houden klanten, kappers en diensten vast (restrict).
            DB::table('appointments')->where('barbershop_id', $shop->id)->delete();
            DB::table('barbershops')->where('id', $shop->id)->where('is_demo', true)->delete();
        });

        Storage::disk(ImageStore::disk())->deleteDirectory('shops/'.$shop->id);
    }

    /**
     * Ruim alle verlopen demozaken op; geeft het aantal terug.
     */
    public function deleteExpired(): int
    {
        $expired = Barbershop::query()
            ->where('is_demo', true)
            ->where(fn ($query) => $query->whereNull('demo_expires_at')->orWhere('demo_expires_at', '<=', now()))
            ->get();

        $expired->each(fn (Barbershop $shop) => $this->delete($shop));

        return $expired->count();
    }

    private function build(User $pitcher): Barbershop
    {
        $now = now();

        $shop = new Barbershop([
            'slug' => 'demo-'.Str::lower(Str::random(10)),
            'name' => 'Studio Fade',
            'description' => 'Barbershop in hartje Utrecht. Strakke fades, verzorgde baarden en altijd goede koffie.',
            'phone' => '+31301234567',
            'email' => $pitcher->email,
            'address' => 'Voorbeeldstraat 12',
            'postal_code' => '3511 AB',
            'city' => 'Utrecht',
            'accent_color' => '#1f3a5f',
        ]);
        $shop->is_public = true;
        $shop->is_demo = true;
        $shop->demo_expires_at = $now->copy()->addHours((int) config('barbercuts.demo.hours'));
        $shop->demo_pitcher_id = $pitcher->id;
        $shop->save();

        $shop->users()->attach($pitcher->id, ['role' => ShopRole::Owner->value]);

        $settings = new ShopSetting;
        $settings->barbershop_id = $shop->id;
        $settings->save();

        foreach ([2, 3, 4, 5, 6] as $day) {
            DB::table('opening_hours')->insert(['barbershop_id' => $shop->id, 'day_of_week' => $day, 'opens_at' => '09:00:00', 'closes_at' => '18:00:00']);
        }

        $staff = [];

        foreach (self::STAFF as $index => $member) {
            // De pitcher is zelf de eerste kapper, zodat "eigen agenda" en "eigen omzet" te laten zien zijn.
            $id = DB::table('staff_members')->insertGetId([
                'barbershop_id' => $shop->id,
                'user_id' => $index === 0 ? $pitcher->id : null,
                'name' => $member['name'],
                'color' => Color::STAFF_COLORS[$index % count(Color::STAFF_COLORS)],
                'sort_order' => $index,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($member['days'] as $day) {
                DB::table('working_hours')->insert([
                    'staff_member_id' => $id,
                    'day_of_week' => $day,
                    'starts_at' => sprintf('%02d:00:00', $member['from']),
                    'ends_at' => sprintf('%02d:00:00', $member['to']),
                ]);
            }

            $staff[] = $member + ['id' => $id];
        }

        $services = [];

        foreach (self::SERVICES as $index => $service) {
            $id = DB::table('services')->insertGetId([
                'barbershop_id' => $shop->id,
                'name' => $service['name'],
                'duration' => $service['duration'],
                'price_cents' => $service['price_cents'],
                'sort_order' => $index,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $services[] = $service + ['id' => $id];
        }

        [$appointments, $customerStats] = $this->planAppointments($shop, $staff, $services);

        $customerIds = $this->insertCustomers($shop, $customerStats);

        foreach (array_chunk($appointments, 250) as $chunk) {
            DB::table('appointments')->insert(array_map(function (array $row) use ($customerIds): array {
                $row['customer_id'] = $customerIds[$row['customer_id']];

                return $row;
            }, $chunk));
        }

        $this->insertReviews($shop);
        $this->insertWinbackAndWaitlist($shop);

        return $shop;
    }

    /**
     * "Klanten terughalen" staat aan met een resultaat om te laten zien, en er staan een paar klanten op de wachtlijst.
     */
    private function insertWinbackAndWaitlist(Barbershop $shop): void
    {
        DB::table('shop_settings')->where('barbershop_id', $shop->id)->update(['winback_weeks' => 6]);

        // Berichten van de afgelopen weken, elk twee dagen voordat die klant weer een afspraak maakte.
        $returned = DB::table('appointments')->where('barbershop_id', $shop->id)->where('status', AppointmentStatus::Completed->value)
            ->where('created_at', '>', now()->subDays(50))->orderByDesc('id')->get(['customer_id', 'created_at'])->unique('customer_id')->take(18);

        foreach ($returned as $visit) {
            $sentAt = CarbonImmutable::parse($visit->created_at, 'UTC')->subDays(2);

            DB::table('winback_messages')->insert([
                'barbershop_id' => $shop->id, 'customer_id' => $visit->customer_id, 'channel' => 'mail', 'status' => 'sent',
                'sent_at' => $sentAt, 'created_at' => $sentAt, 'updated_at' => $sentAt,
            ]);
        }

        $serviceId = DB::table('services')->where('barbershop_id', $shop->id)->orderBy('id')->value('id');
        $customers = DB::table('customers')->where('barbershop_id', $shop->id)->whereNotNull('email')->inRandomOrder()->limit(3)->pluck('id');
        $day = CarbonImmutable::now($shop->timezone)->addDay();

        foreach ($customers as $customerId) {
            // Alleen dagen waarop de zaak open is (di–za).
            while (! in_array($day->dayOfWeekIso, [2, 3, 4, 5, 6], true)) {
                $day = $day->addDay();
            }

            DB::table('waitlist_entries')->insert([
                'barbershop_id' => $shop->id, 'customer_id' => $customerId, 'service_id' => $serviceId, 'date' => $day->toDateString(),
                'token' => Str::random(40), 'status' => 'waiting', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reviews bij een deel van de voltooide afspraken, met een paar reacties en één lage score om op te reageren.
     */
    private function insertReviews(Barbershop $shop): void
    {
        $visits = DB::table('appointments')->where('barbershop_id', $shop->id)->where('status', AppointmentStatus::Completed->value)
            ->inRandomOrder()->limit(60)->get(['id', 'customer_id', 'staff_member_id', 'ends_at']);

        $rows = [];

        foreach ($visits as $index => $visit) {
            $rating = $index === 0 ? 2 : $this->pick([[5, 68], [4, 26], [3, 6]]);
            $comment = match (true) {
                $index === 0 => 'Moest twintig minuten wachten terwijl ik een afspraak had. Knipbeurt zelf was prima.',
                $index % 3 === 0 => null,
                default => self::REVIEW_TEXTS[$rating][$index % count(self::REVIEW_TEXTS[$rating])],
            };
            $at = CarbonImmutable::parse($visit->ends_at, 'UTC')->addHours(mt_rand(3, 30))->min(now());

            $rows[] = [
                'barbershop_id' => $shop->id,
                'appointment_id' => $visit->id,
                'customer_id' => $visit->customer_id,
                'staff_member_id' => $visit->staff_member_id,
                'rating' => $rating,
                'comment' => $comment,
                'reply' => $index % 10 === 5 ? 'Dank je wel, tot de volgende keer!' : null,
                'replied_at' => $index % 10 === 5 ? $at->addDay()->min(now()) : null,
                'created_at' => $at,
                'updated_at' => $at,
            ];
        }

        DB::table('reviews')->insert($rows);

        app(ReviewService::class)->recalculate($shop->id);
    }

    /**
     * Acht weken historie, vandaag en twee weken vooruit: per kapper een dag zonder overlap, binnen de werktijden.
     *
     * @param  list<array{id: int, name: string, days: list<int>, from: int, to: int}>  $staff
     * @param  list<array{id: int, name: string, duration: int, price_cents: int, weight: int}>  $services
     * @return array{0: list<array<string, mixed>>, 1: array<int, array{last_visit: ?string, no_shows: int}>}
     */
    private function planAppointments(Barbershop $shop, array $staff, array $services): array
    {
        $now = CarbonImmutable::now($shop->timezone);
        $customerCount = 140;
        $stats = array_fill(0, $customerCount, ['last_visit' => null, 'no_shows' => 0]);
        $rows = [];
        $stamp = fn (CarbonImmutable $time): string => $time->utc()->format('Y-m-d H:i:s');

        for ($offset = -56; $offset <= 14; $offset++) {
            $day = $now->startOfDay()->addDays($offset);
            $fill = $offset <= 0 ? 0.78 : max(0.15, 0.65 - $offset * 0.04);

            foreach ($staff as $member) {
                if (! in_array($day->dayOfWeekIso, $member['days'], true)) {
                    continue;
                }

                $cursor = $day->setTime($member['from'], 0);
                $end = $day->setTime($member['to'], 0);

                while ($cursor < $end) {
                    if (mt_rand(1, 100) > $fill * 100) {
                        $cursor = $cursor->addMinutes(30);

                        continue;
                    }

                    $service = $this->pickService($services);
                    $finish = $cursor->addMinutes($service['duration']);

                    if ($finish > $end) {
                        break;
                    }

                    $customer = mt_rand(0, $customerCount - 1);
                    $status = $this->pickStatus($cursor, $now);
                    $created = $cursor->subDays(mt_rand(1, 9))->min($now);

                    $row = [
                        'barbershop_id' => $shop->id,
                        'customer_id' => $customer,
                        'staff_member_id' => $member['id'],
                        'service_id' => $service['id'],
                        'service_name' => $service['name'],
                        'duration' => $service['duration'],
                        'price_cents' => $service['price_cents'],
                        'starts_at' => $stamp($cursor),
                        'ends_at' => $stamp($finish),
                        'blocked_until' => $stamp($finish),
                        'status' => $status->value,
                        'source' => $this->pick([[AppointmentSource::Online, 60], [AppointmentSource::Staff, 30], [AppointmentSource::WalkIn, 10]])->value,
                        'manage_token' => Str::random(40),
                        'amount_paid_cents' => null,
                        'tip_cents' => null,
                        'payment_method' => null,
                        'completed_at' => null,
                        'cancelled_at' => null,
                        'cancelled_by' => null,
                        'created_at' => $stamp($created),
                        'updated_at' => $stamp($created),
                    ];

                    if ($status === AppointmentStatus::Completed) {
                        $row['amount_paid_cents'] = $service['price_cents'];
                        $row['tip_cents'] = $this->pick([[0, 70], [100, 10], [200, 12], [500, 8]]);
                        $row['payment_method'] = $this->pick([[PaymentMethod::Pin, 60], [PaymentMethod::Cash, 20], [PaymentMethod::Tikkie, 15], [PaymentMethod::Other, 5]])->value;
                        $row['completed_at'] = $stamp($finish);
                        $stats[$customer]['last_visit'] = max($stats[$customer]['last_visit'] ?? '', $row['starts_at']);
                    } elseif ($status === AppointmentStatus::NoShow) {
                        $stats[$customer]['no_shows']++;
                    } elseif ($status === AppointmentStatus::Cancelled) {
                        $row['cancelled_at'] = $stamp($cursor->subDay());
                        $row['cancelled_by'] = CancelledBy::Customer->value;
                    }

                    $rows[] = $row;
                    $cursor = $finish->addMinutes(mt_rand(1, 100) <= 20 ? 15 : 0);
                }
            }
        }

        return [$rows, $stats];
    }

    /**
     * Wat net begonnen is of nog komt staat gepland, zodat de pitcher "Voltooien" kan laten zien.
     */
    private function pickStatus(CarbonImmutable $start, CarbonImmutable $now): AppointmentStatus
    {
        if ($start > $now->subMinutes(45)) {
            return AppointmentStatus::Scheduled;
        }

        return $this->pick([[AppointmentStatus::Completed, 92], [AppointmentStatus::NoShow, 4], [AppointmentStatus::Cancelled, 4]]);
    }

    /**
     * @param  array<int, array{last_visit: ?string, no_shows: int}>  $stats
     * @return array<int, int> index in de planning → id van de klant
     */
    private function insertCustomers(Barbershop $shop, array $stats): array
    {
        $now = now();
        $rows = [];

        foreach ($stats as $index => $stat) {
            $first = self::FIRST_NAMES[$index % count(self::FIRST_NAMES)];
            $last = self::LAST_NAMES[($index * 7 + intdiv($index, count(self::FIRST_NAMES))) % count(self::LAST_NAMES)];
            $created = $now->copy()->subDays(mt_rand(20, 400));

            $rows[] = [
                'barbershop_id' => $shop->id,
                'name' => $first.' '.$last,
                // Fictieve nummers in een reeks die niet wordt uitgegeven; er wordt nooit iets naartoe gestuurd.
                'phone' => '+3169'.str_pad((string) (9000000 + $index), 7, '0', STR_PAD_LEFT),
                'email' => $index % 3 === 0 ? null : Str::slug($first.' '.$last, '.').'@voorbeeld.test',
                'notes' => $index % 9 === 0 ? self::NOTES[intdiv($index, 9) % count(self::NOTES)] : null,
                'marketing_opt_in' => $index % 2 === 0,
                'no_show_count' => $stat['no_shows'],
                'last_visit_at' => $stat['last_visit'],
                'created_at' => $created,
                'updated_at' => $created,
            ];
        }

        DB::table('customers')->insert($rows);

        return DB::table('customers')->where('barbershop_id', $shop->id)->orderBy('id')->pluck('id')->all();
    }

    /**
     * @param  list<array{id: int, name: string, duration: int, price_cents: int, weight: int}>  $services
     * @return array{id: int, name: string, duration: int, price_cents: int, weight: int}
     */
    private function pickService(array $services): array
    {
        return $this->pick(array_map(fn (array $service): array => [$service, $service['weight']], $services));
    }

    /**
     * Kies een waarde op gewicht.
     *
     * @template T
     *
     * @param  list<array{0: T, 1: int}>  $options
     * @return T
     */
    private function pick(array $options): mixed
    {
        $roll = mt_rand(1, array_sum(array_column($options, 1)));

        foreach ($options as [$value, $weight]) {
            $roll -= $weight;

            if ($roll <= 0) {
                return $value;
            }
        }

        return $options[0][0];
    }
}
