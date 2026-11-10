<?php

namespace App\Livewire\App\Services;

use App\Livewire\Concerns\InShop;
use App\Models\Service;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::app', ['active' => 'more'])]
#[Title('Diensten')]
class Index extends Component
{
    use InShop;

    public function mount(): void
    {
        $this->authorizeOwner();
    }

    public function render(): View
    {
        return view('livewire.app.services.index', [
            'services' => Service::query()->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }
}
