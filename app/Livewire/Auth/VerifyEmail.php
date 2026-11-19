<?php

namespace App\Livewire\Auth;

use App\Livewire\Concerns\SendsEmailVerification;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * "Bevestig je e-mailadres". Gaat vanzelf verder zodra de link in de mail is aangetikt, ook op een ander apparaat:
 * het scherm kijkt elke paar seconden, bij terugkomen in het tabblad en bij een seintje van een ander tabblad.
 */
#[Layout('layouts::auth')]
#[Title('Bevestig je e-mailadres')]
class VerifyEmail extends Component
{
    use SendsEmailVerification;

    public bool $editingEmail = false;

    public string $newEmail = '';

    public function mount(): void
    {
        if ($this->verifyingUser()->hasVerifiedEmail()) {
            $this->redirect($this->verifyingUser()->homeRoute());
        }
    }

    /**
     * Is het adres inmiddels bevestigd? Dan meteen door. Aangeroepen door wire:poll en de seintjes uit de browser.
     */
    public function check(): void
    {
        $user = $this->verifyingUser()->fresh();

        if ($user instanceof User && $user->hasVerifiedEmail()) {
            Session::flash('toast', 'Je e-mailadres is bevestigd.');
            $this->redirect($user->homeRoute());
        }
    }

    public function resend(): void
    {
        $user = $this->verifyingUser();

        if ($user->hasVerifiedEmail()) {
            $this->redirect($user->homeRoute());

            return;
        }

        if ($this->sendVerificationLink($user)) {
            $this->dispatch('toast', message: 'We hebben een nieuwe link gestuurd.');
        }
    }

    public function editEmail(): void
    {
        $this->newEmail = $this->verifyingUser()->email;
        $this->editingEmail = true;
        $this->resetValidation();
    }

    public function cancelEditEmail(): void
    {
        $this->editingEmail = false;
        $this->resetValidation();
    }

    /**
     * Een typfout in het adres herstellen zonder een nieuw account te maken. Kan alleen zolang het adres niet bevestigd is.
     */
    public function updateEmail(): void
    {
        $user = $this->verifyingUser();

        if ($user->hasVerifiedEmail()) {
            $this->redirect($user->homeRoute());

            return;
        }

        $this->newEmail = strtolower(trim($this->newEmail));

        $this->validate([
            'newEmail' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')->ignore($user->id)],
        ], attributes: ['newEmail' => 'e-mailadres']);

        if ($this->newEmail !== $user->email) {
            $user->forceFill(['email' => $this->newEmail, 'email_verified_at' => null])->save();
        }

        if ($this->sendVerificationLink($user)) {
            $this->editingEmail = false;
            $this->dispatch('toast', message: 'Gewijzigd. We hebben een link gestuurd naar '.$user->email);
        }
    }

    public function render(): View
    {
        $user = $this->verifyingUser();

        return view('livewire.auth.verify-email', [
            'email' => $user->email,
            'continueUrl' => $user->homeRoute(),
            'hours' => intdiv((int) config('auth.verification.expire', 60), 60),
        ]);
    }
}
