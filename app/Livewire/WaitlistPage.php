<?php

namespace App\Livewire;

use App\Exceptions\BookingException;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Services\WaitlistService;
use App\Support\CurrentShop;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * De wachtlijstpagina van een klant: /wachtlijst/{token} (fase 8). Laat de status zien; is er een plek aangeboden,
 * dan kiest de klant hier een tijd. Net als de afspraakpagina zonder zaak in de URL: het token wijst de inschrijving aan.
 */
class WaitlistPage extends Component
{
    #[Locked]
    public string $token = '';

    public string $time = '';

    private WaitlistEntry $entry;

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->load();
    }

    public function hydrate(): void
    {
        $this->load();
    }

    private function load(): void
    {
        $this->entry = WaitlistEntry::withoutGlobalScopes()
            ->with(['barbershop', 'customer', 'service', 'staffMember'])
            ->where('token', $this->token)
            ->firstOrFail();

        app(CurrentShop::class)->set($this->entry->barbershop);
    }

    public function book(WaitlistService $waitlist): void
    {
        $shop = $this->entry->barbershop;
        $user = Auth::user();

        if (preg_match('/^\d{2}:\d{2}$/', $this->time) !== 1) {
            $this->addError('time', 'Kies een tijd.');

            return;
        }

        try {
            $appointment = $waitlist->book(
                $this->entry,
                CarbonImmutable::parse($this->entry->date->toDateString().' '.$this->time, $shop->timezone)->utc(),
                $user instanceof User ? $user : null,
            );
        } catch (BookingException $exception) {
            $this->addError('time', $exception->getMessage());
            $this->time = '';

            return;
        }

        session()->flash('booked', $appointment->manage_token);

        $this->redirect($appointment->manageUrl());
    }

    public function leave(WaitlistService $waitlist): void
    {
        $waitlist->cancel($this->entry);
        $this->load();

        $this->dispatch('toast', message: 'Je staat niet meer op de wachtlijst');
    }

    public function render(WaitlistService $waitlist): View
    {
        $entry = $this->entry;
        $shop = $entry->barbershop;
        $open = $entry->hasOpenOffer();

        return view('livewire.waitlist-page', [
            'entry' => $entry,
            'shop' => $shop,
            'day' => CarbonImmutable::parse($entry->date->toDateString(), $shop->timezone),
            'open' => $open,
            'times' => $open ? array_map(fn (CarbonImmutable $slot): string => $slot->setTimezone($shop->timezone)->format('H:i'), $waitlist->slots($entry)) : [],
        ])->layout('layouts::shop', ['shop' => $shop, 'title' => 'Wachtlijst bij '.$shop->name]);
    }
}
