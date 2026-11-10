<?php

use App\Http\Middleware\ResolveShopFromApp;
use App\Http\Middleware\ResolveShopFromSlug;
use App\Livewire\AcceptInvitation;
use App\Livewire\App\Appearance;
use App\Livewire\App\More;
use App\Livewire\App\OpeningHours;
use App\Livewire\App\Services\Form as ServiceForm;
use App\Livewire\App\Services\Index as ServiceIndex;
use App\Livewire\App\Settings;
use App\Livewire\App\Staff\Edit as StaffEdit;
use App\Livewire\App\Staff\Index as StaffIndex;
use App\Livewire\App\Today;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Auth\VerifyEmail;
use App\Livewire\Dev\UiShowcase;
use App\Livewire\Home;
use App\Livewire\Onboarding;
use App\Livewire\Shop\Show as ShopShow;
use App\Support\CurrentShop;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

/*
|--------------------------------------------------------------------------
| Platform
|--------------------------------------------------------------------------
*/
Route::livewire('/', Home::class)->name('home');

// Zaak aanmelden (§8) en uitnodiging voor kappers
Route::livewire('/aanmelden', Onboarding::class)->name('onboarding');
Route::livewire('/uitnodiging/{staffMember}', AcceptInvitation::class)
    ->whereNumber('staffMember')
    ->middleware('signed')
    ->name('invitation.accept');

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

        return redirect()->route('app.today')->with('toast', 'Je e-mailadres is bevestigd.');
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
| Kapper-app (/app)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', ResolveShopFromApp::class])->prefix('app')->name('app.')->group(function (): void {
    Route::livewire('/', Today::class)->name('today');
    Route::livewire('/meer', More::class)->name('more');

    Route::livewire('/kappers', StaffIndex::class)->name('staff');
    Route::livewire('/kappers/{staffMember}', StaffEdit::class)->whereNumber('staffMember')->name('staff.edit');

    Route::livewire('/diensten', ServiceIndex::class)->name('services');
    Route::livewire('/diensten/nieuw', ServiceForm::class)->name('services.create');
    Route::livewire('/diensten/{service}', ServiceForm::class)->whereNumber('service')->name('services.edit');

    Route::livewire('/openingstijden', OpeningHours::class)->name('opening-hours');
    Route::livewire('/uiterlijk', Appearance::class)->name('appearance');
    Route::livewire('/instellingen', Settings::class)->name('settings');

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
});
