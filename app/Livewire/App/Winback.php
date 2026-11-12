<?php

namespace App\Livewire\App;

use App\Livewire\Concerns\InShop;
use App\Models\Customer;
use App\Models\ShopSetting;
use App\Services\WinbackService;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Klanten terughalen (fase 8): aan of uit, na hoeveel weken, de tekst, en wat het heeft opgeleverd. Alleen de eigenaar.
 */
#[Layout('layouts::app', ['active' => 'more'])]
#[Title('Klanten terughalen')]
class Winback extends Component
{
    use InShop;

    public bool $enabled = false;

    public string $weeks = '6';

    public string $message = '';

    public function mount(): void
    {
        $this->authorizeOwner();

        $settings = $this->settings();
        $this->enabled = $settings->winback_weeks !== null;
        $this->weeks = (string) ($settings->winback_weeks ?? 6);
        $this->message = (string) $settings->winback_message;
    }

    private function settings(): ShopSetting
    {
        return ShopSetting::query()->first() ?? new ShopSetting;
    }

    public function save(): void
    {
        $this->authorizeOwner();

        $this->validate([
            'weeks' => ['required', Rule::in(array_map('strval', WinbackService::WEEK_OPTIONS))],
            'message' => ['nullable', 'string', 'max:400'],
        ], attributes: ['weeks' => 'aantal weken', 'message' => 'tekst']);

        $this->settings()->fill([
            'winback_weeks' => $this->enabled ? (int) $this->weeks : null,
            'winback_message' => trim($this->message) ?: null,
        ])->save();

        $this->dispatch('toast', message: 'Opgeslagen');
    }

    public function render(WinbackService $winback): View
    {
        $shop = $this->shop();

        return view('livewire.app.winback', [
            'shop' => $shop,
            'results' => $winback->results($shop),
            'eligible' => $winback->eligible($shop, (int) $this->weeks)->count(),
            'optedIn' => Customer::query()->where('marketing_opt_in', true)->count(),
            'defaultText' => WinbackService::defaultText($shop),
        ]);
    }
}
