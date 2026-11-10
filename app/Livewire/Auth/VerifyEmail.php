<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::platform')]
#[Title('Bevestig je e-mailadres')]
class VerifyEmail extends Component
{
    public function mount(): void
    {
        if ($this->user()->hasVerifiedEmail()) {
            $this->redirectRoute('app.today');
        }
    }

    public function resend(): void
    {
        $user = $this->user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectRoute('app.today');

            return;
        }

        $key = 'verify-email:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, 3)) {
            $this->dispatch('toast', message: 'Even geduld — probeer het over een minuut opnieuw.', type: 'error');

            return;
        }

        RateLimiter::hit($key, 60);
        $user->sendEmailVerificationNotification();

        $this->dispatch('toast', message: 'We hebben een nieuwe link gestuurd.');
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }

    public function render(): View
    {
        return view('livewire.auth.verify-email', ['email' => $this->user()->email]);
    }
}
