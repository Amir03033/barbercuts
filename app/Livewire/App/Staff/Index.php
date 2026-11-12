<?php

namespace App\Livewire\App\Staff;

use App\Livewire\Concerns\InShop;
use App\Models\StaffMember;
use App\Notifications\StaffInvitation;
use App\Services\ShopSetupService;
use App\Support\WeekSchedule;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Notification;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts::app', ['active' => 'more'])]
#[Title('Kappers')]
class Index extends Component
{
    use InShop;

    public string $name = '';

    public string $email = '';

    /**
     * Kapper toevoegen. Met e-mailadres gaat er meteen een uitnodiging uit (§8).
     */
    public function add(ShopSetupService $setup): void
    {
        $this->authorizeOwner();

        $this->validate([
            'name' => ['required', 'string', 'max:60'],
            'email' => ['nullable', 'string', 'lowercase', 'email', 'max:255'],
        ], attributes: ['name' => 'naam', 'email' => 'e-mailadres']);

        $shop = $this->shop();
        $staff = $setup->addStaffMember($shop, trim($this->name));

        // Nieuwe kapper werkt standaard tijdens de openingstijden van de zaak.
        $setup->saveWorkingHours($staff, WeekSchedule::fromRows($shop->openingHours()->get(), 'opens_at', 'closes_at'));

        if ($this->email !== '') {
            $staff->invited_email = $this->email;
            $staff->invited_at = now();
            $staff->save();

            // In een demozaak (fase 6) gaat de uitnodiging niet echt de deur uit.
            if (! $shop->is_demo) {
                Notification::route('mail', $this->email)->notify(new StaffInvitation($staff, $shop, $this->user()->name));
            }
        }

        $this->reset('name', 'email');
        $this->dispatch('close-sheet');
        $this->dispatch('toast', message: $staff->invited_email ? 'Kapper toegevoegd en uitgenodigd' : 'Kapper toegevoegd');
    }

    public function render(): View
    {
        return view('livewire.app.staff.index', [
            'staffMembers' => StaffMember::query()->orderBy('sort_order')->orderBy('id')->get(),
            'isOwner' => $this->isOwner(),
            'userId' => $this->user()->id,
        ]);
    }
}
