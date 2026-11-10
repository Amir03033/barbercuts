<div class="mx-auto max-w-sm px-4 py-8">
    <x-ui.empty-state icon="mail" title="Bevestig je e-mailadres" class="!px-0"
        text="We hebben een link gestuurd naar {{ $email }}. Tik op de link in de mail om verder te gaan.">
        <x-ui.button wire:click="resend" loading="resend" variant="secondary">Stuur de link opnieuw</x-ui.button>
    </x-ui.empty-state>
</div>
