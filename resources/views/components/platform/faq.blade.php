{{--
    Veelgestelde vragen als uitklaplijst (gewone <details>, dus zonder script en toegankelijk) plus gestructureerde data
    voor zoekmachines. items: lijst van [vraag, antwoord] in platte tekst.
--}}
@props(['items' => []])

<div {{ $attributes->class('divide-y divide-border/70 overflow-hidden rounded-[1.75rem] bg-card shadow-soft ring-1 ring-border/70') }}>
    @foreach ($items as [$question, $answer])
        <details name="faq" class="group" @if ($loop->first && $attributes->has('open-first')) open @endif>
            <summary class="flex min-h-14 cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 text-left text-base font-semibold [&::-webkit-details-marker]:hidden">
                <span>{{ $question }}</span>
                <span class="flex size-8 shrink-0 items-center justify-center rounded-full bg-surface text-muted transition-transform duration-300 ease-out-soft group-open:rotate-180"><x-ui.icon name="chevron-down" size="18" /></span>
            </summary>
            <p class="px-5 pb-5 text-base leading-7 text-muted">{{ $answer }}</p>
        </details>
    @endforeach
</div>

@php
    // Let op: "@context" mag niet direct in Blade staan, daar leest Blade het als instructie. Daarom hier in PHP opgebouwd.
    $structured = json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => collect($items)->map(fn (array $item): array => [
            '@type' => 'Question',
            'name' => $item[0],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item[1]],
        ])->all(),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
@endphp

<script type="application/ld+json">{!! $structured !!}</script>
