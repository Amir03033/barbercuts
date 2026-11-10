{{-- Tip "Zet op beginscherm" voor kappers (§10, PWA). Verborgen als de app al geïnstalleerd is of de tip is weggeklikt. --}}
<div x-data="{
        show: false,
        ios: /iphone|ipad|ipod/i.test(navigator.userAgent),
        init() {
            const installed = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
            this.show = ! installed && localStorage.getItem('bc-install-tip') !== 'dismissed';
        },
        dismiss() { localStorage.setItem('bc-install-tip', 'dismissed'); this.show = false; },
    }" x-show="show" x-cloak>
    <x-ui.card flat class="!bg-surface">
        <p class="text-base font-semibold">Zet BarberCuts op je beginscherm</p>
        <p class="mt-1 text-sm text-muted" x-show="ios">Tik in Safari op het deel-icoon en kies “Zet op beginscherm”. Dan open je je agenda met één tik.</p>
        <p class="mt-1 text-sm text-muted" x-show="! ios">Open het menu van je browser en kies “App installeren” of “Toevoegen aan startscherm”.</p>
        <x-ui.button variant="ghost" size="sm" class="-ml-3 mt-1" x-on:click="dismiss()">Niet meer tonen</x-ui.button>
    </x-ui.card>
</div>
