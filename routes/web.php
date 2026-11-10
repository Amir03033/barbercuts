<?php

use App\Livewire\App\Today;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Auth\VerifyEmail;
use App\Livewire\Dev\UiShowcase;
use App\Livewire\Home;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Platform
|--------------------------------------------------------------------------
*/
Route::livewire('/', Home::class)->name('home');

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
Route::middleware(['auth', 'verified'])->prefix('app')->name('app.')->group(function (): void {
    Route::livewire('/', Today::class)->name('today');
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
