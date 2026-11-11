# BarberCuts.nl — Bouwopdracht

> **Voor de chat die dit leest:** dit document is je volledige opdracht. Je bouwt **barbercuts.nl** vanaf nul als nieuw Laravel-project. Er is geen bestaande code waar je op voortbouwt.
>
> **Eerste actie:** zet dit document in de repo als `docs/BOUWOPDRACHT.md` en maak een `CLAUDE.md` met de regel *"Lees altijd eerst `docs/BOUWOPDRACHT.md`. Houd §15 (keuzes) bij."* Zo heeft elke volgende chat dezelfde context.
>
> Bij twijfel: **dit document > je eigen aannames.** Staat iets er niet in, kies dan de simpelste oplossing die op mobiel goed werkt, en noteer die keuze in §15.

---

## 1. Wat je bouwt

**BarberCuts** is een platform (SaaS) voor kapperszaken in heel Nederland.

- **Kapperszaken** melden zich zelf aan en beheren in één app hun **afspraken, agenda's per kapper, klanten, omzet, diensten en openingstijden**.
- Een zaak kan **meerdere kappers** hebben; **elke kapper heeft een eigen agenda**.
- **Klanten** boeken via de eigen pagina van een zaak: `barbercuts.nl/{zaak}`. Dat kan met of zonder account.
- **De platformeigenaar** beheert alles en ziet **alle statistieken** in een **Filament-adminpanel**.

**Prioriteiten, in deze volgorde:**
1. **Mobile.** Elk scherm wordt eerst gebouwd en getest op 375px breed (iPhone SE). Desktop komt daarna.
2. **Clean interface.** Rustig, licht, veel witruimte, één primaire knop per scherm, lijsten met kaarten in plaats van tabellen.
3. **Veilig per zaak.** Een zaak ziet nooit gegevens van een andere zaak, en een kapper ziet nooit de omzet van een collega. Dit wordt per fase getest.

---

## 2. Beslissingen van de eigenaar (vast)

| # | Onderwerp | Beslissing |
|---|---|---|
| B1 | Project | Helemaal nieuw project, los van andere apps. Geen demozaken van echte bedrijven; de eigenaar voegt zelf testzaken toe. |
| B2 | Thema | Het platform (beheer, landingspagina, aanmelden) is **licht**. Elke zaak past haar **eigen thema** aan voor haar boekingspagina: licht/donker, accentkleur, logo en omslagfoto. |
| B3 | Logo | Er is nog geen logo. Gebruik een simpel woordmerk "barbercuts" in tekst, makkelijk te vervangen (één Blade-component `x-brand`). |
| B4 | Gastboeken | Boeken zonder account mag. Laat op een **duidelijke maar niet opdringerige** manier zien dat een account meer voordelen en overzicht geeft (zie §6.5). |
| B5 | Verplichte velden | **Naam + telefoonnummer verplicht**, e-mail optioneel. |
| B6 | Aanbetaling | De code voor aanbetalingen wordt **nu al voorbereid** (datamodel, interfaces, feature flag), maar staat **uit**. Activeren gebeurt in een latere update. |
| B7 | Kappers | Meerdere kappers per zaak, elk met een **eigen agenda en eigen werktijden**. De klant kiest een kapper of "Geen voorkeur". |
| B8 | Aanmelden | Kapperszaken melden zich **zelf** aan. De platformeigenaar heeft een **Filament-adminpanel** met **alle statistieken**. |
| B9 | Omzet zien | Een kapper ziet **alleen zijn eigen omzet**. De **eigenaar** van de zaak ziet het **totaal** en een uitsplitsing per kapper. |
| B10 | Prijs | **14 dagen gratis**, daarna **€10 per maand**. De prijs moet **later aan te passen** zijn via het adminpanel (niet hardcoden). |
| B11 | Betalen | **Mollie**. |
| B12 | Start | Eerst **gratis** lanceren. De eigenaar moet via het adminpanel een zaak makkelijk een **gratis abonnement voor een bepaalde periode** kunnen geven. |
| B13 | Notificaties | Eerst **e-mail**. Daarna **web push** (PWA). **SMS en WhatsApp** moeten ook uitgewerkt worden (via aparte drivers). |
| B14 | Herinneringen | Standaard **24 uur** voor de afspraak. De zaak kan de tijd van de **1e herinnering aanpassen** en een **2e herinnering** instellen. |
| B15 | Hosting | Laravel op een **eigen VPS** (zie §13). |
| B16 | Data | Er is geen bestaande data om te migreren. |

---

## 3. Tech stack

- **Laravel 13**, PHP 8.4+ (8.5 als de VPS het heeft)
- **Livewire 4** voor alle interactieve schermen (boekflow, agenda, formulieren)
- **Filament** voor `/admin` (de nieuwste versie die Laravel 13 + Livewire 4 ondersteunt; controleer de compatibiliteit voordat je installeert)
- **Tailwind CSS v4** via Vite, met design tokens (§10)
- **MySQL 8** (ook lokaal en in CI, zodat locks en hoofdlettergevoeligheid hetzelfde werken als in productie) — SQLite alleen voor snelle unit-tests mag, maar de feature-tests draaien in CI op MySQL
- **Redis** voor queue, cache en sessies
- **Pest** voor tests, **Playwright** voor mobiele screenshots en tests van de flows
- **Laravel Pint** voor codestijl, **Larastan** (niveau 6+) voor statische analyse
- **Mollie:** `mollie/laravel-mollie` (API). Gebruik Cashier Mollie alleen als die Laravel 13 ondersteunt; anders bouw je de abonnementslogica zelf (§7.7). Dat is goed te doen.
- **Telefoonnummers:** `propaganistas/laravel-phone` (libphonenumber); opslaan als E.164 (`+31612345678`)
- **Web push:** `laravel-notification-channels/webpush`
- **Laravel Boost:** installeer het na het aanmaken van het project (`composer require laravel/boost --dev && php artisan boost:install`) en volg de gegenereerde richtlijnen

Taal: **UI in het Nederlands**, code, klassen, kolommen en routenamen in het **Engels**. Datum- en geldnotatie volgens NL: `€ 1.234,50`, `do 14 nov`.

---

## 4. URL-structuur

Alles op **één domein**, op basis van paden (één SSL-certificaat, geen wildcard-DNS).

| URL | Voor wie | Wat |
|---|---|---|
| `/` | iedereen | landingspagina + "Start gratis" |
| `/prijzen`, `/privacy`, `/voorwaarden`, `/contact` | iedereen | statische pagina's |
| `/aanmelden` | kappers | zaak aanmelden (onboarding, §8) |
| `/inloggen`, `/registreren`, `/wachtwoord-vergeten` | iedereen | auth |
| `/app/...` | eigenaar + kappers | de beheeromgeving van de zaak |
| `/account/...` | klanten | eigen afspraken bij alle zaken, profiel, notificatievoorkeuren |
| `/afspraak/{token}` | klanten (ook gasten) | afspraak bekijken, verplaatsen, annuleren, `.ics` downloaden |
| `/admin/...` | platformeigenaar | Filament |
| `/{slug}` | klanten | publieke pagina van een zaak |
| `/{slug}/boeken` | klanten | boekflow |

**Gereserveerde slugs** (validatie bij aanmelden + config-lijst): `app, admin, account, afspraak, aanmelden, inloggen, registreren, uitloggen, wachtwoord-vergeten, prijzen, privacy, voorwaarden, contact, over, help, api, up, webhooks, storage, build, livewire, filament, css, js, images, kappers, zoeken`.

Registreer de `/{slug}`-routes **als laatste**.

---

## 5. Datamodel

> Alle bedragen in **centen** (`unsignedInteger`). Alle tijden in de database in **UTC**; omrekenen naar `barbershops.timezone` (standaard `Europe/Amsterdam`) bij invoer en weergave.

