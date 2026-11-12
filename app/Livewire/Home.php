<?php

namespace App\Livewire;

use App\Services\ShopDirectory;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts::platform')]
class Home extends Component
{
    public function render(ShopDirectory $directory): View
    {
        return view('livewire.home', ['topShops' => $directory->topRated(6)]);
    }
}
