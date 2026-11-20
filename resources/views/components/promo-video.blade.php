{{--
    Een promovideo: speelt vanzelf af zonder geluid, in een lus, maar alleen zolang hij in beeld is (spaart batterij en
    houdt scrollen soepel). Met een knop voor geluid. Laadt pas iets als hij bijna in beeld komt.
--}}
@props(['video', 'framed' => true])

<figure {{ $attributes->class('mx-auto w-full') }} @style(['max-width: 22rem' => $video->orientation === 'portrait', 'max-width: 32rem' => $video->orientation === 'square'])>
    <div x-data="{ muted: true, playing: false }"
        x-intersect:enter.margin.200px="$refs.player.preload = 'auto'; $refs.player.play().then(() => playing = true).catch(() => {})"
        x-intersect:leave="$refs.player.pause(); playing = false"
        @class(['relative overflow-hidden bg-black', 'rounded-[1.75rem] shadow-pop ring-1 ring-border/70' => $framed])
        style="aspect-ratio: {{ $video->aspectRatio() }}">
        <video x-ref="player" src="{{ $video->url() }}" @if ($video->posterUrl()) poster="{{ $video->posterUrl() }}" @endif
            muted loop playsinline preload="none" class="absolute inset-0 size-full object-cover"
            aria-label="{{ $video->title }}"></video>
        <button type="button" class="absolute right-3 bottom-3 flex min-h-11 items-center gap-1.5 rounded-pill bg-black/60 px-3.5 text-xs font-semibold text-white"
            x-on:click="muted = ! muted; $refs.player.muted = muted; if (! muted) { $refs.player.play() }"
            x-text="muted ? 'Geluid aan' : 'Geluid uit'">Geluid aan</button>
    </div>
    @if ($video->caption)
        <figcaption class="mt-3 text-center text-sm text-muted">{{ $video->caption }}</figcaption>
    @endif
</figure>
