{{-- Telefoonkader voor de voorbeeldschermen op de landingspagina. Puur decoratief; de inhoud is fictief. --}}
@props(['label'])

<figure {{ $attributes->class('mx-auto w-full max-w-[17rem]') }}>
    <div class="rounded-[2.25rem] border-[6px] border-primary bg-card p-0 shadow-lift" role="img" aria-label="{{ $label }}">
        <div class="h-[27rem] overflow-hidden rounded-[1.75rem] bg-bg" aria-hidden="true">
            {{ $slot }}
        </div>
    </div>
    <figcaption class="mt-3 text-center text-sm text-muted">{{ $label }}</figcaption>
</figure>
