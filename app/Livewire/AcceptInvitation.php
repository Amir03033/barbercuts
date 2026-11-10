<?php

namespace App\Livewire;

use App\Enums\ShopRole;
use App\Models\Barbershop;
use App\Models\StaffMember;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Een kapper accepteert de uitnodiging via de gesigneerde link uit de mail (§8).
 * Deze pagina heeft geen tenant: de kapper wordt expliciet op id + e-mailadres opgezocht.
 */
#[Layout('layouts::platform')]
#[Title('Uitnodiging')]
class AcceptInvitation extends Component
{
    #[Locked]
    public int $staffId;

    #[Locked]
    public string $email = '';

    public string $name = '';

    public string $password = '';

    public function mount(int $staffMember): void
    {
        $this->staffId = $staffMember;
        $this->email = strtolower((string) request()->query('email'));
        $this->name = $this->staff()->name ?? '';
    }

    public function accept(): void
    {
        $staff = $this->staff();

        abort_if($staff === null, 403);

        $user = Auth::user();

        if ($user instanceof User) {
            abort_unless(strtolower($user->email) === $this->email, 403);
        } else {
            if (User::where('email', $this->email)->exists()) {
                $this->addError('password', 'Er bestaat al een account met dit e-mailadres. Log eerst in en open de link opnieuw.');

                return;
            }

            $this->validate([
                'name' => ['required', 'string', 'max:255'],
                'password' => ['required', 'string', Password::defaults()],
            ], attributes: ['name' => 'naam', 'password' => 'wachtwoord']);

            $user = new User(['name' => $this->name, 'email' => $this->email, 'password' => $this->password]);
            // Het adres is bevestigd: de link kwam uit de mail naar dit adres.
            $user->email_verified_at = now();
            $user->save();

            Auth::login($user, remember: true);
            Session::regenerate();
        }

        DB::transaction(function () use ($staff, $user): void {
            $shop = Barbershop::findOrFail($staff->barbershop_id);

            if (! $user->isMemberOf($shop)) {
                $shop->users()->attach($user->id, ['role' => ShopRole::Barber->value]);
            }

            $staff->user_id = $user->id;
            $staff->invited_email = null;
            $staff->save();
        });

        Session::put('current_shop_id', $staff->barbershop_id);
        Session::flash('toast', 'Welkom! Je account is gekoppeld.');

        $this->redirectRoute('app.today');
    }

    /**
     * De uitgenodigde kapper, of null als de uitnodiging niet meer geldig is.
     */
    private function staff(): ?StaffMember
    {
        if ($this->email === '') {
            return null;
        }

        return StaffMember::withoutGlobalScopes()
            ->whereKey($this->staffId)
            ->whereNull('user_id')
            ->where('invited_email', $this->email)
            ->first();
    }

    public function render(): View
    {
        $staff = $this->staff();
        $user = Auth::user();

        return view('livewire.accept-invitation', [
            'staff' => $staff,
            'shop' => $staff ? Barbershop::find($staff->barbershop_id) : null,
            'wrongAccount' => $user instanceof User && strtolower($user->email) !== $this->email,
            'loggedIn' => $user instanceof User,
            'accountExists' => ! $user && User::where('email', $this->email)->exists(),
        ]);
    }
}
