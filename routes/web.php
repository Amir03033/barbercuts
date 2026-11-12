<?php

use App\Enums\ShopRole;
use App\Http\Controllers\MollieWebhookController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Middleware\EnsurePitcher;
use App\Http\Middleware\ResolveShopFromApp;
use App\Http\Middleware\ResolveShopFromSlug;
use App\Livewire\AcceptInvitation;
use App\Livewire\Account;
use App\Livewire\App\Appearance;
use App\Livewire\App\Appointments\Form as AppointmentForm;
use App\Livewire\App\Calendar;
use App\Livewire\App\Customers\Index as CustomerIndex;
use App\Livewire\App\Customers\Show as CustomerShow;
use App\Livewire\App\More;
use App\Livewire\App\OpeningHours;
use App\Livewire\App\Profile;
use App\Livewire\App\Revenue;
use App\Livewire\App\Reviews;
use App\Livewire\App\Services\Form as ServiceForm;
use App\Livewire\App\Services\Index as ServiceIndex;
use App\Livewire\App\Settings;
use App\Livewire\App\Staff\Edit as StaffEdit;
use App\Livewire\App\Staff\Index as StaffIndex;
use App\Livewire\App\Subscription;
use App\Livewire\App\Today;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Auth\VerifyEmail;
use App\Livewire\Demo;
use App\Livewire\Dev\UiShowcase;
use App\Livewire\Directory;
use App\Livewire\Home;
use App\Livewire\ManageAppointment;
use App\Livewire\Onboarding;
use App\Livewire\Shop\Book as ShopBook;
use App\Livewire\Shop\Show as ShopShow;
use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\Barbershop;
use App\Models\Plan;
use App\Models\User;
use App\Services\Demo\DemoShopService;
use App\Services\ShopStatsService;
use App\Support\CurrentShop;
use App\Support\Features;
use App\Support\Period;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/*
|--------------------------------------------------------------------------
| Platform
|--------------------------------------------------------------------------
*/
Route::livewire('/', Home::class)->name('home');

// Statische pagina's (§9.4). Teksten staan in resources/views/pages.
Route::get('/prijzen', fn () => view('pages.pricing', ['plan' => Plan::current(), 'billing' => Features::billing()]))->name('pricing');
Route::view('/privacy', 'pages.privacy')->name('privacy');
Route::view('/voorwaarden', 'pages.terms')->name('terms');
Route::view('/verwerkersovereenkomst', 'pages.processor-agreement')->name('processor-agreement');
Route::view('/contact', 'pages.contact')->name('contact');

