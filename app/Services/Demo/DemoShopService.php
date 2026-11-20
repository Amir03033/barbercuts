<?php

namespace App\Services\Demo;

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Enums\CancelledBy;
use App\Enums\PaymentMethod;
use App\Enums\ShopRole;
use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\Barbershop;
use App\Models\DemoInvite;
use App\Models\DemoVisit;
use App\Models\Service;
use App\Models\ShopSetting;
use App\Models\User;
use App\Services\Booking\AvailabilityService;
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
    /** Tijdelijke demo-accounts krijgen een adres op een domein dat nooit mail ontvangt (RFC 2606). */
    public const string VISITOR_EMAIL_DOMAIN = 'demo.invalid';

    public const string DEFAULT_NAME = 'Studio Fade';

    public const string DEFAULT_CITY = 'Utrecht';

    public const string DEFAULT_ACCENT = '#1f3a5f';

    /** De plaats van de demozaak die nu gebouwd wordt (voor de teksten van reviews). */
    private string $city = self::DEFAULT_CITY;

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

        $shop = DB::transaction(fn (): Barbershop => $this->build($pitcher, pitcher: $pitcher));

        $pitcher->forceFill(['last_demo_at' => now(), 'demo_count' => $pitcher->demo_count + 1])->save();
        $pitcher->forgetShopRoles();

        ActivityLog::record('demo.started', null, $pitcher);

        return $shop;
    }

    /**
     * Openbare demolink: maak een tijdelijk demo-account met een eigen demozaak, voor iemand zonder account.
     * De pitcher van de link (als die er is) krijgt de demo op zijn naam, zodat hij ziet hoe vaak zijn link werkt.
     * Met een persoonlijke uitnodiging krijgt de demozaak de naam (en plaats en kleur) van de zaak van de kapper.
     *
     * @return array{0: User, 1: Barbershop, 2: DemoVisit}
     */
    public function startForVisitor(?User $referrer, ?DemoInvite $invite = null): array
    {
        if ($invite !== null) {
            $referrer = $invite->pitcher;
        }

        [$visitor, $shop, $visit] = DB::transaction(function () use ($referrer, $invite): array {
            $visitor = new User;
            $visitor->forceFill([
                'name' => 'Demo',
                'email' => 'demo-'.Str::lower(Str::random(16)).'@'.self::VISITOR_EMAIL_DOMAIN,
                'password' => Str::random(40),
                'email_verified_at' => now(),
                'is_demo_visitor' => true,
            ])->save();

            $shop = $this->build($visitor, referrer: $referrer, profile: self::profileFor($invite));
            $visit = DemoVisit::query()->create(['pitcher_id' => $referrer?->id, 'demo_invite_id' => $invite?->id, 'visitor_id' => $visitor->id]);

            return [$visitor, $shop, $visit];
        });

        if ($referrer !== null) {
            $referrer->increment('demo_link_starts');
        }

        if ($invite !== null) {
            $invite->forceFill(['starts' => $invite->starts + 1, 'last_started_at' => now()])->save();
        }

        ActivityLog::record('demo.visitor_started', null, $referrer, ['visitor_id' => $visitor->id, 'invite_id' => $invite?->id]);

        return [$visitor, $shop, $visit];
    }

    /**
     * Naam, plaats en kleur van de demozaak: die van de persoonlijke uitnodiging, anders Studio Fade in Utrecht.
     *
     * @return array{name: string, city: string, accent: string, personal: bool}
     */
    public static function profileFor(?DemoInvite $invite): array
    {
        return [
            'name' => $invite !== null ? $invite->shop_name : self::DEFAULT_NAME,
            'city' => $invite !== null && filled($invite->city) ? (string) $invite->city : self::DEFAULT_CITY,
            'accent' => $invite !== null && filled($invite->accent_color) ? (string) $invite->accent_color : self::DEFAULT_ACCENT,
            'personal' => $invite !== null,
        ];
    }

    /**
     * De laatste gestarte demo van een bezoeker (met de uitnodiging, als die er was).
     */
    public function visitFor(User $visitor): ?DemoVisit
    {
        return DemoVisit::query()->where('visitor_id', $visitor->id)->latest('id')->first();
    }

    /**
     * Opnieuw beginnen vanuit de openbare demo: dezelfde bezoeker krijgt een verse demozaak, met dezelfde pitcher.
     */
    public function restartForVisitor(User $visitor): Barbershop
    {
        abort_unless($visitor->is_demo_visitor, 403);

        $previous = Barbershop::query()->where('is_demo', true)->whereHas('users', fn ($query) => $query->whereKey($visitor->id))->get();
        $referrer = $previous->first()?->demo_referrer_id !== null ? User::query()->find($previous->first()->demo_referrer_id) : null;
        $invite = $this->visitFor($visitor)?->invite;

        foreach ($previous as $shop) {
            $this->delete($shop, keepVisitors: true);
        }

        $shop = DB::transaction(fn (): Barbershop => $this->build($visitor, referrer: $referrer, profile: self::profileFor($invite)));
        $visitor->forgetShopRoles();

        return $shop;
    }

    /**
     * De bezoeker is klaar met de demo (bijvoorbeeld omdat hij zijn eigen zaak gaat aanmelden): wis alles meteen.
     */
    public function endVisit(User $visitor): void
    {
        abort_unless($visitor->is_demo_visitor, 403);

        Barbershop::query()->where('is_demo', true)->whereHas('users', fn ($query) => $query->whereKey($visitor->id))
            ->get()->each(fn (Barbershop $shop) => $this->delete($shop));

        $visitor->delete();
    }

    /**
     * Een "live" online boeking in een demozaak, zodat de agenda voelt als een echte drukke dag: een bestaande
     * voorbeeldklant boekt een vrije tijd vandaag (of de eerstvolgende open dag). Rechtstreeks in de database, dus er
     * gaat nooit een bericht uit. Geeft de afspraak terug, of null als er niets vrij is.
     */
    public function simulateOnlineBooking(Barbershop $shop): ?Appointment
    {
        abort_unless($shop->is_demo, 403);

        $services = Service::withoutGlobalScopes()->where('barbershop_id', $shop->id)->where('is_active', true)->orderBy('sort_order')->get();
        $customerId = DB::table('customers')->where('barbershop_id', $shop->id)->inRandomOrder()->value('id');

        if ($services->isEmpty() || $customerId === null) {
            return null;
        }

        // Meestal een gewone knipbeurt, soms iets anders.
        $service = mt_rand(1, 100) <= 60 ? $services->first() : $services->random();
        $now = CarbonImmutable::now($shop->timezone);
        $availability = app(AvailabilityService::class);

        for ($offset = 0; $offset <= 6; $offset++) {
            $date = $now->addDays($offset)->toDateString();
            $slots = array_values(array_filter(
                $availability->slots($shop, $service, null, $date, online: false),
                fn (CarbonImmutable $slot): bool => $slot->greaterThan($now->addMinutes(45)),
            ));

            if ($slots === []) {
                continue;
            }

            $start = $slots[mt_rand(0, min(5, count($slots) - 1))];
            $staff = $availability->freeStaffAt($shop, $service, null, $start, online: false)[0] ?? null;

            if ($staff === null) {
                continue;
            }

            $end = $start->addMinutes($service->duration);
            $stamp = now()->utc()->format('Y-m-d H:i:s');

            $id = DB::table('appointments')->insertGetId([
                'barbershop_id' => $shop->id,
                'customer_id' => $customerId,
                'staff_member_id' => $staff->id,
                'service_id' => $service->id,
                'service_name' => $service->name,
                'duration' => $service->duration,
                'price_cents' => $service->price_cents,
                'starts_at' => $start->utc()->format('Y-m-d H:i:s'),
                'ends_at' => $end->utc()->format('Y-m-d H:i:s'),
                'blocked_until' => $end->addMinutes((int) $service->buffer_after)->utc()->format('Y-m-d H:i:s'),
                'status' => AppointmentStatus::Scheduled->value,
                'source' => AppointmentSource::Online->value,
                'manage_token' => Str::random(40),
                'created_at' => $stamp,
                'updated_at' => $stamp,
            ]);

            $shop->forceFill(['demo_live_bookings' => $shop->demo_live_bookings + 1, 'demo_live_booking_at' => now()])->save();

            return Appointment::withoutGlobalScopes()->with(['customer', 'staffMember'])->find($id);
        }

        return null;
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
    public function delete(Barbershop $shop, bool $keepVisitors = false): void
    {
        abort_unless($shop->is_demo, 403);

        DB::transaction(function () use ($shop, $keepVisitors): void {
            // Het tijdelijke account van een bezoeker van de openbare demolink hoort bij de zaak en gaat mee.
            $visitorIds = $keepVisitors ? [] : DB::table('shop_user')->join('users', 'users.id', '=', 'shop_user.user_id')
                ->where('shop_user.barbershop_id', $shop->id)->where('users.is_demo_visitor', true)->pluck('users.id')->all();

            // Afspraken eerst: die houden klanten, kappers en diensten vast (restrict).
            DB::table('appointments')->where('barbershop_id', $shop->id)->delete();
            DB::table('barbershops')->where('id', $shop->id)->where('is_demo', true)->delete();

            if ($visitorIds !== []) {
                DB::table('sessions')->whereIn('user_id', $visitorIds)->delete();
                User::query()->whereIn('id', $visitorIds)->where('is_demo_visitor', true)->delete();
            }
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

        // Demo-accounts die (door een fout of een half afgebroken start) geen zaak meer hebben.
        User::query()->where('is_demo_visitor', true)->where('created_at', '<=', now()->subHours((int) config('barbercuts.demo.hours')))
            ->whereDoesntHave('shops')->delete();

        return $expired->count();
    }

    /**
     * @param  User  $owner  wie de zaak beheert: de pitcher zelf, of het tijdelijke account van een bezoeker
     * @param  User|null  $pitcher  de pitcher van wie dit zijn eigen demo is
     * @param  User|null  $referrer  de pitcher van de openbare demolink
     * @param  array{name: string, city: string, accent: string, personal: bool}|null  $profile  naam, plaats en kleur
     */
    private function build(User $owner, ?User $pitcher = null, ?User $referrer = null, ?array $profile = null): Barbershop
    {
        $now = now();
        $profile ??= self::profileFor(null);
        $this->city = $profile['city'];

        $shop = new Barbershop([
            'slug' => 'demo-'.Str::lower(Str::random(10)),
            'name' => $profile['name'],
            'description' => 'Barbershop in hartje '.$profile['city'].'. Strakke fades, verzorgde baarden en altijd goede koffie.',
            'phone' => '+31301234567',
            'email' => $pitcher !== null ? $pitcher->email : 'info@zaak.'.self::VISITOR_EMAIL_DOMAIN,
            'address' => 'Voorbeeldstraat 12',
            // De postcode hoort bij Utrecht; bij een andere plaats laten we hem weg.
            'postal_code' => $profile['city'] === self::DEFAULT_CITY ? '3511 AB' : null,
            'city' => $profile['city'],
            'accent_color' => $profile['accent'],
        ]);
        $shop->is_public = true;
        $shop->is_demo = true;
        $shop->demo_expires_at = $now->copy()->addHours((int) config('barbercuts.demo.hours'));
        $shop->demo_pitcher_id = $pitcher?->id;
        $shop->demo_referrer_id = $referrer?->id;
        $shop->save();

        $shop->users()->attach($owner->id, ['role' => ShopRole::Owner->value]);

        $settings = new ShopSetting;
        $settings->barbershop_id = $shop->id;
        $settings->save();

        foreach ([2, 3, 4, 5, 6] as $day) {
            DB::table('opening_hours')->insert(['barbershop_id' => $shop->id, 'day_of_week' => $day, 'opens_at' => '09:00:00', 'closes_at' => '18:00:00']);
        }

        $staff = [];

        foreach (self::STAFF as $index => $member) {
            // De eigenaar is zelf de eerste kapper, zodat "eigen agenda" en "eigen omzet" te laten zien zijn.
            $id = DB::table('staff_members')->insertGetId([
                'barbershop_id' => $shop->id,
                'user_id' => $index === 0 ? $owner->id : null,
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
        $this->insertLoyalty($shop);

        return $shop;
    }

    /**
     * Een actieve spaarkaart (10 stempels, gratis afspraak); elke klant heeft zoveel stempels als bezoeken, en een paar zijn vol.
     */
    private function insertLoyalty(Barbershop $shop): void
    {
        DB::table('loyalty_cards')->insert([
            'barbershop_id' => $shop->id, 'name' => 'Spaarkaart', 'stamps_required' => 10, 'reward_type' => 'free',
            'reward_cents' => 0, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $visits = DB::table('appointments')->where('barbershop_id', $shop->id)->where('status', AppointmentStatus::Completed->value)
            ->groupBy('customer_id')->selectRaw('customer_id, COUNT(*) as visits')->pluck('visits', 'customer_id');

        foreach ($visits as $customerId => $count) {
            DB::table('customers')->where('id', $customerId)->update(['loyalty_stamps' => min(12, (int) $count * 2)]);
        }

        DB::table('appointments')->where('barbershop_id', $shop->id)->where('status', AppointmentStatus::Completed->value)->update(['loyalty_stamp' => true]);
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
                default => str_replace('Utrecht', $this->city, self::REVIEW_TEXTS[$rating][$index % count(self::REVIEW_TEXTS[$rating])]),
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
