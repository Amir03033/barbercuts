<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts::auth')]
#[Title('Inloggen')]
class Login extends Component
{
    private const int MAX_ATTEMPTS = 5;

    // Per e-mailadres, ongeacht het IP-adres: stopt een aanval die van veel adressen tegelijk komt.
    private const int MAX_ATTEMPTS_PER_EMAIL = 15;

    #[Validate('required|string|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = true;

    /**
     * Komt de bezoeker van de bevestigingslink, dan staat het e-mailadres alvast ingevuld (?email=...).
     */
    public function mount(): void
    {
        $email = request()->query('email');

        if (is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
            $this->email = strtolower($email);
        }
    }

    public function login(): void
    {
        $this->validate();
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($this->throttleKey());
            RateLimiter::hit($this->emailThrottleKey(), 900);

            throw ValidationException::withMessages([
                'email' => 'Deze combinatie van e-mailadres en wachtwoord klopt niet.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        RateLimiter::clear($this->emailThrottleKey());
        Session::regenerate();

        /** @var User $user */
        $user = Auth::user();

        $this->redirectIntended(default: $user->homeRoute());
    }

    private function ensureIsNotRateLimited(): void
    {
        $perEmail = RateLimiter::tooManyAttempts($this->emailThrottleKey(), self::MAX_ATTEMPTS_PER_EMAIL);

        if (! $perEmail && ! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_ATTEMPTS)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($perEmail ? $this->emailThrottleKey() : $this->throttleKey());

        throw ValidationException::withMessages([
            'email' => "Te veel inlogpogingen. Probeer het over {$seconds} seconden opnieuw.",
        ]);
    }

    private function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email).'|'.request()->ip());
    }

    private function emailThrottleKey(): string
    {
        return 'login-email:'.Str::transliterate(Str::lower($this->email));
    }

    public function render(): View
    {
        return view('livewire.auth.login');
    }
}
