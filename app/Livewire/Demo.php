<?php

namespace App\Livewire;

use App\Models\User;
use App\Services\Demo\DemoShopService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Startpunt van de demo voor pitchers (fase 6): een verse demozaak starten of verdergaan met de lopende.
 */
#[Layout('layouts::auth')]
#[Title('Demo')]
class Demo extends Component
{
    public function render(DemoShopService $demos): View
    {
        /** @var User $pitcher */
        $pitcher = Auth::user();

        return view('livewire.demo', [
            'pitcher' => $pitcher,
            'shop' => $demos->current($pitcher),
            'hours' => (int) config('barbercuts.demo.hours'),
        ]);
    }
}