```text
users
  id, name, email (unique, nullable alleen als er later inloggen met telefoon komt), phone (E.164, nullable),
  password, is_admin (bool, default false), email_verified_at, remember_token, timestamps

barbershops                                  -- de tenant
  id, slug (unique), name, description?, phone?, email?,
  address?, postal_code?, city?, latitude?, longitude?,
  timezone (default 'Europe/Amsterdam'),
  -- thema (B2)
  theme_mode (light|dark, default light), accent_color (hex, default #111827), logo_path?, cover_path?,
  is_public (bool, default false — pas true na onboarding),
  -- abonnement (§7.7)
  trial_ends_at?, free_until?, free_reason?, suspended_at?, suspended_reason?,
  plan_id? → plans, mollie_customer_id?, mollie_mandate_id?,
  timestamps

shop_user                                    -- wie mag inloggen in /app van welke zaak
  id, barbershop_id, user_id, role (owner|barber), timestamps
  unique(barbershop_id, user_id)

staff_members                                -- de kappers met een agenda (B7)
  id, barbershop_id, user_id? (gekoppeld account; null = kapper zonder login, eigenaar beheert),
  name, color (hex, voor de agenda), avatar_path?, bio?, is_active (bool), sort_order, timestamps
  index(barbershop_id, is_active)

services
  id, barbershop_id, name, description?, duration (minuten), price_cents,
  buffer_after (minuten, default 0), is_active, sort_order,
  -- aanbetaling, voorbereid (B6)
  deposit_type (none|fixed|percent, default none), deposit_value (centen of procent, default 0),
  timestamps

service_staff_member                         -- welke kapper doet welke dienst (leeg = iedereen)
  service_id, staff_member_id  (primary key samen)

opening_hours                                -- openingstijden van de zaak (publiek + buitengrens)
  id, barbershop_id, day_of_week (1=ma … 7=zo), opens_at (time), closes_at (time)
  -- meerdere rijen per dag mogelijk; geen rij = gesloten

working_hours                                -- werktijden per kapper (eigen agenda)
  id, staff_member_id, day_of_week, starts_at (time), ends_at (time)
  -- meerdere blokken per dag = pauze ertussen (bv. 09:00–12:30 en 13:00–18:00)

time_off                                     -- vrij, vakantie, ziek, of extra/afwijkende uren
  id, barbershop_id, staff_member_id? (null = hele zaak dicht),
  starts_at (datetime), ends_at (datetime), type (closed|custom_hours), reason?, timestamps
  -- custom_hours: in deze periode gelden alleen deze uren (bv. koopavond)

customers                                    -- klantenbestand PER ZAAK
  id, barbershop_id, user_id? (gekoppeld account), name, phone (E.164), email?,
  notes?, marketing_opt_in (bool), no_show_count (int, default 0), last_visit_at?, timestamps
  unique(barbershop_id, phone), unique(barbershop_id, user_id), index(barbershop_id, name)

appointments
  id, barbershop_id, customer_id, staff_member_id, service_id,
  service_name, duration, price_cents,                 -- snapshot bij boeken
  starts_at, ends_at,                                  -- ends_at = starts_at + duration (buffer telt NIET mee in ends_at)
  blocked_until,                                       -- ends_at + buffer_after; gebruikt voor overlap
  status (scheduled|completed|cancelled|no_show),
  source (online|staff|walk_in),
  notes?, manage_token (unique, 40 tekens random),
  amount_paid_cents?, tip_cents?, payment_method? (pin|cash|tikkie|online|other), completed_at?,
  cancelled_at?, cancelled_by? (customer|staff|system), cancellation_reason?,
  -- aanbetaling, voorbereid (B6)
  deposit_cents (default 0), deposit_status (none|pending|paid|refunded|forfeited, default none),
  created_by_user_id?, timestamps
  index(barbershop_id, starts_at), index(staff_member_id, starts_at), index(customer_id, starts_at)

shop_settings                                -- 1-op-1 met barbershops
  id, barbershop_id (unique),
  slot_interval (min, default 15), min_notice (min, default 60), max_days_ahead (default 60),
  customer_cancel_limit (min, default 120), customer_reschedule_limit (min, default 120),
  allow_guest_booking (bool, default true), allow_staff_choice (bool, default true),
  reminder_1_minutes (default 1440 = 24 uur), reminder_2_minutes? (null = uit, bv. 120 = 2 uur),
  reminder_channels (json, default ["mail"]),            -- mail, push, sms, whatsapp
  sms_enabled (bool, default false), whatsapp_enabled (bool, default false),
  timestamps

appointment_notifications                    -- geplande en verstuurde berichten per afspraak
  id, appointment_id, type (confirmation|reminder_1|reminder_2|rescheduled|cancelled),
  send_at, sent_at?, channel?, status (pending|sent|failed|skipped), error?, timestamps
  index(status, send_at)

message_logs                                 -- elk verstuurd bericht, voor statistiek en kosten
  id, barbershop_id?, channel (mail|push|sms|whatsapp), to, type, cost_cents?, provider_id?, status, timestamps

push_subscriptions                           -- van laravel-notification-channels/webpush

plans
  id, name, price_cents (1000), interval (month), trial_days (14), is_active, timestamps
  -- de prijs is hier aan te passen in Filament (B10)

subscriptions
  id, barbershop_id, plan_id, mollie_subscription_id?, status (active|past_due|cancelled),
  price_cents (vastgelegd bij start), current_period_ends_at?, cancelled_at?, timestamps

payments                                     -- alle Mollie-betalingen (abonnement én later aanbetalingen)
  id, barbershop_id, payable_type, payable_id (morph: subscription of appointment),
  mollie_payment_id (unique), amount_cents, status, paid_at?, refunded_at?, metadata (json), timestamps

shop_images                                  -- foto's voor de publieke pagina
  id, barbershop_id, path, sort_order, timestamps

activity_log                                 -- wie deed wat (afspraak geannuleerd door…, admin gaf gratis periode…)
  id, barbershop_id?, user_id?, action, subject_type, subject_id, properties (json), created_at
```

---

## 6. Rollen, rechten en tenancy

### 6.1 Rollen

| Rol | Waar vastgelegd | Wat |
|---|---|---|
| Platformadmin | `users.is_admin` | alleen `/admin` (Filament) |
| Eigenaar | `shop_user.role = owner` | alles binnen de eigen zaak |
| Kapper | `shop_user.role = barber` + `staff_members.user_id` | eigen agenda, klanten van de zaak, **eigen** omzet |
| Klant | `users` zonder lidmaatschap, met `customers.user_id` | `/account`, eigen afspraken |
| Gast | geen account; `customers.user_id = null` | alleen via `/afspraak/{token}` |

Eén user kan tegelijk eigenaar van een zaak én klant bij een andere zaak zijn.

### 6.2 Rechten

| Actie | Eigenaar | Kapper | Klant / gast |
|---|---|---|---|
| Agenda's van alle kappers zien | ✅ | ✅ (alleen-lezen voor collega's) | — |
| Afspraak maken/verplaatsen/annuleren | ✅ alle | ✅ in eigen agenda | eigen, binnen limieten |
| Afspraak voltooien / no-show | ✅ alle | ✅ eigen | — |
| Klanten zien en bewerken | ✅ | ✅ | — |
| **Omzet** | ✅ **totaal + per kapper** | ✅ **alleen eigen** (B9) | — |
| Diensten, openingstijden, thema, instellingen | ✅ | — | — |
| Kappers toevoegen/uitnodigen, eigen werktijden van kappers | ✅ | eigen werktijden en vrije dagen | — |
| Abonnement | ✅ | — | — |

Implementeer dit met **policies** (`AppointmentPolicy`, `CustomerPolicy`, `BarbershopPolicy`, `StaffMemberPolicy`) en een helper `User::roleIn(Barbershop): ?ShopRole` (enum). Geen losse `if`-jes in controllers.

### 6.3 Tenancy (één database)

- `App\Support\CurrentShop` (scoped singleton): `set()`, `get()`, `id()`.
- Middleware **`ResolveShopFromApp`** (op `/app`): neemt `session('current_shop_id')`, anders het eerste lidmaatschap. Geen lidmaatschap → redirect naar `/aanmelden`. Controleert dat de user lid is. Zaakwisselaar als iemand bij meerdere zaken hoort.
- Middleware **`ResolveShopFromSlug`** (op `/{slug}`): `Barbershop::where('slug', $slug)->where('is_public', true)->firstOrFail()`.
- Trait **`BelongsToShop`**: global scope `where barbershop_id = CurrentShop::id()` plus automatisch invullen bij `creating`. Op: `StaffMember`, `Service`, `OpeningHour`, `TimeOff`, `Customer`, `Appointment`, `ShopSetting`, `ShopImage`.
- Route model binding gaat zo automatisch via de scope: een id van een andere zaak geeft **404**.
- `/account` en `/afspraak/{token}` hebben **geen** tenant: daar filter je expliciet (`customers.user_id = auth()->id()` of op `manage_token`) met `withoutGlobalScope`, en alleen daar.
- Livewire: zet `CurrentShop` ook bij Livewire-updaterequests (persistent middleware van Livewire).
- **Filament** gebruikt geen tenant-scope (de admin ziet alles): zet de scope uit voor het admin-panel.

### 6.4 Omzetzichtbaarheid (B9)

`ShopStatsService` krijgt altijd een **viewer** mee:

```php
$scope = $viewer->roleIn($shop) === ShopRole::Owner
    ? null                                                    // alles + uitsplitsing per kapper
    : $shop->staffMembers()->where('user_id', $viewer->id)->value('id'); // alleen eigen

// $scope !== null  → where staff_member_id = $scope
```
Test: een kapper die met de hand een `staff_member_id` van een collega meestuurt, krijgt toch alleen eigen cijfers.

### 6.5 Gast vs. account (B4)

- In de boekflow is de stap "Gegevens" standaard **als gast**: naam (verplicht), telefoon (verplicht), e-mail (optioneel).
- Erboven een rustig blok (geen popup):
  > **Sneller boeken met een account** — al je afspraken op één plek · verplaatsen met één tik · herinneringen via push · je gegevens hoef je niet opnieuw in te vullen.
  > [Inloggen] [Account maken]
- Op het **bevestigingsscherm** voor gasten: kaart "Bewaar deze afspraak in je account" → één veld (wachtwoord, e-mail voorgevuld als die is ingevuld) → account maken en alle `customers`-records met hetzelfde telefoonnummer of e-mailadres koppelen.
- Is er geen e-mail ingevuld: laat zien "Zonder e-mail ontvang je geen bevestiging per mail" (en, als de zaak SMS aan heeft, "je krijgt een sms").
- **Klant koppelen:** zoek binnen de zaak op `phone` (E.164); bestaat die → bijwerken (naam/e-mail alleen invullen als ze leeg waren); anders nieuw record.

---

## 7. Kernlogica

### 7.1 Beschikbaarheid per kapper — `AvailabilityService`

