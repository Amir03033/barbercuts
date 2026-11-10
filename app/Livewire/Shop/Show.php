<?php

namespace App\Livewire\Shop;

use App\Models\Service;
use App\Models\StaffMember;
use App\Support\CurrentShop;
use App\Support\WeekSchedule;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;
use Livewire\Component;

/**
 * Publieke pagina van een zaak: /{slug} (§9.2). De boekflow volgt in fase 2.
 */
class Show extends Component
{
    public function render(CurrentShop $currentShop): View
    {
        $shop = $currentShop->get();

        abort_if($shop === null, 404);

        return view('livewire.shop.show', [
            'shop' => $shop,
            'services' => Service::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get(),
            'staffMembers' => StaffMember::query()->where('is_active', true)->orderBy('sort_order')->orderBy('id')->get(),
            'week' => WeekSchedule::fromRows($shop->openingHours()->get(), 'opens_at', 'closes_at'),
            'images' => $shop->images()->get(),
            'bookUrl' => Route::has('shop.book') ? route('shop.book', $shop->slug) : null,
        ])->layout('layouts::shop', ['shop' => $shop, 'title' => $shop->name]);
    }
}
