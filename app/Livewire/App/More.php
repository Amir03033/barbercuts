<?php

namespace App\Livewire\App;

use App\Livewire\Concerns\InShop;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::app', ['active' => 'more'])]
#[Title('Meer')]
class More extends Component
{
    use InShop;

    public function switchShop(int $shopId): void
    {
        $shop = $this->user()->shops()->where('barbershops.id', $shopId)->firstOrFail();

        session(['current_shop_id' => $shop->id]);

        $this->redirectRoute('app.today');
    }

    public function render(): View
    {
        $shop = $this->shop();

        return view('livewire.app.more', [
            'shop' => $shop,
            'isOwner' => $this->isOwner(),
            'otherShops' => $this->user()->shops()->where('barbershops.id', '!=', $shop->id)->whereNull('onboarding_step')->get(),
            'ownStaffId' => $shop->staffMembers()->where('user_id', $this->user()->id)->value('id'),
        ]);
    }
}
