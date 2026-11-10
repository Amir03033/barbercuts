<?php

namespace App\Livewire\App;

use App\Enums\AppointmentStatus;
use App\Livewire\Concerns\HandlesAppointmentActions;
use App\Livewire\Concerns\InShop;
use App\Models\Appointment;
use App\Models\Barbershop;
use App\Services\ShopStatsService;
use App\Support\Period;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Vandaag (§9.1): omzet en aantal afspraken, de eerstvolgende afspraak uitgelicht en de tijdlijn van vandaag.
 * De eigenaar ziet alle kappers; een kapper ziet de eigen dag en de eigen omzet (§6.4).
 */
#[Layout('layouts::app', ['active' => 'today'])]
#[Title('Vandaag')]
class Today extends Component
{
    use HandlesAppointmentActions;
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

    public function render(ShopStatsService $stats): View
    {
        $shop = $this->shop();
        $user = $this->user();
        $isOwner = $this->isOwner();
        $scope = $stats->scopeFor($shop, $user);

        $today = CarbonImmutable::now($shop->timezone);
        $period = Period::make('day', $today->toDateString(), $shop->timezone);

        $appointments = Appointment::query()
            ->with(['customer', 'staffMember'])
            ->when($scope !== null, fn ($query) => $query->where('staff_member_id', $scope))
            ->where('starts_at', '>=', $period->from->utc())
            ->where('starts_at', '<', $period->to->utc())
            ->where('status', '!=', AppointmentStatus::Cancelled->value)
            ->orderBy('starts_at')
            ->get();

        $next = $appointments->first(
            fn (Appointment $appointment): bool => $appointment->status === AppointmentStatus::Scheduled && $appointment->ends_at->isFuture()
        );

        $selected = $this->selectedAppointment();
        $checklist = $isOwner ? $this->checklist($shop) : [];

        return view('livewire.app.today', [
            'shop' => $shop,
            'isOwner' => $isOwner,
            'revenue' => $stats->revenueWithComparison($shop, $user, $period)['revenue_cents'],
            'appointments' => $appointments,
            'next' => $next,
            'selected' => $selected,
            'canUpdateSelected' => $selected !== null && $user->can('update', $selected),
            'checklist' => collect($checklist)->every('done') ? [] : $checklist,
            'emailVerified' => $user->hasVerifiedEmail(),
        ]);
    }
}
