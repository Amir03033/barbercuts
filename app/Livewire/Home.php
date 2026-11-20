<?php

namespace App\Livewire;

use App\Models\PromoVideo;
use App\Services\ShopDirectory;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::platform')]
class Home extends Component
{
    public function render(ShopDirectory $directory): View
    {
        return view('livewire.home', [
            'topShops' => $directory->topRated(6),
            'videos' => PromoVideo::query()->shown('show_on_home')->limit(3)->get(),
        ]);
    }
}
