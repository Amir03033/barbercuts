<?php

namespace App\Livewire;

use App\Livewire\Concerns\SendsEmailVerification;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Melding "Bevestig je e-mailadres" bovenaan de app en het klantaccount. Ziet vanzelf dat het adres bevestigd is
 * (ook op een ander apparaat) en laat dan even "Bevestigd" zien in plaats van te blijven staan tot je ververst.
 */
class VerifyEmailBanner extends Component
{
    use SendsEmailVerification;

    public bool $verified = false;

    public bool $justVerified = false;

    public function mount(): void
    {
        $this->verified = $this->verifyingUser()->hasVerifiedEmail();
    }

    public function check(): void
    {
        if ($this->verified) {
            return;
        }

        if ($this->verifyingUser()->fresh()?->hasVerifiedEmail() === true) {
            $this->verified = true;
            $this->justVerified = true;
        }
    }

    public function resend(): void
    {
        $user = $this->verifyingUser();

        if ($user->hasVerifiedEmail()) {
            $this->check();

            return;
        }

        if ($this->sendVerificationLink($user)) {
            $this->dispatch('toast', message: 'We hebben een nieuwe link gestuurd naar '.$user->email);
        }
    }

    public function render(): View
    {
        return view('livewire.verify-email-banner', ['email' => $this->verifyingUser()->email]);
    }
}
