<?php

namespace App\Livewire\App;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Tijdelijke startpagina van de kapper-app. Wordt in fase 3 het scherm "Vandaag" (§9.1).
 */
#[Layout('layouts::app', ['active' => 'today'])]
#[Title('Vandaag')]
class Today extends Component
{
    public function render(): View
    {
        return view('livewire.app.today');
    }
}