Werk met **tijdsintervallen** in de tijdzone van de zaak.

```text
werkblokken(kapper, dag):
    1. time_off van de hele zaak met type=closed die de dag raakt  → die delen eraf
    2. time_off van de kapper type=custom_hours op die dag        → alleen die uren
       anders working_hours van de kapper voor die weekdag
    3. snijd af op opening_hours van de zaak voor die dag (als de zaak openingstijden heeft)
    4. trek time_off type=closed (zaak én kapper) eraf
    → lijst van [start, eind]

vrij(kapper, dag) = werkblokken − bezette afspraken van die kapper
                     (status scheduled of completed, interval [starts_at, blocked_until))

slots(dienst, kapper, dag):
    benodigd = dienst.duration + dienst.buffer_after
    voor elk vrij blok [a, b]:
        t = a, afgerond naar boven op het raster van slot_interval (gerekend vanaf middernacht)
        zolang t + dienst.duration ≤ b  en  t + benodigd ≤ b (buffer mag niet over het einde van het blok, behalve aan het einde van de werkdag):
            als t ≥ nu + min_notice  en  dag ≤ vandaag + max_days_ahead  → t is een slot
            t += slot_interval

slots(dienst, "geen voorkeur", dag) = unie van slots(dienst, k, dag) over alle actieve kappers die de dienst doen
```

- Een kapper kan alleen diensten die in `service_staff_member` staan (lege koppeling voor een dienst = iedereen).
- **Dagoverzicht in 3–4 queries:** `availableDays(dienst, kapper|null, van, tot)` laadt werktijden, openingstijden, time_off en afspraken voor de hele periode in één keer en rekent per dag in geheugen. De boekpagina toont 14 dagen in een swipebare datumstrip en laadt meer bij het doorswipen.
- **Zomer- en wintertijd:** reken per dag met `CarbonImmutable::parse("$datum $tijd", $shop->timezone)` en zet om naar UTC voor de database. Test de laatste zondag van maart en oktober.

### 7.2 Toewijzen bij "Geen voorkeur"

Kies uit de kappers die op dat tijdstip vrij zijn degene met de **minste geboekte minuten die dag**. Bij gelijkspel: laagste `sort_order`. Zo wordt het werk eerlijk verdeeld.

### 7.3 Boeken — één methode voor alle kanalen

```php
AppointmentService::book(BookingData $data, ?User $actor): Appointment

BookingData {
    Barbershop $shop; Service $service; ?StaffMember $staff;  // null = geen voorkeur
    CarbonImmutable $startsAt;                               // in UTC
    Customer|CustomerInput $customer;                        // bestaand of {name, phone, email?}
    AppointmentSource $source;                               // online|staff|walk_in
    ?string $notes;
}
```

Stappen:
1. **Validatie:** dienst hoort bij de zaak en is actief; gekozen kapper hoort bij de zaak, is actief en doet de dienst; zaak heeft toegang (§7.7) en `is_public` (bij online).
2. **Autorisatie:**
   - `actor = null` (gast) → alleen als `allow_guest_booking`.
   - `actor` is klant → boekt voor zichzelf (customer wordt gekoppeld aan `actor`).
   - `actor` is eigenaar → elke kapper; `actor` is kapper → in de eigen agenda (of alle, als de eigenaar dat toestaat — standaard alleen eigen).
3. **Klant vinden/aanmaken** (§6.5).
4. **Transactie:**
   - `lockForUpdate` op de rij(en) van de betrokken kapper(s) in `staff_members` (bij "geen voorkeur": alle kandidaten, gesorteerd op id om deadlocks te voorkomen).
   - Slot opnieuw berekenen; niet meer vrij → `BookingException('Dit tijdstip is net geboekt. Kies een andere tijd.')`.
   - Kapper toewijzen (§7.2).
   - Afspraak opslaan met snapshot (`service_name`, `duration`, `price_cents`), `blocked_until`, `manage_token`.
   - Aanbetaling berekenen als feature aan staat (§7.8); anders `deposit_cents = 0`.
5. **Na de commit** (`DB::afterCommit`): event `AppointmentBooked` → notificaties inplannen (§7.6).

**Andere acties** (allemaal in een transactie met lock op de kapper, autorisatie via policy):

| Methode | Regels |
|---|---|
| `reschedule(appt, newStart, ?newStaff, actor)` | alleen `scheduled`; klant alleen tot `customer_reschedule_limit` minuten vooraf; de eigen afspraak telt niet als bezet; herinneringen opnieuw inplannen; event `AppointmentRescheduled` |
| `cancel(appt, actor, ?reason)` | alleen `scheduled`; klant alleen tot `customer_cancel_limit`; zet `cancelled_at/by`; openstaande herinneringen op `skipped`; event `AppointmentCancelled` |
| `complete(appt, actor, amountPaidCents, tipCents, paymentMethod)` | alleen `scheduled` en alleen als de afspraak begonnen is; bedrag ≥ 0; `last_visit_at` van de klant bijwerken |
| `markNoShow(appt, actor)` | alleen `scheduled` en begonnen; `customers.no_show_count++` |
| `undoComplete(appt, actor)` | alleen `completed` en binnen 24 uur na `completed_at`; terug naar `scheduled` met bedragen leeg |

Fouten die de gebruiker moet zien zijn **`BookingException`** met een **Nederlandse, menselijke tekst**. Geen rechten = `AuthorizationException` (403), niet gevonden = 404.

### 7.4 Statussen

```text
scheduled ──cancel──────▶ cancelled
scheduled ──complete────▶ completed ──undoComplete (≤ 24 uur)──▶ scheduled
scheduled ──markNoShow──▶ no_show
```
Elke andere overgang → `BookingException`. Alleen `completed` telt als bezoek en als omzet. `cancelled` en `no_show` blokkeren geen tijd.

### 7.5 Omzet & statistieken — `ShopStatsService`

Altijd: per zaak, met de viewer-scope uit §6.4, periode in de tijdzone van de zaak, op basis van `starts_at`. Rekenen in de database (`SUM`/`COUNT` met `GROUP BY`), niet in PHP-collections.

| KPI | Formule |
|---|---|
| Omzet | Σ `amount_paid_cents` van completed |
| Fooi | Σ `tip_cents` van completed (apart tonen, niet in de omzet) |
| Afspraken | aantal completed |
| Gemiddelde besteding | omzet / afspraken |
| No-show % | no_show / (completed + no_show) |
| Bezettingsgraad | geboekte minuten (scheduled + completed) / beschikbare werkminuten (uit §7.1) |
| Nieuwe klanten | klanten met hun eerste completed afspraak in de periode |
| Terugkeer % | klanten met ≥ 2 completed afspraken in de laatste 90 dagen / unieke klanten in die 90 dagen |
| Per betaalmethode | Σ per `payment_method` (handig voor de kassa) |

- Perioden: vandaag, week, maand, jaar, eigen periode; altijd met vergelijking met de vorige periode ("+12% t.o.v. vorige week").
- Eigenaar: totaal + een lijst per kapper. Kapper: alleen eigen cijfers.
- **CSV-export** per maand (datum, tijd, klant, dienst, kapper, bedrag, fooi, betaalmethode) — eigenaar alleen; een kapper exporteert alleen eigen regels.

### 7.6 Notificaties & herinneringen (B13, B14)

**Inplannen in plaats van zoeken.** Bij boeken of verplaatsen maak je rijen in `appointment_notifications`:
- `confirmation` → `send_at = now()`
- `reminder_1` → `send_at = starts_at − reminder_1_minutes` (alleen als dat moment in de toekomst ligt)
- `reminder_2` → `send_at = starts_at − reminder_2_minutes` (alleen als ingesteld en in de toekomst)

Bij verplaatsen: openstaande herinneringen op `skipped` en nieuwe inplannen + `rescheduled`-bericht. Bij annuleren: openstaande op `skipped` + `cancelled`-bericht.

**Versturen:** de scheduler draait elke minuut `notifications:send-due`. Die pakt `status = pending AND send_at <= now()` (met `lockForUpdate` / `skip locked` tegen dubbel versturen), bepaalt het kanaal en verstuurt via de queue.

**Kanaalkeuze per bericht** (`NotificationChannelResolver`):
1. Klant heeft een account met push-abonnement en push staat in `reminder_channels` → **push**
2. Er is een e-mailadres → **mail**
3. Zaak heeft `sms_enabled` → **sms** (of **whatsapp** als dat aan staat en de klant dat kiest)
4. Niets mogelijk → `skipped` met reden

Een account kan in `/account` eigen voorkeuren zetten (bijvoorbeeld "ook sms").

**Drivers:** eigen Laravel Notification-kanalen `SmsChannel` en `WhatsAppChannel` met een interface:

```php
interface SmsProvider { public function send(string $toE164, string $message): ProviderResult; }
interface WhatsAppProvider { public function sendTemplate(string $toE164, string $template, array $params): ProviderResult; }
```
Implementaties: `LogSmsProvider` (lokaal/standaard), en later bijvoorbeeld Bird (MessageBird), Twilio of Spryng — kiezen via `.env` (`SMS_PROVIDER=log`). WhatsApp werkt met door Meta goedgekeurde templates. **Elk** bericht komt in `message_logs` (kanaal, kosten) voor de statistieken in Filament.

**Web push:** de PWA vraagt **pas na de eerste boeking** of na het inloggen om toestemming (nooit bij het eerste bezoek). Voor kappers: push bij een nieuwe online boeking of annulering in hun agenda.

