{{--
    Een promovideo die alleen speelt zolang hij in beeld is (spaart batterij en houdt scrollen soepel) en pas iets laadt
    als hij bijna in beeld komt.
    sound: probeer met geluid te spelen. Browsers staan dat alleen toe als de bezoeker al iets op de pagina heeft
    aangeraakt; anders speelt hij zonder geluid en zet de eerste tik ergens op de pagina het geluid aan.
--}}
@props(['video', 'framed' => true, 'sound' => false])

<figure {{ $attributes->class('mx-auto w-full') }} @style(['max-width: 22rem' => $video->orientation === 'portrait', 'max-width: 32rem' => $video->orientation === 'square'])>
    <div x-data="{
            muted: true,
            wantsSound: @js((bool) $sound),
            visible: false,
            start() {
                const player = this.$refs.player;
                this.visible = true;
                player.preload = 'auto';
                if (! this.wantsSound) {
                    player.muted = true;
                    player.play().catch(() => {});
                    return;
                }
                player.muted = false;
                player.play().then(() => { this.muted = false }).catch(() => {
                    // Geen toestemming voor geluid: zonder geluid verder, en bij de eerste tik het geluid aan.
                    player.muted = true;
                    this.muted = true;
                    player.play().catch(() => {});
                    const unmute = () => { if (this.visible) { this.unmute() } };
                    ['pointerdown', 'keydown', 'touchstart'].forEach((type) => document.addEventListener(type, unmute, { once: true, passive: true }));
                });
            },
            stop() { this.visible = false; this.$refs.player.pause() },
            unmute() { const player = this.$refs.player; player.muted = false; this.muted = false; player.play().catch(() => {}) },
            toggle() { this.muted ? this.unmute() : (this.$refs.player.muted = true, this.muted = true) },
        }"
        x-intersect:enter.margin.100px="start()"
        x-intersect:leave="stop()"
        @class(['relative overflow-hidden bg-black', 'rounded-[1.75rem] shadow-pop ring-1 ring-border/70' => $framed])
        style="aspect-ratio: {{ $video->aspectRatio() }}">
        <video x-ref="player" src="{{ $video->url() }}" @if ($video->posterUrl()) poster="{{ $video->posterUrl() }}" @endif
            muted loop playsinline preload="none" class="absolute inset-0 size-full object-cover"
            aria-label="{{ $video->title }}" x-on:click="toggle()"></video>
        <button type="button" x-on:click.stop="toggle()"
            class="absolute right-3 bottom-3 flex min-h-11 items-center gap-2 rounded-pill bg-black/65 px-4 text-sm font-semibold text-white transition-transform active:scale-95"
            x-bind:class="muted && wantsSound ? 'sound-hint' : ''">
            <svg x-show="muted" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 5 6 9H2v6h4l5 4V5Z"/><line x1="22" x2="16" y1="9" y2="15"/><line x1="16" x2="22" y1="9" y2="15"/></svg>
            <svg x-show="! muted" x-cloak width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 5 6 9H2v6h4l5 4V5Z"/><path d="M15.5 8.5a5 5 0 0 1 0 7"/><path d="M19 5a10 10 0 0 1 0 14"/></svg>
            <span x-text="muted ? 'Tik voor geluid' : 'Geluid uit'">Tik voor geluid</span>
        </button>
    </div>
    @if ($video->caption)
        <figcaption class="mt-3 text-center text-sm text-muted">{{ $video->caption }}</figcaption>
    @endif
</figure>
