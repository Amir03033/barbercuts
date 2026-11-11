<?php

namespace App\Livewire\App;

use App\Livewire\Concerns\InShop;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Propaganistas\LaravelPhone\PhoneNumber;
use Propaganistas\LaravelPhone\Rules\Phone;

/**
 * Eigen profiel van een zaaklid: naam, telefoonnummer en wachtwoord. Het e-mailadres staat vast (inloggen en uitnodigingen).
 */
#[Layout('layouts::app', ['active' => 'profile'])]
#[Title('Mijn profiel')]
class Profile extends Component
{
    use InShop;

    public string $name = '';

    public string $phone = '';

    public string $currentPassword = '';

    public string $newPassword = '';

    public function mount(): void
    {
        $user = $this->user();
        $this->name = $user->name;
        $this->phone = (string) $user->phone;
    }

    public function saveProfile(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'phone' => ['nullable', (new Phone)->country(['NL'])->international()],
        ], messages: ['phone' => 'Vul een geldig telefoonnummer in, bijvoorbeeld 06 12345678.'], attributes: ['name' => 'naam', 'phone' => 'telefoonnummer']);

        $this->user()->update([
            'name' => trim($this->name),
            'phone' => $this->phone === '' ? null : (new PhoneNumber($this->phone, 'NL'))->formatE164(),
        ]);

        $this->dispatch('toast', message: 'Profiel opgeslagen');
    }

    public function savePassword(): void
    {
        $this->validate([
            'currentPassword' => ['required'],
            'newPassword' => ['required', Password::min(8)],
        ], attributes: ['currentPassword' => 'huidig wachtwoord', 'newPassword' => 'nieuw wachtwoord']);

        if (! Hash::check($this->currentPassword, $this->user()->password)) {
            $this->addError('currentPassword', 'Het huidige wachtwoord klopt niet.');

            return;
        }

        $this->user()->update(['password' => $this->newPassword]);
        $this->reset('currentPassword', 'newPassword');

        $this->dispatch('toast', message: 'Wachtwoord gewijzigd');
    }

    public function render(): View
    {
        return view('livewire.app.profile', ['user' => $this->user(), 'shop' => $this->shop()]);
    }
}
