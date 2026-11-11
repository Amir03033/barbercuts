{{-- Tabel voor desktop. Op mobiel gebruik je lijstrijen; zet deze tabel dan in `hidden md:block`. Kop in slot `head`, rijen in de slot. --}}
@props([])

<div {{ $attributes->class('overflow-hidden rounded-card border border-border bg-card shadow-soft') }}>
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm [&_td]:px-4 [&_td]:py-3 [&_th]:px-4 [&_th]:py-3 [&_th]:text-xs [&_th]:font-medium [&_th]:tracking-wide [&_th]:text-muted [&_th]:uppercase [&_tbody_tr]:border-t [&_tbody_tr]:border-border [&_tbody_tr]:transition-colors [&_tbody_tr]:duration-150 [&_tbody_tr:hover]:bg-surface/60">
            @isset($head)
                <thead class="bg-surface/50"><tr>{{ $head }}</tr></thead>
            @endisset
            <tbody>{{ $slot }}</tbody>
        </table>
    </div>
</div>
