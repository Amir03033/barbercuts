<?php

namespace App\Livewire\App;

use App\Livewire\Concerns\InShop;
use App\Models\Customer;
use App\Models\LoyaltyCard;
use App\Models\Service;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Spaarkaart (fase 9): de zaak richt haar eigen kaart in. Alleen de eigenaar.
 */
#[Layout('layouts::app', ['active' => 'more'])]
#[Title('Spaarkaart')]
class Loyalty extends Component
{
    use InShop;

    public bool $active = false;

    public string $name = 'Spaarkaart';

    public string $stamps = '10';

    public string $rewardType = 'free';

    public string $rewardAmount = '';

    /** @var list<string> Diensten die een stempel opleveren; leeg = alle diensten. */
    public array $serviceIds = [];

    public function mount(): void
    {
        $this->authorizeOwner();

        $card = $this->card();
        $this->active = $card->is_active;
        $this->name = $card->name;
        $this->stamps = (string) $card->stamps_required;
        $this->rewardType = $card->reward_type;
        $this->rewardAmount = $card->reward_cents > 0 ? money_input($card->reward_cents) : '';
        $this->serviceIds = $card->exists ? $card->services()->pluck('services.id')->map(fn ($id): string => (string) $id)->all() : [];
    }

    private function card(): LoyaltyCard
    {
        return LoyaltyCard::query()->first() ?? new LoyaltyCard;
    }

    public function save(): void
    {
        $this->authorizeOwner();

        $this->validate([
            'name' => ['required', 'string', 'max:60'],
            'stamps' => ['required', Rule::in(array_map('strval', LoyaltyCard::STAMP_OPTIONS))],
            'rewardType' => ['required', Rule::in(['free', 'discount'])],
            'serviceIds' => ['array'],
        ], attributes: ['name' => 'naam', 'stamps' => 'aantal stempels']);

        $cents = $this->rewardType === 'discount' ? parse_money($this->rewardAmount) : 0;

        if ($this->rewardType === 'discount' && ($cents === null || $cents <= 0)) {
            $this->addError('rewardAmount', 'Vul het bedrag van de korting in, bijvoorbeeld 5,00.');

            return;
        }

        $card = $this->card();
        $card->fill([
            'name' => trim($this->name),
            'stamps_required' => (int) $this->stamps,
            'reward_type' => $this->rewardType,
            'reward_cents' => (int) $cents,
            'is_active' => $this->active,
        ])->save();

        // Alleen diensten van deze zaak; alles of niets aangevinkt = alle diensten.
        $all = Service::query()->pluck('id');
        $chosen = $all->intersect(array_map('intval', $this->serviceIds));
        $card->services()->sync($chosen->count() === $all->count() ? [] : $chosen->all());

        $this->dispatch('toast', message: 'Spaarkaart opgeslagen');
    }

    public function render(): View
    {
        return view('livewire.app.loyalty', [
            'services' => Service::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get(),
            'saving' => Customer::query()->where('loyalty_stamps', '>', 0)->count(),
            'full' => Customer::query()->where('loyalty_stamps', '>=', (int) $this->stamps)->count(),
        ]);
    }
}
