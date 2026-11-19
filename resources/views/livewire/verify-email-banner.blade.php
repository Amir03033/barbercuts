{{-- Bevestig je e-mailadres: verdwijnt vanzelf zodra de link in de mail is aangetikt, waar dan ook. --}}
<div>
    @if (! $verified)
        <div wire:poll.8s="check"
            x-data="{
                init() {
                    try {
                        this.channel = new BroadcastChannel('barbercuts');
                        this.channel.onmessage = (event) => event.data === 'email-verified' && $wire.check();
                    } catch (error) {}
                },
                destroy() { this.channel?.close(); },
            }"
            x-on:storage.window="$event.key === 'barbercuts:email-verified' && $wire.check()"
            x-on:visibilitychange.document="document.visibilityState === 'visible' && $wire.check()">
            <x-ui.alert type="warning" title="Bevestig je e-mailadres">
                <p>We hebben een link gestuurd naar <strong class="break-all">{{ $email }}</strong>. Niets ontvangen? Kijk ook bij ongewenste mail. Deze melding verdwijnt vanzelf zodra je op de link tikt.</p>
                <div class="-ml-3 flex flex-wrap items-center">
                    <x-ui.button variant="ghost" size="sm" wire:click="resend" loading="resend">Stuur de link opnieuw</x-ui.button>
                    <x-ui.button variant="ghost" size="sm" :href="route('verification.notice')">Adres wijzigen</x-ui.button>
                </div>
            </x-ui.alert>
        </div>
    @elseif ($justVerified)
        <div x-data="{ shown: true }" x-init="setTimeout(() => shown = false, 4000)" x-show="shown"
            x-transition:leave="transition duration-300 ease-in" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
            role="status" class="flex items-center gap-3 rounded-card border border-completed/30 bg-completed/10 p-4 text-sm font-semibold text-completed">
            <x-ui.icon name="check" size="20" />
            Je e-mailadres is bevestigd. Bedankt!
        </div>
    @endif
</div>
