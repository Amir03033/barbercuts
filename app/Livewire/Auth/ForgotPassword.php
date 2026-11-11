<?php

namespace App\Livewire\Auth;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts::auth')]
#[Title('Wachtwoord vergeten')]
class ForgotPassword extends Component
{
    #[Validate('required|string|email')]
    public string $email = '';

    public bool $sent = false;

    /**
     * Verstuurt de herstel-link. De melding is altijd dezelfde, zodat niet te zien is of een adres bestaat.
     */
    public function sendResetLink(): void
    {
        $this->validate();

        Password::sendResetLink(['email' => $this->email]);

        $this->sent = true;
    }

    public function render(): View
    {
        return view('livewire.auth.forgot-password');
    }
}
