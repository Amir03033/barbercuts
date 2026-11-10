<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts::platform')]
#[Title('Nieuw wachtwoord')]
class ResetPassword extends Component
{
    #[Locked]
    public string $token = '';

    #[Url]
    public string $email = '';

    public string $password = '';

    public function mount(string $token): void
    {
        $this->token = $token;
    }

    public function resetPassword(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', PasswordRule::defaults()],
        ], attributes: [
            'email' => 'e-mailadres',
            'password' => 'wachtwoord',
        ]);

        $status = Password::reset(
            ['email' => $this->email, 'password' => $this->password, 'token' => $this->token],
            function (User $user): void {
                $user->forceFill([
                    'password' => $this->password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            $this->addError('email', __($status));

            return;
        }

        session()->flash('toast', 'Je wachtwoord is gewijzigd. Log in met je nieuwe wachtwoord.');

        $this->redirectRoute('login');
    }

    public function render(): View
    {
        return view('livewire.auth.reset-password');
    }
}
