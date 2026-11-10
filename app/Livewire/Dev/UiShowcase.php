<?php

namespace App\Livewire\Dev;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Interne showcase van alle x-ui componenten (alleen in local): /_ui, /_ui/app en /_ui/shop.
 */
class UiShowcase extends Component
{
    public string $layout = 'platform';

    #[Url]
    public string $theme = 'light';

    #[Url]
    public string $accent = '#111827';

    public string $period = 'week';

    public string $date = '';

    public string $time = '10:30';

    public string $staff = 'any';

    public string $name = '';

    public string $phone = '';

    public string $amount = '25,00';

    public function mount(?string $layout = null): void
    {
        $this->layout = $layout ?? 'platform';
        $this->date = CarbonImmutable::now('Europe/Amsterdam')->addDay()->toDateString();
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'min:2'],
            'phone' => ['required', 'string', 'min:10'],
        ], attributes: ['name' => 'naam', 'phone' => 'telefoonnummer']);

        usleep(400_000);

        $this->dispatch('toast', message: 'Opgeslagen');
    }

    public function render(): View
    {
        $today = CarbonImmutable::now('Europe/Amsterdam');

        $days = collect(range(0, 13))->map(fn (int $offset): array => [
            'date' => $today->addDays($offset)->toDateString(),
            'disabled' => in_array($today->addDays($offset)->dayOfWeekIso, [1, 7], true),
        ])->all();

        $light = $this->theme !== 'dark';

        return view('livewire.dev.ui-showcase', ['days' => $days])
            ->layout('layouts::'.$this->layout, [
                'title' => 'UI-showcase',
                'active' => 'today',
                'themeMode' => $light ? 'light' : 'dark',
                'accentColor' => $this->accent,
                'onAccentColor' => $this->accent === '#facc15' ? '#111114' : '#ffffff',
            ]);
    }
}