**Inhoud** (kort, met de beheerlink):
> Herinnering: morgen 14:30 bij JF Cuts (Knippen, bij Sam). Verplaatsen of annuleren: barbercuts.nl/afspraak/AbC123…

Bevestigingen voor de kapper: e-mail en/of push bij online boekingen, annuleringen en verplaatsingen door klanten.

### 7.7 Abonnementen & toegang (B10, B11, B12)

**Toegangsstatus van een zaak** — één methode, overal gebruikt (middleware, Filament, banners):

```php
public function accessState(): AccessState
{
    if ($this->suspended_at)                                   return AccessState::Suspended; // door admin geblokkeerd
    if ($this->free_until?->isFuture())                        return AccessState::Free;      // gratis gegeven door admin
    if ($this->activeSubscription())                           return AccessState::Active;
    if ($this->trial_ends_at?->isFuture())                     return AccessState::Trial;
    if ($this->pastDueSubscriptionWithinGrace(days: 7))        return AccessState::PastDue;   // volledige toegang + banner
    return AccessState::Expired;
}
```

| Status | `/app` | Boekingspagina |
|---|---|---|
| Trial / Free / Active | volledig | open |
| PastDue | volledig + rode banner "Betaling mislukt" | open |
| Expired | **alleen lezen** + scherm "Abonnement starten" | "Online boeken is tijdelijk niet beschikbaar" + telefoonnummer van de zaak |
| Suspended | alleen een melding | niet beschikbaar |

**Nooit data verwijderen** bij verlopen of blokkeren.

**Gratis start (B12):** `config('barbercuts.billing.enabled')` (env `BILLING_ENABLED=false`). Zolang dat uit staat, krijgt elke nieuwe zaak automatisch `free_until = null` en werkt alles alsof de status `Free` is (geen betaalschermen). Daarnaast kan de admin in Filament per zaak een **gratis periode** geven (datum + reden), ook als facturering aan staat.

**Mollie-flow (als facturering aan staat):**
1. Eigenaar klikt "Abonnement starten" → Mollie-customer aanmaken (opslaan in `mollie_customer_id`).
2. Eerste betaling met `sequenceType = first` (iDEAL, bedrag = `plans.price_cents`) → redirect naar Mollie.
3. Webhook `/webhooks/mollie` (CSRF uitzetten, status **altijd opnieuw ophalen bij Mollie**, nooit de payload vertrouwen) → betaald? → mandaat opslaan → Mollie-subscription aanmaken met `startDate` = het einde van de trial of gratis periode (dan wordt er nooit dubbel betaald).
4. Terugkerende betalingen komen via dezelfde webhook binnen → `payments`-rij, `current_period_ends_at` bijwerken. Mislukt → `past_due`.
5. Prijs wijzigen in Filament geldt voor **nieuwe** abonnementen. Bestaande abonnementen behouden `subscriptions.price_cents`, tenzij de admin "Nieuwe prijs toepassen op bestaande abonnementen" kiest (dan de Mollie-subscription updaten en de eigenaar mailen).
6. Opzeggen: tot het einde van de betaalde periode blijft de toegang `Active`.

Facturen: Mollie stuurt geen facturen; maak een simpele pdf-factuur per betaling (met btw) en mail die. Mag in een latere fase.

### 7.8 Aanbetaling — voorbereid, staat uit (B6)

Nu bouwen, maar **verborgen** achter `config('barbercuts.features.deposits')` (env `FEATURE_DEPOSITS=false`):
- Kolommen op `services` en `appointments` (§5) en de `payments`-morph.
- `DepositCalculator::for(Service): int` → `none` = 0, `fixed` = `deposit_value`, `percent` = `round(price_cents * deposit_value / 100)`.
- Interface `PaymentGateway { createPayment(...); refund(...); }` met `MolliePaymentGateway`.
- In `book()`: als de feature aan staat en `deposit_cents > 0` → afspraak met status `scheduled` en `deposit_status = pending`, de klant gaat naar Mollie; bij niet betalen binnen 15 minuten → automatisch annuleren (job). Bij annuleren binnen de limiet → `refunded`; bij no-show → `forfeited`.
- **Let op:** aanbetalingen moeten naar de **Mollie-rekening van de kapper** gaan, niet naar het platform. Dat vraagt **Mollie Connect** (OAuth per zaak). Leg alleen de kolommen (`mollie_connect_*`) en de interface vast; de koppeling zelf komt in de latere update.
- Tests voor de calculator en voor "feature uit = geen aanbetaling, geen UI".

### 7.9 Thema per zaak (B2)

- Instellingen → "Uiterlijk": licht/donker, accentkleur (kleurkiezer + 8 voorgestelde kleuren), logo, omslagfoto. **Live voorbeeld** van de boekingspagina op hetzelfde scherm.
- Op de publieke pagina's: `<html data-theme="{{ $shop->theme_mode }}" style="--accent: {{ $shop->accent_color }}; --on-accent: {{ $shop->onAccentColor() }}">`.
- `onAccentColor()` kiest automatisch wit of zwart voor de tekst op knoppen (WCAG-contrast ≥ 4.5:1). Te weinig contrast met de achtergrond → waarschuwing in de instellingen.
- `/app`, `/account`, `/admin` en de platformpagina's blijven altijd **licht** in de BarberCuts-stijl.

---

## 8. Onboarding van een nieuwe zaak (doel: < 3 minuten, op een telefoon)

```text
/aanmelden
1. Account        naam, e-mail, wachtwoord (of al ingelogd → overslaan)
2. Zaak           naam zaak, plaats, telefoon → slug voorstellen ("jf-cuts", live check of die vrij is)
3. Kappers        "Werk je alleen of met meer kappers?" → alleen = eigenaar wordt de eerste kapper;
                  meer = namen toevoegen (uitnodigen per e-mail kan later)
4. Tijden         preset "di–za 09:00–18:00" → aanpassen per dag, pauze toevoegen
5. Diensten       voorgestelde lijst aanvinken en prijzen aanpassen:
                  Knippen 30 min € 25 · Knippen + baard 45 min € 35 · Baard 15 min € 15 · Kinderen 20 min € 18
6. Klaar          "Je boekingslink: barbercuts.nl/jf-cuts" → Kopiëren · Delen via WhatsApp · QR-code downloaden
```
- Na stap 2: `shop_user` (owner), `shop_settings`, `trial_ends_at = now + plans.trial_days` (of gratis als facturering uit staat), `is_public = false`. Na stap 6: `is_public = true`.
- Elke stap wordt meteen opgeslagen; wie halverwege stopt, gaat bij de volgende login verder bij de juiste stap.
- Op het dashboard daarna een **checklist**: logo uploaden · foto's toevoegen · thema kiezen · link in Instagram-bio zetten · eerste afspraak.
- **Kapper uitnodigen:** eigenaar vult naam + e-mail in → mail met link (signed URL, 7 dagen geldig) → account maken → `shop_user` (barber) + koppeling aan `staff_members.user_id`.

---

## 9. Schermen

### 9.1 Kapper-app (`/app`) — mobiel eerst

**Bottom navigation** (vast, met safe-area): `Vandaag` · `Agenda` · **`+`** (groot, in het midden) · `Klanten` · `Meer`
`Meer` = Omzet · Diensten · Kappers · Openingstijden · Uiterlijk · Instellingen · Abonnement · Boekingslink delen · Uitloggen.

1. **Vandaag** — bovenaan twee tegels: omzet vandaag (eigen of totaal, volgens §6.4) en het aantal afspraken. Daaronder de **eerstvolgende afspraak** uitgelicht, dan een tijdlijn van vandaag. Afspraakkaart: tijd, klantnaam, dienst, kapper (kleurbolletje), status-chip. Tikken → bottom sheet met *Voltooien* · *No-show* · *Verplaatsen* · *Annuleren* · *Bellen* · *WhatsApp*.
2. **Agenda** — swipebare weekstrip + dagweergave als tijdlijn. Kapperkeuze bovenaan als chips ("Iedereen", "Sam", "Mo"). Op mobiel één kapper tegelijk (swipen tussen kappers); vanaf `md` kolommen naast elkaar. Tik op een lege plek → nieuwe afspraak op dat tijdstip bij die kapper. Pauzes en vrije dagen grijs gearceerd.
3. **Nieuwe afspraak** (doel: telefonische boeking in **< 15 seconden**) — één scherm: klant zoeken of "+ nieuwe klant" (naam + telefoon) → dienst (chips) → kapper (voorgeselecteerd: jezelf) → vrije tijden (chips, de eerstvolgende bovenaan) → **Opslaan** (sticky onderaan). Ook "Inloop nu" (`walk_in`, start nu).
4. **Voltooien** — bottom sheet: bedrag (voorgevuld met de prijs, `inputmode="decimal"`), fooi (snelknoppen € 0 / 2 / 5 / anders), betaalmethode (4 grote knoppen: Pin · Contant · Tikkie · Anders), **Opslaan**. Maximaal 3 tikken als het bedrag klopt.
5. **Klanten** — sticky zoekbalk, lijst (naam, laatste bezoek). Klantdetail: bel- en WhatsApp-knop, notities ("fade 1,5 aan de zijkant"), bezoekhistorie, totale besteding (alleen eigenaar, of alleen eigen besteding bij een kapper), aantal no-shows, "Nieuwe afspraak".
6. **Omzet** — segmented control Dag · Week · Maand · Jaar, één groot getal + verschil met de vorige periode, eenvoudige staafgrafiek, KPI-tegels (§7.5), voor de eigenaar een lijst per kapper, knop "Exporteer CSV".
7. **Kappers** (eigenaar) — lijst, toevoegen/uitnodigen, per kapper: kleur, diensten, werktijden met pauzes, vrije dagen/vakantie.
8. **Openingstijden** — per dag aan/uit, blokken toevoegen (pauze), "kopieer naar alle dagen", plus "Gesloten op…" (feestdagen, vakantie).
9. **Instellingen** — boekregels (raster, minimale boektermijn, max. dagen vooruit, annuleer- en verplaatslimiet, gastboeken, kapperkeuze), herinneringen (1e herinnering: 24 u / 12 u / 3 u / anders; 2e herinnering: uit / 3 u / 2 u / 1 u / anders; kanalen).