// Kappersgids (fase 7): best beoordeeld in de buurt en per plaats
Route::livewire('/kappers', Directory::class)->name('directory');
Route::livewire('/kappers/{city}', Directory::class)->where('city', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('directory.city');

// Zaak aanmelden (§8) en uitnodiging voor kappers
Route::livewire('/aanmelden', Onboarding::class)->name('onboarding');
Route::livewire('/uitnodiging/{staffMember}', AcceptInvitation::class)
    ->whereNumber('staffMember')
    ->middleware('signed')
    ->name('invitation.accept');

/*
|--------------------------------------------------------------------------
| Klanten: beheerlink (ook voor gasten) en account. Geen tenant in de URL (§6.3).
|--------------------------------------------------------------------------
*/
Route::livewire('/afspraak/{token}', ManageAppointment::class)->where('token', '[A-Za-z0-9]{40}')->name('appointment.manage');

Route::get('/afspraak/{token}/agenda.ics', function (string $token): Response {
    $appointment = Appointment::withoutGlobalScopes()->with(['barbershop', 'staffMember'])->where('manage_token', $token)->firstOrFail();
    $shop = $appointment->barbershop;
    $escape = fn (string $text): string => addcslashes($text, ',;\\');
    $stamp = fn ($date): string => $date->copy()->utc()->format('Ymd\\THis\\Z');

    $lines = [
        'BEGIN:VCALENDAR',
        'VERSION:2.0',
        'PRODID:-//BarberCuts//NL',
        'METHOD:PUBLISH',
        'BEGIN:VEVENT',
        'UID:afspraak-'.$appointment->id.'@barbercuts.nl',
        'DTSTAMP:'.$stamp(now()),
        'DTSTART:'.$stamp($appointment->starts_at),
        'DTEND:'.$stamp($appointment->ends_at),
        'SUMMARY:'.$escape($appointment->service_name.' bij '.$shop->name),
        'DESCRIPTION:'.$escape('Bij '.$appointment->staffMember->name.'. Verplaatsen of annuleren: '.$appointment->manageUrl()),
        'LOCATION:'.$escape(collect([$shop->name, $shop->address, trim($shop->postal_code.' '.$shop->city)])->filter()->implode(', ')),
        'URL:'.$appointment->manageUrl(),
        'END:VEVENT',
        'END:VCALENDAR',
    ];

    return response(implode("\r\n", $lines)."\r\n", 200, [
        'Content-Type' => 'text/calendar; charset=utf-8',
        'Content-Disposition' => 'attachment; filename="afspraak-'.$shop->slug.'.ics"',
    ]);
})->where('token', '[A-Za-z0-9]{40}')->name('appointment.ics');

Route::livewire('/account', Account::class)->middleware('auth')->name('account');

// Web push: het browserabonnement van de ingelogde gebruiker (klant of kapper), §7.6
Route::middleware(['auth', 'throttle:20,1'])->group(function (): void {
    Route::post('/push/abonneren', [PushSubscriptionController::class, 'store'])->name('push.subscribe');
    Route::post('/push/afmelden', [PushSubscriptionController::class, 'destroy'])->name('push.unsubscribe');
});

// Webhook van Mollie (CSRF staat uit voor /webhooks/*, zie bootstrap/app.php)
Route::post('/webhooks/mollie', MollieWebhookController::class)->name('webhooks.mollie');

/*
|--------------------------------------------------------------------------
| Auth
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function (): void {
    Route::livewire('/inloggen', Login::class)->name('login');
    Route::livewire('/registreren', Register::class)->name('register');
    Route::livewire('/wachtwoord-vergeten', ForgotPassword::class)->name('password.request');
    Route::livewire('/wachtwoord-herstellen/{token}', ResetPassword::class)->name('password.reset');
});

Route::middleware('auth')->group(function (): void {
    Route::livewire('/e-mail-bevestigen', VerifyEmail::class)->name('verification.notice');

    Route::get('/e-mail-bevestigen/{id}/{hash}', function (EmailVerificationRequest $request): RedirectResponse {
        $request->fulfill();

        return redirect()->to($request->user()->homeRoute())->with('toast', 'Je e-mailadres is bevestigd.');
    })->middleware(['signed', 'throttle:6,1'])->name('verification.verify');

    Route::post('/uitloggen', function (Request $request): RedirectResponse {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    })->name('logout');
});

/*
|--------------------------------------------------------------------------
| Inloggen als eigenaar (alleen platformadmin, §11). Elke start en stop komt in het activiteitenlogboek.
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function (): void {
    Route::get('/impersonate/{shop}', function (Request $request, Barbershop $shop): RedirectResponse {
        $admin = $request->user();
        abort_unless($admin instanceof User && $admin->is_admin && ! $request->session()->has('impersonator_id'), 403);

        $owner = $shop->users()->wherePivot('role', ShopRole::Owner->value)->orderBy('shop_user.id')->first();
        abort_if($owner === null, 404);

        ActivityLog::record('impersonate.start', $shop, $shop, ['as_user_id' => $owner->id]);

        Auth::guard('web')->login($owner);
        $request->session()->regenerate();
        $request->session()->put(['impersonator_id' => $admin->id, 'current_shop_id' => $shop->id]);

        return redirect()->route('app.today');
    })->whereNumber('shop')->middleware('signed')->name('impersonate.start');

    Route::post('/impersonate/stop', function (Request $request): RedirectResponse {
        $admin = User::query()->find($request->session()->get('impersonator_id'));
        abort_unless($admin instanceof User && $admin->is_admin, 403);

        ActivityLog::record('impersonate.stop', null, null, ['from_user_id' => $request->user()?->id]);

        Auth::guard('web')->login($admin);
        $request->session()->forget(['impersonator_id', 'current_shop_id']);
        $request->session()->regenerate();

        return redirect('/admin');
    })->name('impersonate.stop');
});

/*
|--------------------------------------------------------------------------
| Demo voor pitchers (fase 6). Niet gelinkt, alleen voor accounts die de admin als pitcher heeft aangemaakt.
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', EnsurePitcher::class])->group(function (): void {
    Route::livewire('/demo', Demo::class)->name('demo');

    // Verse demozaak: wist de vorige demo van deze pitcher en logt hem in als eigenaar van de nieuwe.
    Route::post('/demo/start', function (Request $request, DemoShopService $demos): RedirectResponse {
        $shop = $demos->start($request->user());
        $request->session()->put('current_shop_id', $shop->id);

        return redirect()->route('app.today')->with('toast', 'Je demo staat klaar.');
    })->middleware('throttle:6,1')->name('demo.start');
});

// Link uit de uitnodiging van een pitcher (7 dagen geldig): door naar "wachtwoord kiezen" met een vers token.
Route::get('/demo/welkom/{user}', function (User $user): RedirectResponse {
    abort_unless($user->is_pitcher, 404);

    return redirect()->route('password.reset', ['token' => Password::broker()->createToken($user), 'email' => $user->email]);
})->whereNumber('user')->middleware(['signed', 'guest', 'throttle:6,1'])->name('demo.welcome');

/*
|--------------------------------------------------------------------------
| Kapper-app (/app)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', ResolveShopFromApp::class])->prefix('app')->name('app.')->group(function (): void {
    Route::livewire('/', Today::class)->name('today');
    Route::livewire('/meer', More::class)->name('more');
    Route::livewire('/profiel', Profile::class)->name('profile');

    Route::livewire('/agenda', Calendar::class)->name('calendar');
    Route::livewire('/afspraken/nieuw', AppointmentForm::class)->name('appointments.create');
    Route::livewire('/afspraken/{appointment}/verplaatsen', AppointmentForm::class)->whereNumber('appointment')->name('appointments.reschedule');

    Route::livewire('/klanten', CustomerIndex::class)->name('customers');
    Route::livewire('/klanten/{customer}', CustomerShow::class)->whereNumber('customer')->name('customers.show');

    Route::livewire('/omzet', Revenue::class)->name('revenue');
    Route::livewire('/reviews', Reviews::class)->name('reviews');

    // CSV-export van een maand: de eigenaar alles, een kapper alleen de eigen regels (§7.5)
    Route::get('/omzet/export', function (Request $request, CurrentShop $currentShop, ShopStatsService $stats): StreamedResponse {
        $shop = $currentShop->get();
        abort_if($shop === null, 404);

        $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->query('maand')) === 1
            ? (string) $request->query('maand')
            : now($shop->timezone)->format('Y-m');

        $rows = $stats->exportRows($shop, $request->user(), Period::month($month, $shop->timezone));

        return response()->streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'w');
            // BOM, zodat Excel de trema's en het euroteken goed leest
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Datum', 'Tijd', 'Klant', 'Dienst', 'Kapper', 'Bedrag', 'Fooi', 'Betaalmethode'], ';');

            foreach ($rows as $row) {
                fputcsv($output, $row, ';');
            }

            fclose($output);
        }, "omzet-{$shop->slug}-{$month}.csv", ['Content-Type' => 'text/csv; charset=utf-8']);
    })->name('revenue.export');

    Route::livewire('/kappers', StaffIndex::class)->name('staff');
    Route::livewire('/kappers/{staffMember}', StaffEdit::class)->whereNumber('staffMember')->name('staff.edit');

    Route::livewire('/diensten', ServiceIndex::class)->name('services');
    Route::livewire('/diensten/nieuw', ServiceForm::class)->name('services.create');
    Route::livewire('/diensten/{service}', ServiceForm::class)->whereNumber('service')->name('services.edit');

    Route::livewire('/openingstijden', OpeningHours::class)->name('opening-hours');
    Route::livewire('/uiterlijk', Appearance::class)->name('appearance');
    Route::livewire('/instellingen', Settings::class)->name('settings');
    Route::livewire('/abonnement', Subscription::class)->name('subscription');

    // QR-code van de boekingslink als SVG-download (§8, stap 6)
    Route::get('/qr-code', function (CurrentShop $currentShop): Response {
        $shop = $currentShop->get();
        abort_if($shop === null, 404);

        $svg = (new Writer(new ImageRenderer(new RendererStyle(600, 2), new SvgImageBackEnd)))->writeString($shop->bookingUrl());

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'attachment; filename="qr-'.$shop->slug.'.svg"',
        ]);
    })->name('qr');
});

/*
|--------------------------------------------------------------------------
| Interne showcase van de UI-componenten (alleen lokaal)
|--------------------------------------------------------------------------
*/
if (app()->environment('local')) {
    Route::livewire('/_ui/{layout?}', UiShowcase::class)
        ->whereIn('layout', ['platform', 'app', 'shop'])
        ->name('ui');
}

/*
|--------------------------------------------------------------------------
| Publieke pagina's van een zaak: /{slug} — altijd als LAATSTE registreren (§4)
|--------------------------------------------------------------------------
*/
Route::middleware(ResolveShopFromSlug::class)->group(function (): void {
    Route::livewire('/{slug}', ShopShow::class)->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('shop.show');
    Route::livewire('/{slug}/boeken', ShopBook::class)->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('shop.book');
});
