<x-layouts::platform title="Account geblokkeerd">
    <div class="mx-auto max-w-sm px-4 py-12 text-center">
        <span class="mx-auto flex size-14 items-center justify-center rounded-full bg-surface text-muted"><x-ui.icon name="alert" /></span>
        <h1 class="mt-4 text-lg font-bold">Dit account is geblokkeerd</h1>
        <p class="mt-2 text-sm text-muted">
            {{ $shop->name }} is tijdelijk niet beschikbaar. Er is niets verwijderd. Neem contact met ons op via
            <a href="mailto:{{ config('barbercuts.company.email') }}" class="font-semibold text-text underline">{{ config('barbercuts.company.email') }}</a>.
        </p>
    </div>
</x-layouts::platform>
