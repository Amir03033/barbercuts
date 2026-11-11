{{-- Vaste balk onderaan voor de primaire knop (mobiel). Vanaf lg staat de knop gewoon rechts in de pagina. nav = true: boven de bottom nav plaatsen. --}}
@props(['nav' => false])

<div aria-hidden="true" class="h-24 lg:hidden"></div>
<div {{ $attributes->class([
    'fixed inset-x-0 z-30 border-t border-border bg-bg/95 backdrop-blur lg:static lg:border-0 lg:bg-transparent lg:backdrop-blur-none',
    'bottom-0 pb-safe lg:pb-0' => ! $nav,
    'bottom-[calc(var(--spacing-nav)+env(safe-area-inset-bottom))]' => $nav,
]) }}>
    <div class="mx-auto max-w-2xl px-4 py-3 lg:mx-0 lg:max-w-none lg:px-4 lg:pt-0 lg:[&>*]:w-auto lg:[&>*]:min-w-56">{{ $slot }}</div>
</div>
