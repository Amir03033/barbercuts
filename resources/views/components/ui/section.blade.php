{{-- Blok met een kleine kop, voor formulieren en instellingen. --}}
@props(['title' => null, 'text' => null])

<section {{ $attributes->class('space-y-3') }}>
    @if ($title)
        <div>
            <h2 class="text-base font-semibold text-text lg:text-sm lg:tracking-wide lg:uppercase lg:text-muted">{{ $title }}</h2>
            @if ($text)
                <p class="text-sm text-muted">{{ $text }}</p>
            @endif
        </div>
    @endif
    {{ $slot }}
</section>