### 9.2 Publiek (`/{slug}`) — in het thema van de zaak

1. **Zaakpagina** — omslagfoto, logo, naam, adres (tik → kaarten-app), telefoon, openingstijden (vandaag uitgelicht: "Nu open tot 18:00"), foto's (horizontale carrousel), diensten met prijs en duur, kappers met foto, sticky knop **"Afspraak maken"**.
2. **Boekflow** (`/{slug}/boeken`, maximaal 4 stappen, geen bottom nav, terugknop linksboven, gekozen opties als samenvatting bovenaan):
   1. **Dienst**
   2. **Kapper** ("Geen voorkeur" bovenaan; stap overslaan als de zaak één kapper heeft of `allow_staff_choice` uit staat)
   3. **Dag + tijd op één scherm** — datumstrip bovenaan (gesloten dagen grijs), tijden als chips eronder, gegroepeerd in Ochtend / Middag / Avond
   4. **Gegevens** — gast of inloggen (§6.5), opmerking (optioneel), **Bevestigen**
   → **Bevestigd** — samenvatting, "Zet in je agenda" (.ics), account-voordelen (§6.5), deel-/routeknop.
3. **Afspraak beheren** (`/afspraak/{token}`) — details, verplaatsen (zelfde dag+tijd-scherm), annuleren (met bevestiging), .ics. Na de limiet: "Bel de zaak om te wijzigen" + belknop.

### 9.3 Klantaccount (`/account`)

Komende afspraken (alle zaken) · Geschiedenis · "Opnieuw boeken" (zelfde dienst en kapper) · Profiel · Notificatievoorkeuren (mail/push/sms) · Account verwijderen (AVG).

### 9.4 Platform

Landingspagina (licht, rustig): kop "Afspraken, agenda en omzet voor je kapperszaak — op je telefoon", 3 voordelen, schermafbeeldingen van een telefoon, prijs "€ 10 per maand, 14 dagen gratis", knop **Start gratis**. Daarnaast prijzen, privacy, voorwaarden en een verwerkersovereenkomst (de zaak is verwerkingsverantwoordelijke voor de klantgegevens; BarberCuts is verwerker).

---

## 10. Design system (clean, licht, mobiel)

**Tokens** in `resources/css/app.css` (`@theme`):
- Kleuren platform: `--color-bg #ffffff`, `--color-surface #f7f7f8`, `--color-border #e7e7ea`, `--color-text #111114`, `--color-muted #6b6b76`, `--color-primary #111114` (zwarte knoppen; past bij elk logo later). Statussen: gepland `#2563eb`, voltooid `#16a34a`, geannuleerd `#9ca3af`, no-show `#dc2626`.
- Publieke pagina's: `--accent` / `--on-accent` per zaak + een donkere variant van bg/surface/text voor `data-theme="dark"`.
- Spacing 4 · 8 · 12 · 16 · 24 · 32 · 48. Radius 12px (kaarten, inputs), 999px (chips, knoppen in de bottom nav). Eén zachte schaduw.
- Typografie: één font (Inter), groottes 13 · 15 · 17 · 20, display 32 voor grote getallen. Inputs altijd ≥ 16px (anders zoomt iOS in).

**Blade-componenten** in `resources/views/components/ui/` — pagina's gebruiken **alleen** deze, geen losse styling:
`button` (primary/secondary/ghost/danger, `loading`-staat), `input`, `textarea`, `select`, `phone-input`, `money-input`, `card`, `list-item`, `chip`, `badge` (status), `stat`, `segmented`, `bottom-sheet`, `date-strip`, `time-grid`, `avatar`, `empty-state`, `toast`, `page-header` (titel + terugknop + actie), `bottom-nav`, `sticky-footer`.

**Regels:**
- Eén primaire knop per scherm, sticky onderaan binnen bereik van de duim.
- Tapdoelen ≥ 44×44px, minimaal 8px ertussen. Geen hover-afhankelijke functies. Geen tabellen op mobiel.
- Juiste toetsenborden en autofill: `type="tel"`, `inputmode="decimal"`, `autocomplete="name|tel|email"`.
- `viewport-fit=cover` + `env(safe-area-inset-*)`.
- `wire:navigate` voor paginawissels, `wire:loading`-skeletons, toasts boven de bottom nav (3 sec, `aria-live`).
- Lege staten met uitleg + één actie ("Nog geen diensten — Voeg je eerste dienst toe").
- Datums menselijk: "Vandaag 14:30", "Morgen", "do 14 nov". Bedragen via helper `money(int $cents)` → `€ 1.234,50`.
- Iconen: één set (Lucide of Heroicons outline, 24px).
- Toegankelijkheid: contrast ≥ 4.5:1, zichtbare focus, labels bij elk veld.
- **PWA:** `manifest.webmanifest` (naam, icons, `display: standalone`), service worker die alleen de app-shell en statische assets cachet (geen afspraakdata offline), "Zet op beginscherm"-tip voor kappers na de onboarding.
- Performance-budget: < 100 KB CSS + JS (gzip) op publieke pagina's, LCP < 2 s op 4G, afbeeldingen als WebP met vaste afmetingen en `loading="lazy"`.

---

## 11. Filament-adminpanel (`/admin`) — B8, B12

Alleen voor `users.is_admin = true` (`canAccessPanel`), met 2FA (Filament app-authenticatie). Geen tenant-scope.

**Dashboard-widgets (alle statistieken):**
- Zaken: totaal en per status (Trial · Free · Active · PastDue · Expired · Suspended), nieuwe aanmeldingen per week (grafiek), onboarding afgerond %.
- Geld: **MRR**, inkomsten deze maand, churn % per maand, conversie trial → betaald.
- Gebruik: afspraken per dag (grafiek, gemaakt én uitgevoerd), online vs. door kapper gemaakt, totale omzet verwerkt via het platform, gemiddelde no-show %, actieve kappers, klantaccounts vs. gasten.
- Berichten: verstuurd per kanaal, kosten SMS/WhatsApp deze maand, mislukte berichten.
- Top-lijsten: zaken met de meeste boekingen, steden met de meeste zaken.

Zware statistieken: dagelijks voorberekenen met een geplande job in een tabel `platform_daily_stats` (datum, sleutel, waarde), zodat het dashboard snel blijft.

**Resources:**
- **Barbershops** — lijst met filters op status en stad; detail met kerngegevens, kappers, aantallen en omzet. **Acties:**
  - *Geef gratis periode* (tot-datum of "3/6/12 maanden", reden) → `free_until`, `free_reason`, regel in `activity_log`
  - *Verleng trial* (aantal dagen)
  - *Blokkeren / deblokkeren* (reden)
  - *Inloggen als eigenaar* (impersonate, gelogd, met een duidelijke banner "Je bekijkt als …" en een terugknop)
  - *Boekingspagina openen*
- **Users** — zoeken, admin-rechten, e-mail verifiëren, wachtwoord-reset sturen.
- **Plans** — naam, `price_cents` (B10: hier pas je de €10 aan), trial-dagen, actief. Prijswijziging met de keuze "alleen nieuwe" of "ook bestaande" abonnementen (§7.7).
- **Subscriptions** en **Payments** — alleen lezen, met een link naar het Mollie-dashboard.
- **Appointments** — alleen lezen, over alle zaken (support).
- **Message logs** — alleen lezen, filteren op kanaal/status.
- **Activity log** — alleen lezen.
- **Instellingen** — feature flags (`deposits`, `sms`, `whatsapp`, `billing`) die de `.env`-standaard overschrijven, gereserveerde slugs.

---

## 12. Tests (verplicht, per fase)

- **Tenant-isolatie** (de belangrijkste set): zaak A en zaak B, elk met kappers, diensten, klanten en afspraken. Als eigenaar of kapper van A:
  - `/app/customers/{klant-van-B}`, `/app/appointments/{afspraak-van-B}` → 404
  - acties (annuleren, voltooien) op afspraken van B → 404
  - klantzoeker, agenda en omzet bevatten niets van B
  - Livewire-acties met id's van B → geweigerd
