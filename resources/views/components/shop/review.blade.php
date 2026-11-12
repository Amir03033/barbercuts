{{-- Eén review: sterren, naam (voornaam + letter), datum, tekst en de reactie van de zaak. --}}
@props(['review', 'shopName' => null])

<article {{ $attributes->class('py-4') }}>
    <div class="flex items-center justify-between gap-3">
        <x-ui.stars :rating="$review->rating" />
        <time datetime="{{ $review->created_at->toDateString() }}" class="shrink-0 text-xs text-muted">{{ nl_date($review->created_at, 'D MMM YYYY') }}</time>
    </div>
    <p class="mt-1 text-sm font-semibold">{{ $review->authorLabel() }} <span class="font-normal text-muted">· bij {{ $review->staffMember?->name }}</span></p>
    @if ($review->comment)
        <p class="mt-1 text-sm whitespace-pre-line">{{ $review->comment }}</p>
    @endif
    @if ($review->reply)
        <div class="mt-3 rounded-card bg-surface p-3 text-sm">
            <p class="font-semibold">Reactie{{ $shopName ? ' van '.$shopName : '' }}</p>
            <p class="mt-1 whitespace-pre-line text-muted">{{ $review->reply }}</p>
        </div>
    @endif
</article>
