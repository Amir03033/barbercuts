{{-- Telefoonnummer. Opslaan gebeurt als E.164 (+31612345678); normaliseren doet de server. --}}
@props(['label' => 'Telefoonnummer', 'name' => null, 'hint' => null, 'optional' => false])

<x-ui.input type="tel" inputmode="tel" autocomplete="tel" placeholder="06 12345678"
    :label="$label" :name="$name" :hint="$hint" :optional="$optional" {{ $attributes }} />
