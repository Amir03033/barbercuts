{{-- Vaste balk onderaan voor de primaire knop. nav = true: boven de bottom nav plaatsen. --}}
@props(['nav' => false])

<div aria-hidden="true" class="h-24"></div>
<div {{ $attributes->class(['fixed inset-x-0 z-30 border-t border-border bg-bg/95 backdrop-blur', 'bottom-0 pb-safe' => ! $nav]) }}
    @if ($nav) style="bottom: calc(var(--spacing-nav) + env(safe-area-inset-bottom))" @endif>
    <div class="mx-auto max-w-2xl px-4 py-3">{{ $slot }}</div>
</div>
