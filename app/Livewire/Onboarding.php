<?php

namespace App\Livewire;

use App\Enums\ShopRole;
use App\Livewire\Concerns\EditsWeekSchedule;
use App\Models\Barbershop;
use App\Models\Service;
use App\Models\StaffMember;
use App\Models\User;
use App\Rules\AvailableSlug;
use App\Services\ShopSetupService;
use App\Support\CurrentShop;
use App\Support\WeekSchedule;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Propaganistas\LaravelPhone\PhoneNumber;
use Propaganistas\LaravelPhone\Rules\Phone;

/**
 * Onboarding van een nieuwe zaak in zes stappen (docs/BOUWOPDRACHT.md §8).
 * Elke stap wordt meteen opgeslagen; wie stopt, gaat later verder bij dezelfde stap.
 */
#[Layout('layouts::platform')]
#[Title('Zaak aanmelden')]
class Onboarding extends Component
{
    use EditsWeekSchedule;

    public const int STEPS = 6;

    public int $step = 1;

    #[Locked]
    public ?int $shopId = null;

    // Stap 1 — account
    public string $name = '';

    public string $email = '';

    public string $password = '';

    // Stap 2 — zaak
    public string $shopName = '';

    public string $city = '';

    public string $phone = '';

    public string $slug = '';

    public bool $slugEdited = false;

    // Stap 3 — kappers
    public string $mode = 'solo';

    public bool $ownerCuts = true;

    /** @var list<string> */
    public array $barbers = [''];

    // Stap 5 — diensten
    /** @var list<array{selected: bool, name: string, duration: int|string, price: string}> */
    public array $services = [];

    private ?Barbershop $shop = null;