- **Omzetzichtbaarheid:** kapper ziet alleen eigen omzet, ook als hij de request manipuleert; eigenaar ziet totaal + per kapper.
- **Beschikbaarheid:** pauzes, time_off van zaak en kapper, custom_hours, openingstijden als buitengrens, buffer, min_notice, max_days_ahead, afspraak over een blokgrens, "geen voorkeur" (unie + eerlijk toewijzen), verplaatsen negeert de eigen afspraak, zomer- en wintertijd.
- **Boeken:** gast, ingelogde klant, eigenaar voor een klant zonder account, kapper alleen in eigen agenda, inactieve dienst, dienst van een andere zaak, kapper die de dienst niet doet, **dubbel boeken van hetzelfde slot → de tweede faalt**, telefoonnummer-normalisatie en koppeling aan een bestaande klant.
- **Statussen:** elke toegestane en elke niet-toegestane overgang, limieten voor klanten, undo binnen en na 24 uur.
- **Notificaties:** juiste `send_at` voor 1e en 2e herinnering, opnieuw inplannen bij verplaatsen, `skipped` bij annuleren, kanaalkeuze (push > mail > sms > skipped), nooit dubbel versturen.
- **Abonnement:** elke `accessState`, gratis periode van de admin gaat voor alles behalve blokkeren, Expired = alleen lezen + boekingspagina dicht, webhook haalt de status op bij Mollie (mock), `BILLING_ENABLED=false` → nergens betaalschermen.
- **Aanbetaling:** calculator; feature uit = geen bedrag en geen UI.
- **Filament:** een niet-admin krijgt 403 op `/admin`; actie "gratis periode" zet de velden en logt.
- **Mobiel (Playwright):** screenshots op 375×667 en 430×932 van elk hoofdscherm, plus checks: geen horizontale scroll, primaire knop zichtbaar zonder te scrollen in de boekflow, inputs ≥ 16px. De volledige boekflow als gast en de flow "Voltooien" als kapper als end-to-end-test.

---

## 13. Eigen VPS (B15)

Doel: Ubuntu 24.04 LTS.

