<?php

namespace App\Livewire\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * Gedeeld door het bevestigscherm en de melding in de app: de link opnieuw sturen (hooguit drie keer per minuut)
 * en een vergissing in het e-mailadres herstellen.
 */
trait SendsEmailVerification
{
    /**
     * Stuur de bevestigingsmail opnieuw. Geeft true terug als de mail is verstuurd.
     */
    protected function sendVerificationLink(User $user): bool
    {
        $key = 'verify-email:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, 3)) {
            $this->dispatch('toast', message: 'Even geduld — probeer het over '.max(1, RateLimiter::availableIn($key)).' seconden opnieuw.', type: 'error');

            return false;
        }

        RateLimiter::hit($key, 60);

        try {
            $user->sendEmailVerificationNotification();
        } catch (Throwable $exception) {
            report($exception);
            $this->dispatch('toast', message: 'De mail versturen is niet gelukt. Probeer het later opnieuw.', type: 'error');

            return false;
        }

        return true;
    }

    protected function verifyingUser(): User
    {
        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
