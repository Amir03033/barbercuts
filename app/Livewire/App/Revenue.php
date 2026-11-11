<?php

namespace App\Livewire\App;

use App\Livewire\Concerns\InShop;
use App\Services\ShopStatsService;
use App\Support\Period;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Omzet (§9.1 en §7.5): één groot getal met het verschil met de vorige periode, een staafgrafiek, KPI-tegels
 * en voor de eigenaar een lijst per kapper. Wat de kijker mag zien bepaalt ShopStatsService (§6.4).
 */
#[Layout('layouts::app', ['active' => 'more', 'wide' => true])]
#[Title('Omzet')]
class Revenue extends Component
{
    use InShop;

    #[Url(as: 'periode')]
    public string $type = 'week';

    #[Url(as: 'datum')]
    public string $anchor = '';

    #[Url(as: 'tot')]
    public string $customTo = '';

    /** Filter op één kapper (alleen de eigenaar; voor een kapper wordt dit genegeerd). */
    #[Url(as: 'kapper')]
    public ?int $staffId = null;

    public function mount(): void
    {
        $this->normalize();
    }

    private function normalize(): void
    {
        $timezone = $this->shop()->timezone;
        $today = CarbonImmutable::now($timezone)->toDateString();

        if (! in_array($this->type, Period::TYPES, true)) {
            $this->type = 'week';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->anchor) !== 1) {
            $this->anchor = $today;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->customTo) !== 1) {
            $this->customTo = $this->anchor;
        }
    }

    private function period(): Period
    {
        $this->normalize();

        return Period::make($this->type, $this->anchor, $this->shop()->timezone, $this->customTo);
    }

    public function updatedType(): void
    {
        $this->anchor = CarbonImmutable::now($this->shop()->timezone)->toDateString();
        $this->customTo = $this->anchor;
    }

    public function shift(int $direction): void
    {
        if ($this->type === 'custom') {
            return;
        }

        $period = $direction < 0 ? $this->period()->previous() : $this->period()->next();
        $this->anchor = $period->fromDate();
    }

    public function render(ShopStatsService $stats): View
    {
        $shop = $this->shop();
        $user = $this->user();
        $period = $this->period();
        $isOwner = $this->isOwner();
        $staffId = $isOwner ? $this->staffId : null;

        $series = $stats->series($shop, $user, $period, $staffId);

        return view('livewire.app.revenue', [
            'shop' => $shop,
            'period' => $period,
            'isOwner' => $isOwner,
            'comparison' => $stats->revenueWithComparison($shop, $user, $period, $staffId),
            'kpis' => $stats->kpis($shop, $user, $period, $staffId),
            'series' => $series,
            'max' => max(1, ...array_column($series, 'cents')),
            'perStaff' => $stats->perStaff($shop, $user, $period),
            'exportMonth' => $period->from->format('Y-m'),
            'isFuture' => $period->from->isFuture(),
        ]);
    }
}
