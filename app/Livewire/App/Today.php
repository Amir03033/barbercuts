<?php

namespace App\Livewire\App;

use App\Livewire\Concerns\InShop;
use App\Models\Barbershop;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Startpagina van de kapper-app. De afspraken van vandaag komen in fase 3 (§9.1);
 * nu: de boekingslink en de checklist na de onboarding (§8).
 */
#[Layout('layouts::app', ['active' => 'today'])]
#[Title('Vandaag')]
class Today extends Component
{
    use InShop;

    public function markLinkShared(): void
    {
        $this->authorizeOwner();

        $shop = $this->shop();

        if ($shop->link_shared_at === null) {
            $shop->link_shared_at = now();
            $shop->save();
        }
    }

    /**
     * @return list<array{label: string, done: bool, href: string|null}>
     */
    private function checklist(Barbershop $shop): array
    {
        return [
            ['label' => 'Logo uploaden', 'done' => $shop->logo_path !== null, 'href' => route('app.appearance')],
            ['label' => "Foto's toevoegen", 'done' => $shop->images()->exists(), 'href' => route('app.appearance')],
            [
                'label' => 'Thema kiezen',
                'done' => $shop->accent_color !== Barbershop::DEFAULT_ACCENT || $shop->theme_mode->value === 'dark',
                'href' => route('app.appearance'),
            ],
            ['label' => 'Link in je Instagram-bio zetten', 'done' => $shop->link_shared_at !== null, 'href' => null],
            ['label' => 'Eerste afspraak', 'done' => $shop->appointments()->exists(), 'href' => null],
        ];
    }

    public function render(): View
    {
        $shop = $this->shop();
        $isOwner = $this->isOwner();
        $checklist = $isOwner ? $this->checklist($shop) : [];

        return view('livewire.app.today', [
            'shop' => $shop,
            'isOwner' => $isOwner,
            'checklist' => collect($checklist)->every('done') ? [] : $checklist,
            'emailVerified' => $this->user()->hasVerifiedEmail(),
        ]);
    }
}
