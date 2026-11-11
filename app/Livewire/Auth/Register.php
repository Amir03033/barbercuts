<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::auth')]
#[Title('Account maken')]
class Register extends Component
{
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', Password::defaults()],
        ], attributes: [
            'name' => 'naam',
            'email' => 'e-mailadres',
            'password' => 'wachtwoord',
        ]);

        $user = User::create($validated);

        event(new Registered($user));

        Auth::login($user, remember: true);
        Session::regenerate();

        // Wie vanuit de boekflow komt, gaat daarheen terug; anders naar de bevestigingspagina.
        $this->redirectIntended(default: route('verification.notice', absolute: false));
    }

    public function render(): View
    {
        return view('livewire.auth.register');
    }
}
