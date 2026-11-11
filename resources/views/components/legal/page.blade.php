{{-- Basis voor de juridische pagina's: titel, datum en leesbare tekst zonder losse styling per pagina. --}}
@props(['title', 'updated' => '11 november 2026'])

<x-layouts::platform :title="$title">
    <article class="mx-auto max-w-2xl px-4 py-10">
        <h1 class="text-lg font-bold md:text-xl">{{ $title }}</h1>
        <p class="mt-1 text-xs text-muted">Laatst bijgewerkt: {{ $updated }}</p>
        <div class="mt-6 space-y-3 text-sm leading-6 [&_a]:font-medium [&_a]:underline [&_h2]:mt-8 [&_h2]:text-base [&_h2]:font-semibold [&_ol]:list-decimal [&_ol]:space-y-1 [&_ol]:pl-5 [&_ul]:list-disc [&_ul]:space-y-1 [&_ul]:pl-5">
            {{ $slot }}
        </div>
    </article>
</x-layouts::platform>