    /**
     * Draait bij elk request: zoek de zaak van deze onboarding en zet hem als huidige zaak.
     */
    public function boot(CurrentShop $currentShop): void
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return;
        }

        $query = $user->shops()->wherePivot('role', ShopRole::Owner->value);

        $this->shop = $this->shopId !== null
            ? $query->where('barbershops.id', $this->shopId)->first()
            : $query->whereNotNull('onboarding_step')->first();

        if ($this->shop !== null) {
            $currentShop->set($this->shop);
        }
    }

    public function mount(): void
    {
        $this->week = WeekSchedule::preset();
        $this->services = array_map(fn (array $service): array => [
            'selected' => true,
            'name' => $service['name'],
            'duration' => $service['duration'],
            'price' => money_input($service['price_cents']),
        ], ShopSetupService::SUGGESTED_SERVICES);

        $user = Auth::user();

        if (! $user instanceof User) {
            return;
        }

        if ($this->shop === null) {
            if ($user->shops()->exists()) {
                $this->redirectRoute('app.today');

                return;
            }

            $this->step = 2;

            return;
        }

        $this->shopId = $this->shop->id;
        $this->step = max(2, min(5, (int) $this->shop->onboarding_step));
        $this->shopName = $this->shop->name;
        $this->city = (string) $this->shop->city;
        $this->phone = (string) $this->shop->phone;
        $this->slug = $this->shop->slug;
        $this->slugEdited = true;
    }

    public function saveAccount(): void
    {
        if (Auth::check()) {
            $this->step = 2;

            return;
        }

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', Password::defaults()],
        ], attributes: ['name' => 'naam', 'email' => 'e-mailadres', 'password' => 'wachtwoord']);

        $user = User::create($validated);

        event(new Registered($user));

        Auth::login($user, remember: true);
        Session::regenerate();

        // Volledige herlaad, zodat de kop van de pagina de ingelogde gebruiker toont; mount() gaat verder bij stap 2.
        $this->redirectRoute('onboarding');
    }

    public function updatedShopName(): void
    {
        $this->suggestSlug();
    }

    public function updatedCity(): void
    {
        $this->suggestSlug();
    }

    public function updatedSlug(): void
    {
        $this->slugEdited = true;
        $this->slug = strtolower(trim($this->slug));
        $this->validateOnly('slug', ['slug' => ['required', new AvailableSlug($this->shop?->id)]]);
    }

    private function suggestSlug(): void
    {
        if ($this->slugEdited || trim($this->shopName) === '') {
            return;
        }

        $this->slug = app(ShopSetupService::class)->suggestSlug($this->shopName, $this->city ?: null);
        $this->resetValidation('slug');
    }

    public function saveShop(ShopSetupService $setup, CurrentShop $currentShop): void
    {
        $user = $this->user();

        $this->validate([
            'shopName' => ['required', 'string', 'min:2', 'max:80'],
            'city' => ['required', 'string', 'max:80'],
            'phone' => ['required', (new Phone)->country(['NL'])->international()],
            'slug' => ['required', new AvailableSlug($this->shop?->id)],
        ], messages: [
            'phone' => 'Vul een geldig telefoonnummer in, bijvoorbeeld 06 12345678.',
        ], attributes: ['shopName' => 'naam van je zaak', 'city' => 'plaats', 'phone' => 'telefoonnummer', 'slug' => 'link']);

        $phone = (new PhoneNumber($this->phone, 'NL'))->formatE164();

        if ($this->shop === null) {
            $this->shop = $setup->createShop($user, trim($this->shopName), trim($this->city), $phone, $this->slug);
            $this->shopId = $this->shop->id;
            $currentShop->set($this->shop);
        } else {
            $this->shop->update(['name' => trim($this->shopName), 'city' => trim($this->city), 'phone' => $phone, 'slug' => $this->slug]);
        }

        $this->goTo(3);
    }

    public function addBarber(): void
    {
        if (count($this->barbers) < 20) {
            $this->barbers[] = '';
        }
    }

    public function removeBarber(int $index): void
    {
        unset($this->barbers[$index]);
        $this->barbers = array_values($this->barbers) ?: [''];
    }

    public function saveBarbers(ShopSetupService $setup): void
    {
        $shop = $this->shopOrFail();
        $user = $this->user();

        $this->validate([
            'mode' => ['required', 'in:solo,team'],
            'barbers.*' => ['nullable', 'string', 'max:60'],
        ], attributes: ['barbers.*' => 'naam']);

        $names = $this->mode === 'team'
            ? array_values(array_filter(array_map(trim(...), $this->barbers), fn (string $name): bool => $name !== ''))
            : [];

        $ownerCuts = $this->mode === 'solo' || $this->ownerCuts;

        if (! $ownerCuts && $names === []) {
            $this->addError('barbers.0', 'Voeg minstens één kapper toe.');

            return;
        }

        DB::transaction(function () use ($setup, $shop, $user, $names, $ownerCuts): void {
            // Tijdens de onboarding bestaan er nog geen afspraken: de lijst mag opnieuw worden opgebouwd.
            StaffMember::query()->delete();

            if ($ownerCuts) {
                $setup->addStaffMember($shop, $user->name, $user);
            }

            foreach ($names as $name) {
                $setup->addStaffMember($shop, $name);
            }
        });

        $this->goTo(4);
    }

    public function saveHours(ShopSetupService $setup): void
    {
        $shop = $this->shopOrFail();

        if (! $this->weekIsValid()) {
            return;
        }

        if (collect($this->week)->where('open', true)->isEmpty()) {
            $this->addError('week', 'Zet minstens één dag open.');

            return;
        }

        $setup->saveOpeningHours($shop, $this->week);

        foreach (StaffMember::all() as $staff) {
            $setup->saveWorkingHours($staff, $this->week);
        }

        $this->goTo(5);
    }

    public function saveServices(ShopSetupService $setup): void
    {
        $shop = $this->shopOrFail();

        $selected = array_values(array_filter($this->services, fn (array $service): bool => (bool) $service['selected']));

        if ($selected === []) {
            $this->addError('services', 'Kies minstens één dienst. Je kunt later meer diensten toevoegen.');

            return;
        }

        $this->validate([
            'services.*.name' => ['required_if_accepted:services.*.selected', 'nullable', 'string', 'max:80'],
            'services.*.duration' => ['required_if_accepted:services.*.selected', 'nullable', 'integer', 'min:5', 'max:480'],
            'services.*.price' => ['required_if_accepted:services.*.selected', 'nullable', function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value !== null && $value !== '' && parse_money((string) $value) === null) {
                    $fail('Vul een bedrag in, bijvoorbeeld 25,00.');
                }
            }],
        ], attributes: ['services.*.name' => 'naam', 'services.*.duration' => 'duur', 'services.*.price' => 'prijs']);

        DB::transaction(function () use ($setup, $shop, $selected): void {
            Service::query()->delete();

            foreach ($selected as $service) {
                $setup->addService($shop, [
                    'name' => trim($service['name']),
                    'duration' => (int) $service['duration'],
                    'price_cents' => (int) parse_money($service['price']),
                ]);
            }

            // Klaar: de boekingspagina gaat open (§8, na stap 6).
            $shop->is_public = true;
            $shop->onboarding_step = null;
            $shop->save();
        });

        $this->step = 6;
    }

    public function back(): void
    {
        if ($this->step > 2 && $this->step < 6) {
            $this->step--;
        }
    }

    private function goTo(int $step): void
    {
        $shop = $this->shopOrFail();

        if ($shop->onboarding_step !== null && $shop->onboarding_step < $step) {
            $shop->onboarding_step = $step;
            $shop->save();
        }

        $this->step = $step;
    }

    private function user(): User
    {
        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function shopOrFail(): Barbershop
    {
        abort_if($this->shop === null, 403);

        return $this->shop;
    }

    public function render(): View
    {
        return view('livewire.onboarding', [
            'shop' => $this->shop,
            'total' => self::STEPS,
        ]);
    }
}