1. **Server:** gebruiker `deploy` (geen root-login, SSH-key, wachtwoordlogin uit), `ufw` (22, 80, 443), `fail2ban`, automatische beveiligingsupdates (`unattended-upgrades`).
2. **Pakketten:** Nginx, PHP 8.4-FPM (extensies: `bcmath, curl, gd/imagick, intl, mbstring, mysql, redis, xml, zip, opcache`), MySQL 8, Redis, Supervisor, Node 22 (alleen voor de build), Composer, Certbot.
3. **Nginx:** vhost voor `barbercuts.nl` + `www` → redirect naar zonder www, root `/var/www/barbercuts/current/public`, HTTP/2, gzip/brotli, lange cache op `/build/*`, `client_max_body_size 10M` (foto's).
4. **SSL:** `certbot --nginx -d barbercuts.nl -d www.barbercuts.nl` + automatische vernieuwing.
5. **Queue:** Supervisor-programma `php artisan queue:work redis --tries=3 --max-time=3600` (2 processen) — of Laravel Horizon als je een dashboard wilt.
6. **Scheduler:** cron `* * * * * cd /var/www/barbercuts/current && php artisan schedule:run >> /dev/null 2>&1`.
7. **Deploy:** zero-downtime met releases-mappen (Deployer of een eigen `deploy.sh`): `git fetch` → nieuwe release-map → `composer install --no-dev --optimize-autoloader` → `npm ci && npm run build` → symlink naar `shared/.env` en `shared/storage` → `php artisan migrate --force` → `php artisan optimize` → symlink `current` omzetten → `php artisan queue:restart` → PHP-FPM herladen. Laatste 5 releases bewaren voor een rollback.
8. **CI/CD:** GitHub Actions: Pint, Larastan, Pest (MySQL- en Redis-service), Playwright; deploy naar **staging** (`staging.barbercuts.nl`, met basic-auth) bij elke push naar `main`, naar productie bij een tag of een handmatige trigger.
9. **Back-ups:** dagelijks `mysqldump` + `storage/app` met `spatie/laravel-backup` naar externe opslag in de EU (bijv. S3-compatible bij Scaleway/Hetzner), 30 dagen bewaren; maandelijks een terugzet-test.
10. **Monitoring:** `/up` laten controleren door een uptime-checker, foutmeldingen naar mail (of Sentry/Flare), logrotatie, en een melding als de queue achterloopt.
11. **Mail:** transactionele provider in de EU (Postmark of Mailgun EU) met SPF, DKIM en DMARC op `barbercuts.nl`.
12. **Bestanden:** de `public` disk lokaal is prima voor de start (met back-up); maak het via `FILESYSTEM_DISK` omzetbaar naar S3-compatible opslag.

Schrijf alles als stappenplan in `docs/DEPLOY.md`, inclusief de exacte config-bestanden (Nginx-vhost, Supervisor, cron, `deploy.sh`), zodat de eigenaar het op zijn VPS kan uitvoeren.

---

## 14. Fases & opleverlijst

Werk **fase voor fase**. Per fase: eigen branch + PR, tests groen, Pint en Larastan schoon, screenshots van nieuwe schermen in de PR, en de app werkt na elke fase. Begin pas aan een nieuwe fase als de "Klaar als"-lijst van de vorige klopt.

### Fase 0 — Fundament
- Nieuw Laravel 13-project, Livewire 4, Tailwind v4, Pest, Pint, Larastan, Laravel Boost.
- MySQL + Redis lokaal (bijv. via Laravel Sail of Herd) en in CI.
- Design tokens + alle `x-ui.*` componenten (§10) + een interne showcase-pagina `/_ui` (alleen in `local`).
- Basislayouts: `layouts.platform` (licht), `layouts.app` (kapper, met bottom nav), `layouts.shop` (publiek, met thema van de zaak).
- Auth (inloggen, registreren, wachtwoord vergeten, e-mailverificatie) in de eigen UI.
- `docs/DEPLOY.md` + staging op de VPS.
- **Klaar als:** CI is groen; de showcase ziet er goed uit op 375px; staging draait met SSL.

### Fase 1 — Zaken, kappers & tenancy
- Migraties voor §5 (behalve de berichten- en betaaltabellen).
- `CurrentShop`, middleware, `BelongsToShop`, policies, `roleIn()`.
- Onboarding (§8), kappers + uitnodigen, diensten (met koppeling aan kappers), openingstijden, werktijden met pauzes, time_off, instellingen, uiterlijk (thema + live voorbeeld).
- **Klaar als:** een nieuwe zaak zich op een telefoon in < 3 minuten aanmeldt; de tenant-isolatietests (§12) bestaan en slagen.

### Fase 2 — Boekingsengine & publieke boekingspagina
- `AvailabilityService`, `AppointmentService` (§7.1–7.4) met alle tests.
- Zaakpagina, boekflow, gast vs. account (§6.5), beheerlink, `.ics`.
- Aanbetaling-voorbereiding (§7.8, uit).
- Bevestigingsmail (eerste versie van §7.6, alleen `confirmation` via mail).
- Rate limiting op boeken (per IP en per telefoonnummer) + honeypot.
- **Klaar als:** een gast op 375px in < 60 seconden boekt zonder te zoomen of horizontaal te scrollen; dubbel boeken faalt in een test; screenshots van elke stap staan in de PR.

### Fase 3 — Kapper-app
- Vandaag, Agenda (per kapper), Nieuwe afspraak, Voltooien, Klanten, Omzet met de zichtbaarheidsregels (§6.4, §7.5), CSV-export.
- Pushmeldingen voor kappers bij nieuwe online boekingen (of mail, als push nog niet klaar is).
- PWA (manifest, icons, service worker).
- **Klaar als:** een telefonische boeking < 15 seconden kost; voltooien ≤ 3 tikken; een kapper ziet aantoonbaar alleen eigen omzet; de app is aan het beginscherm toe te voegen.

### Fase 4 — Filament-admin & abonnementen
- Filament-panel met alle widgets en resources (§11), inclusief "gratis periode geven", trial verlengen, blokkeren en impersonate.
- `plans` (€ 10/maand, 14 dagen, aan te passen), `accessState`, banners, alleen-lezen bij Expired.
- Mollie-abonnementen (§7.7) achter `BILLING_ENABLED` (standaard uit = gratis start).
- Landingspagina, prijzen, privacy, voorwaarden, verwerkersovereenkomst.
- **Klaar als:** de admin een zaak in 3 klikken een gratis periode geeft; met facturering aan doorloopt een testzaak de volledige Mollie-testflow (trial → eerste betaling → abonnement → mislukte betaling → past_due → expired).

### Fase 5 — Herinneringen & kanalen
- `appointment_notifications`, scheduler, kanaalkeuze, 1e en 2e herinnering instelbaar (§7.6).
- Web push voor klanten met een account.
- `SmsChannel` en `WhatsAppChannel` met `LogProvider` + één echte provider (eigenaar kiest welke), `message_logs`, kosten in Filament.
- Notificatievoorkeuren in `/account`.
- **Klaar als:** alle notificatietests slagen; een herinnering komt op het ingestelde moment aan via het juiste kanaal en wordt nooit dubbel verstuurd.

### Later (niet nu bouwen, wel rekening mee houden)
Aanbetaling activeren met Mollie Connect · reviews na een bezoek · wachtlijst bij volle dagen · kappersgids per stad (`/kappers/utrecht`, SEO met schema.org `HairSalon`) · eigen domein per zaak · producten verkopen · loyaliteitskaart · inloggen met telefoonnummer (sms-code) · Engelse vertaling.

---

## 15. Keuzes gemaakt door de chat

> Vul hier aan wat je zelf hebt besloten omdat het niet in dit document stond: **datum — keuze — reden.** Zo kan de eigenaar het nalezen en kan een volgende chat verder.

- **2026-11-10 — Livewire-componenten zijn class-based** (`app/Livewire` + `resources/views/livewire`), niet de single-file standaard van Livewire 4. Reden: Larastan (niveau 6) kan klassen controleren, en er komen geen emoji-bestandsnamen in de repo. Ingesteld in `config/livewire.php`.
- **2026-11-10 — Lokaal geen Redis.** Op de ontwikkelmachine staat geen Redis (en Docker draait niet); lokaal gebruiken sessies, cache en queue daarom de `database`-driver. `.env.example`, CI en productie gebruiken Redis. `predis` is geïnstalleerd zodat het ook zonder de PHP-extensie werkt; op de VPS `REDIS_CLIENT=phpredis`.
- **2026-11-10 — Tests draaien op MySQL** (`barbercuts_test`), ook lokaal en ook de unit-tests. Reden: zelfde gedrag als productie (§3); er is geen SQLite-pad dat apart onderhouden moet worden.
- **2026-11-10 — Iconen: Lucide, als eigen component `x-ui.icon`** met de paden inline, zonder extra pakket. Reden: klein houden (performance-budget §10). Nieuw icoon = één regel toevoegen.
- **2026-11-10 — Eén wachtwoordveld bij registreren en herstellen** (geen "herhaal wachtwoord"). Reden: sneller op mobiel; een typfout los je op met "Wachtwoord vergeten". Minimaal 8 tekens.
- **2026-11-10 — Extra auth-URL's:** `/wachtwoord-herstellen/{token}` en `/e-mail-bevestigen`. Beide toegevoegd aan de gereserveerde slugs, net als `_ui`. De lijst staat in `config/barbercuts.php`; een test controleert dat elke route op het eerste niveau gereserveerd is. Livewire gebruikt een pad met hash (`livewire-xxxx`): **slugs die met `livewire` beginnen moeten in fase 1 geweigerd worden.**
- **2026-11-10 — Primaire knoppen gebruiken `--accent` / `--on-accent`.** Op het platform is dat zwart/wit (`--color-primary`), op de pagina's van een zaak de kleur van de zaak. Zo is er één knop-component voor alles.
- **2026-11-10 — Typografie-tokens:** `text-xs` 13 · `text-sm` 15 (standaard lopende tekst) · `text-base` 17 (inputs en knoppen) · `text-lg` 20 · `text-display` 32. `x-ui.stat` gebruikt 26px, omdat "€ 482,50" op 32px niet in een halve kolom past op 375px; `text-display` is voor het ene grote getal op het omzetscherm.
- **2026-11-10 — Keuze-componenten (`chip`, `segmented`, `date-strip`, `time-grid`) zijn echte radio's.** `wire:model` gaat naar de input. Reden: werkt zonder JavaScript, met toetsenbord en schermlezer.
- **2026-11-10 — `/app` is voorlopig een lege "Vandaag"-pagina** (achter inloggen + bevestigd e-mailadres) en `/` een korte landingspagina. De echte schermen komen in fase 3 en 4.
- **2026-11-10 — Playwright-screenshots staan in `docs/screenshots/<fase>/`** en worden bij elke testrun vernieuwd (`npm run test:browser`).
- **2026-11-10 — Serverbestanden staan in `deploy/`** (Nginx, Supervisor, `deploy.sh`, `rollback.sh`); `docs/DEPLOY.md` verwijst ernaar. Deploy-jobs in CI draaien pas als de repo-variabele `DEPLOY_ENABLED=true` is gezet.
- **2026-11-10 — Staging op de VPS is nog niet opgezet** (fase 0, "Klaar als"). Daarvoor is toegang tot de VPS en een GitHub-repo nodig; het stappenplan staat klaar in `docs/DEPLOY.md`.
- **2026-11-10 — Tenant-scope is "fail closed".** Is er in een webrequest geen zaak gezet, dan geven modellen met `BelongsToShop` niets terug (in plaats van alles). Alleen de console (queue, scheduler, tinker) en code die `CurrentShop::disableScope()` aanroept (straks Filament) werken zonder scope. `CurrentShop::run($shop, fn)` voert code uit binnen één zaak.
- **2026-11-10 — Livewire-componenten in `/app` bewaren alleen een vergrendeld id** (`#[Locked]`) en halen het record bij elke actie opnieuw op via de scope. Een id van een andere zaak geeft daardoor altijd 404, ook bij gemanipuleerde requests.
- **2026-11-10 — Extra kolommen:** `barbershops.onboarding_step` (waar de eigenaar is gebleven; null = afgerond), `barbershops.link_shared_at` (voor de checklist "link in Instagram-bio": afgevinkt zodra de eigenaar de link kopieert), `staff_members.invited_email` en `invited_at` (uitnodiging). `barbershops.plan_id` heeft nog geen foreign key; die komt met de `plans`-tabel in fase 4. De proefperiode (14 dagen) staat tot dan in `config/barbercuts.php`.
- **2026-11-10 — `/app` vraagt geen bevestigd e-mailadres meer.** Anders haalt de onboarding de 3 minuten niet. Er staat een melding "Bevestig je e-mailadres" bovenaan tot het adres bevestigd is.
- **2026-11-10 — Onboarding stap 4 zet dezelfde tijden als openingstijden én als werktijden van elke kapper.** Per kapper aanpassen kan daarna bij Meer → Kappers. Een kapper die later wordt toegevoegd krijgt de openingstijden van de zaak als werktijden.
- **2026-11-10 — "Welke kapper doet welke dienst" stel je in bij de dienst**, niet bij de kapper. Alle kappers aangevinkt = geen koppeling opgeslagen (= iedereen, §7.1), zodat een nieuwe kapper de dienst automatisch ook doet.
- **2026-11-10 — Verwijderen:** een kapper of dienst met afspraken kan niet verwijderd worden, alleen op "niet actief" gezet. De laatste kapper van een zaak kan niet verwijderd worden.
- **2026-11-10 — Uitnodiging voor een kapper** is een gesigneerde link (7 dagen) op `/uitnodiging/{id}`; `uitnodiging` is gereserveerd als slug. Wie via de link een account maakt, heeft meteen een bevestigd e-mailadres. Bestaat er al een account met dat adres, dan moet de kapper eerst inloggen.
- **2026-11-10 — Uploads worden WebP** (GD): logo max. 400px, omslag max. 1600px, foto's max. 1200px, maximaal 12 foto's. Op de `public` disk onder `shops/{id}/`.
- **2026-11-10 — Zaakpagina `/{slug}` staat er in een eerste versie** (naam, diensten, kappers, openingstijden, foto's), omdat het live voorbeeld bij "Uiterlijk" dezelfde component gebruikt. De knop "Afspraak maken" verschijnt zodra de boekflow er is (fase 2).
- **2026-11-10 — QR-code is een SVG** (`bacon/bacon-qr-code`), te downloaden via `/app/qr-code`.
- **2026-11-10 — Nieuwe ui-componenten:** `toggle`, `alert`, `section`, `progress`, `icon-button`, `week-hours`, `copy-link`, `color-swatches`, `time-off-list`, `file-button`, en `x-shop.page` voor de zaakpagina.
- **2026-11-10 — Datums zonder afkortingspunt:** Carbon geeft in het Nederlands "20 jul."; de helper `nl_date()` haalt de punt weg ("ma 20 jul").
- **2026-11-10 — Back-ups (`spatie/laravel-backup`) zijn nog niet ingebouwd**; `docs/DEPLOY.md` noemde fase 1, dat schuift naar het moment dat de VPS er staat.
- **2026-11-10 — Het bevestigingsscherm is de beheerpagina `/afspraak/{token}`.** Na het boeken gaat de klant daar direct naartoe ("Je afspraak staat vast"). Zo werkt verversen of de pagina bewaren altijd, en is er één scherm voor bekijken, verplaatsen, annuleren en `.ics`.
- **2026-11-10 — Afspraken bij ándere zaken worden pas aan een nieuw account gekoppeld als het e-mailadres bevestigd is, en nooit automatisch op telefoonnummer.** §6.5 zegt: koppel alle klantrecords met hetzelfde nummer of adres. Dat zou betekenen dat iemand met andermans nummer of adres diens afspraken kan inzien. Nu: de afspraak van de beheerlink wordt direct gekoppeld; records met hetzelfde e-mailadres volgen na het klikken op de bevestigingslink. Koppelen op telefoonnummer kan zodra er verificatie per sms is.
- **2026-11-10 — De beheerpagina werkt binnen de zaak van de afspraak.** De afspraak wordt zonder scope op het token opgezocht (§6.3); daarna wordt die zaak als huidige zaak gezet. De relaties van `Appointment` naar klant, kapper en dienst slaan de scope over, zodat ze ook in `/account` en in mails werken.
- **2026-11-10 — Een klant verplaatst binnen de agenda van dezelfde kapper.** Naar een andere kapper verplaatsen kan alleen de eigenaar.
- **2026-11-10 — Ook de kapper boekt alleen binnen werktijden en vrije blokken.** Het verschil met online: geen raster, geen minimale boektermijn en geen maximum aantal dagen vooruit (nodig voor telefonisch boeken en inloop).
- **2026-11-10 — Openingstijden als buitengrens:** heeft de zaak openingstijden maar niet op een bepaalde weekdag, dan is de zaak die dag dicht, ook als een kapper dan werktijden heeft. Heeft de zaak helemaal geen openingstijden, dan gelden alleen de werktijden.
- **2026-11-10 — Rate limiting op boeken:** 10 per uur per IP-adres en 5 per uur per telefoonnummer, instelbaar via `BOOKING_MAX_PER_IP_PER_HOUR` en `BOOKING_MAX_PER_PHONE_PER_HOUR`. De Playwright-tests zetten de IP-limiet hoog, omdat alle tests van één adres komen. Daarnaast een honeypot-veld.
- **2026-11-10 — Aanbetaling:** kolommen, `payments`-tabel, `mollie_connect_*`-kolommen, `DepositCalculator`, de interface `PaymentGateway` en de job die een onbetaalde aanbetaling na 15 minuten annuleert staan er. `MolliePaymentGateway` zelf is nog niet geschreven: dat kan pas met het Mollie-pakket (fase 4) en Mollie Connect.
- **2026-11-10 — Bevestigingsmail:** alleen naar de klant en alleen als er een e-mailadres is; via de queue. Meldingen voor de kapper bij een online boeking komen in fase 3, herinneringen in fase 5.
- **2026-11-10 — `/account` staat er in een eerste versie** (komende afspraken, geschiedenis, opnieuw boeken). Na inloggen gaat wie bij een zaak hoort naar `/app`, anderen naar `/account`.
- **2026-11-10 — De toegangsstatus van de zaak (§7.7) wordt bij boeken nog niet gecontroleerd**; alleen `is_public`. Dat komt met `accessState()` in fase 4.
- **2026-11-10 — `slots` is in Livewire 4 een gereserveerde naam in views.** De componenten `time-grid` en `slot-picker` gebruiken daarom de prop `times`.
- **2026-11-10 — Meldingen voor de kapper gaan nu per mail, nog niet per push.** Bij een online boeking, annulering of verplaatsing door de klant krijgt de kapper een mail; heeft de kapper geen account, dan de eigenaar. Web push komt in fase 5, samen met push voor klanten.
- **2026-11-10 — Vandaag:** de eigenaar ziet de dag van alle kappers, een kapper alleen de eigen dag. In de Agenda ziet iedereen alle agenda's (collega's alleen-lezen).
- **2026-11-10 — Agenda "Iedereen":** de kolommen per kapper staan naast elkaar en scrollen op een telefoon horizontaal binnen de tijdlijn (de pagina zelf scrolt niet horizontaal). Standaard opent de agenda op de eigen kapper.
- **2026-11-10 — Blokken in de tijdlijn zijn zo hoog als de afspraak duurt** (1,6 px per minuut; 15 minuten = 24px). Dat is lager dan de 44px voor tapdoelen; de mobiele test slaat deze blokken daarom over (`data-timeline-item`).
- **2026-11-10 — Voltooien in 3 tikken:** afspraak → Voltooien → betaalmethode. De knop van de betaalmethode slaat meteen op; het bedrag is voorgevuld met de prijs.
- **2026-11-10 — Nieuwe afspraak en verplaatsen delen één scherm.** De eerste dienst en de eigen kapper zijn voorgeselecteerd. Een kapper kan alleen zichzelf kiezen; de eigenaar kan bij verplaatsen ook een andere kapper kiezen.
- **2026-11-10 — Grafiek per periode:** dag = per uur (07–21 uur), week en maand = per dag, jaar = per maand; de staven komen uit één query. "Terugkeer %" rekent over de 90 dagen tot het einde van de periode (of tot nu).
- **2026-11-10 — CSV-export** gebruikt `;` als scheidingsteken en een BOM, zodat Excel het bestand goed opent; alleen voltooide afspraken.
- **2026-11-10 — PWA:** `public/manifest.webmanifest`, iconen in `public/icons/` (zwart vlak met een witte "b", te vervangen zodra er een logo is), `public/sw.js` en `public/offline.html`. De service worker bewaart alleen `/build/*` en de iconen; pagina's komen altijd van het netwerk.
- **2026-11-10 — Conditionele blokken met invoervelden krijgen een `wire:key`.** Zonder sleutel hergebruikt Livewire bij het wisselen van blok een veld (het zoekveld werd het naamveld bij "Nieuwe klant").
- **2026-11-11 — Op de server staat `repo/` als `git clone --mirror`** (niet `--bare`) en `deploy.sh` deployt standaard `main`. Met `--bare` haalt `git fetch` geen nieuwe commits op in de lokale branches, waardoor `origin/main` niet bestond. De GitHub-repo is publiek, dus de server clonet via https zonder deploy key.
- **2026-11-11 — `deploy.sh` controleert eerst `shared/.env` en `APP_KEY`, ruimt een mislukte release zelf op en koppelt `public/storage` direct aan `shared/storage/app/public`** (geen `artisan storage:link`, dat wees naar de releasemap). `APP_KEY` wordt vóór de eerste deploy gegenereerd.
- **2026-11-11 — Nginx-configs zonder `http2 on;`:** Ubuntu 24.04 levert nginx 1.24, waar die regel niet bestaat. HTTP/2 staat als optionele stap in `docs/DEPLOY.md`. De `www`-redirect staat in een `location`, zodat certbot zijn challenge kan plaatsen.
- **2026-11-11 — De configbestanden op de server staan in `/var/www/barbercuts/current/deploy/`** (er is daar geen losse checkout).
- **2026-11-11 — Fase 4: Filament 5 voor `/admin`, Mollie via een eigen laag.** `mollie/laravel-mollie` (v5) zit achter de interface `BillingGateway`; tests gebruiken een nagebootste provider (`tests/Fakes`). Cashier Mollie is niet gebruikt (§3).
- **2026-11-11 — Eerste betaling = één maand vooruit, en het Mollie-abonnement begint daarna.** §7.7 zegt: abonnement start aan het einde van de proefperiode. Dan zou de eigenaar de eerste betaling nu doen én bij het einde van de proef weer. Nu: de eerste betaling dekt de maand vanaf het einde van de proefperiode/gratis periode (of vanaf nu bij een verlopen zaak); het Mollie-abonnement begint daarna (`startDate` = einde van die betaalde maand). Zo betaalt niemand dubbel.
- **2026-11-11 — Extra abonnementsstatus `pending`** (eerste betaling gestart, niet betaald). Geeft nooit toegang. Een mislukte of verlaten poging wordt `cancelled` zonder betaalde periode.
- **2026-11-11 — Alleen-lezen is een controle op modelniveau** (`ShopWriteGuard` in `BelongsToShop`, `Barbershop`, `WorkingHour`): elke wijziging door een lid van een verlopen of geblokkeerde zaak geeft een 403 met `X-Shop-Read-Only`, en de browser toont dan een toast. Klanten en gasten, de admin, de console en de queue worden niet geblokkeerd. Online boeken wordt apart geweigerd in `AppointmentService`. Betalen kan wél bij een verlopen zaak.
- **2026-11-11 — Facturering uit = iedereen gratis** (behalve geblokkeerd). Zet de admin facturering aan, dan krijgen zaken zonder proefperiode, gratis periode of abonnement een proefperiode van `plans.trial_days` (anders verlopen alle bestaande zaken meteen). Dat staat in `ShopAdminService::enableBilling()` en in de bevestigingstekst.
- **2026-11-11 — Platforminstellingen** (`platform_settings`) overschrijven `.env`/config voor de feature flags `billing`, `deposits`, `sms`, `whatsapp` en voegen gereserveerde slugs toe (`Features`, `PlatformSettings`).
- **2026-11-11 — Admin-toegang:** `users.is_admin` + verplichte 2FA (Filament app-authenticatie met herstelcodes). Een admin maak je met `php artisan barbercuts:make-admin`. Inloggen als eigenaar gaat via een tijdelijk gesigneerde link die pas bij het klikken wordt gemaakt, met een banner en een stopknop, en wordt gelogd. Een admin kan zijn eigen admin-rechten niet intrekken.
- **2026-11-11 — Statistiek:** de zware cijfers staan in `platform_daily_stats` en worden elke nacht om 02:30 berekend (`stats:compute`, via de scheduler); `stats:compute --days=90` vult geschiedenis aan. Een leeg dashboard berekent vandaag meteen. Het aantal aanmeldingen per week en de toplijsten zijn live queries.
- **2026-11-11 — Landingspagina:** voorbeeldschermen zijn opgebouwd uit HTML (fictieve namen), geen schermafbeeldingen. Zonder facturering staat er "Tijdens de lancering gratis"; met facturering de prijs en proefperiode uit het actieve plan.
- **2026-11-11 — De teksten van privacy, voorwaarden en verwerkersovereenkomst zijn concepten** van mij en niet juridisch gecontroleerd. Bedrijfsgegevens (KvK, btw, adres) komen uit `.env` (`COMPANY_*`); lege velden worden weggelaten. Btw op het abonnement (incl. of excl.) is nog niet bepaald; er worden ook nog geen facturen gemaakt (mag later, §7.7).
- **2026-11-11 — Filament-bestanden in `public/` staan niet in Git**; `composer install` publiceert ze via `filament:upgrade`.
- **2026-11-11 — Eigen MollieBillingGateway is niet tegen de echte Mollie-API geprobeerd.** De logica is getest met een nagebootste provider; de koppeling zelf (payload-velden, iDEAL-eerste-betaling, webhook) moet nog met een Mollie-testsleutel doorlopen worden.

