<x-layouts::platform title="Contact">
    <div class="mx-auto max-w-2xl px-4 py-10">
        <h1 class="text-lg font-bold md:text-xl">Contact</h1>
        <p class="mt-1 text-sm text-muted">Een vraag, een idee of hulp nodig? We lezen alles zelf.</p>

        <x-ui.card :padding="false" class="mt-6 divide-y divide-border overflow-hidden">
            <x-ui.list-item :href="'mailto:'.config('barbercuts.company.email')" title="E-mail" :subtitle="config('barbercuts.company.email')" :chevron="false">
                <x-slot:leading><x-ui.icon name="mail" class="text-muted" /></x-slot:leading>
            </x-ui.list-item>
            @if (config('barbercuts.company.phone'))
                <x-ui.list-item :href="'tel:'.config('barbercuts.company.phone')" title="Telefoon" :subtitle="config('barbercuts.company.phone')" :chevron="false">
                    <x-slot:leading><x-ui.icon name="phone" class="text-muted" /></x-slot:leading>
                </x-ui.list-item>
            @endif
        </x-ui.card>

        <dl class="mt-6 space-y-1 text-sm text-muted">
            <div class="font-medium text-text">{{ config('barbercuts.company.name') }}</div>
            @if (config('barbercuts.company.address'))<dd>{{ config('barbercuts.company.address') }}</dd>@endif
            @if (config('barbercuts.company.kvk'))<dd>KvK {{ config('barbercuts.company.kvk') }}</dd>@endif
            @if (config('barbercuts.company.vat'))<dd>Btw-nummer {{ config('barbercuts.company.vat') }}</dd>@endif
        </dl>
    </div>
</x-layouts::platform>
