<?php

namespace App\Livewire\App;

use App\Livewire\Concerns\InShop;
use App\Models\ShopSetting;
use App\Models\WaitlistEntry;
use App\Services\WaitlistService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Wachtlijst van de zaak (fase 8): wie wacht op welke dag. De eigenaar zet de wachtlijst aan of uit en haalt iemand eraf.
 */
#[Layout('layouts::app', ['active' => 'more'])]
#[Title('Wachtlijst')]
class Waitlist extends Component
{
    use InShop;

    public function setEnabled(bool $enabled): void
    {
        $this->authorizeOwner();

        (ShopSetting::query()->first() ?? new ShopSetting)->fill(['waitlist_enabled' => $enabled])->save();

        $this->dispatch('toast', message: $enabled ? 'Wachtlijst staat aan' : 'Wachtlijst staat uit');
    }

    public function remove(int $entryId, WaitlistService $waitlist): void
    {
        $this->authorizeOwner();

        $waitlist->cancel(WaitlistEntry::query()->findOrFail($entryId));

        $this->dispatch('toast', message: 'Van de wachtlijst gehaald');
    }

    public function render(): View
    {
        $shop = $this->shop();

        return view('livewire.app.waitlist', [
            'isOwner' => $this->isOwner(),
            'enabled' => (ShopSetting::query()->first() ?? new ShopSetting)->waitlist_enabled,
            'entries' => WaitlistEntry::query()->with(['customer', 'service', 'staffMember'])
                ->whereDate('date', '>=', CarbonImmutable::now($shop->timezone)->toDateString())
                ->orderBy('date')->orderBy('id')->limit(200)->get()
                ->groupBy(fn (WaitlistEntry $entry): string => $entry->date->toDateString()),
        ]);
    }
}
