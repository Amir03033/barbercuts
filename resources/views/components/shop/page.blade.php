{{--
    De publieke pagina van een zaak (§9.2). Wordt gebruikt op /{slug} en als live voorbeeld bij "Uiterlijk".
    preview = true: zet thema en accentkleur op dit blok zelf (anders doet layouts.shop dat).
--}}
@props([
    'shop',
    'services',
    'staffMembers',
    'week',
    'images' => [],
    'reviews' => [],
    'loyaltyCard' => null,
    'themeMode' => null,
    'accentColor' => null,
    'logoUrl' => null,
    'coverUrl' => null,
    'bookUrl' => null,
    'preview' => false,
])

@php
    use App\Support\Color;
    use App\Support\WeekSchedule;

    $themeMode ??= $shop->theme_mode->value;
    $accentColor = Color::isHex($accentColor) ? $accentColor : $shop->accent_color;
    $logoUrl ??= $shop->logoUrl();
    $coverUrl ??= $shop->coverUrl();

    $now = now($shop->timezone);
    $today = $week[$now->dayOfWeekIso] ?? ['open' => false, 'blocks' => []];
    $current = $today['open'] ? collect($today['blocks'])->first(fn ($block) => $block['from'] <= $now->format('H:i') && $now->format('H:i') < $block['to']) : null;
    $next = $today['open'] ? collect($today['blocks'])->first(fn ($block) => $block['from'] > $now->format('H:i')) : null;
    $status = match (true) {
        $current !== null => 'Nu open tot '.$current['to'],
        $next !== null => 'Vandaag open vanaf '.$next['from'],
        default => 'Nu gesloten',
    };
    $address = collect([$shop->address, trim($shop->postal_code.' '.$shop->city)])->filter()->implode(', ');
@endphp

<div {{ $attributes->class(['bg-bg text-text', 'overflow-hidden rounded-card border border-border' => $preview]) }}
    @if ($preview) data-theme="{{ $themeMode }}" style="--accent: {{ $accentColor }}; --on-accent: {{ Color::onColor($accentColor) }}" @endif>

    <div class="relative h-36 bg-surface">
        @if ($coverUrl)
            <img src="{{ $coverUrl }}" alt="" class="size-full object-cover" width="800" height="288" />
        @endif
    </div>

    <div class="px-4 pb-6">
        <div class="-mt-8 flex items-end gap-3">
            <div class="relative flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-card border-2 border-bg bg-accent text-lg font-bold text-on-accent">
                @if ($logoUrl)
                    <img src="{{ $logoUrl }}" alt="Logo {{ $shop->name }}" class="size-full object-cover" width="64" height="64" />
                @else
                    {{ mb_strtoupper(mb_substr($shop->name, 0, 1)) }}
                @endif
            </div>
        </div>

        <h1 class="mt-3 text-lg font-bold">{{ $shop->name }}</h1>
        @if ($shop->hasRating())
            <a href="#reviews" class="mt-1 flex min-h-8 items-center gap-2 text-sm">
                <x-ui.stars :rating="$shop->rating_avg" />
                <span class="font-semibold tabular-nums">{{ number_format($shop->rating_avg, 1, ',', '') }}</span>
                <span class="text-muted">· {{ $shop->rating_count }} {{ $shop->rating_count === 1 ? 'review' : 'reviews' }}</span>
            </a>
        @endif
        <p class="text-sm text-muted">{{ $status }}</p>

        @if ($shop->description)
            <p class="mt-3 text-sm">{{ $shop->description }}</p>
        @endif

        <div class="mt-4 flex flex-wrap gap-2">
            @if ($address !== '')
                <x-ui.chip :href="$preview ? '#' : 'https://maps.google.com/?q='.rawurlencode($shop->name.', '.$address)" target="_blank" rel="noopener">
                    <x-ui.icon name="map-pin" size="16" /> {{ $address }}
                </x-ui.chip>
            @endif
            @if ($shop->phone)
                <x-ui.chip :href="$preview ? '#' : 'tel:'.$shop->phone"><x-ui.icon name="phone" size="16" /> Bellen</x-ui.chip>
            @endif
        </div>

        @if ($loyaltyCard)
            <p class="mt-4 flex items-center gap-2 rounded-card bg-surface px-3 py-2 text-sm">
                <x-ui.icon name="star" size="16" class="shrink-0 text-gold" />
                <span><span class="font-semibold">{{ $loyaltyCard->name }}:</span> spaar {{ $loyaltyCard->stamps_required }} stempels voor {{ $loyaltyCard->rewardLabel() }}.</span>
            </p>
        @endif

        @if (count($images) > 0)
            <div class="no-scrollbar -mx-4 mt-5 flex snap-x gap-2 overflow-x-auto px-4">
                @foreach ($images as $image)
                    <img src="{{ $image->url() }}" alt="Foto van {{ $shop->name }}" loading="lazy" width="224" height="160"
                        class="h-40 w-56 shrink-0 snap-start rounded-card object-cover" />
                @endforeach
            </div>
        @endif

        <h2 class="mt-6 mb-2 text-base font-semibold">Diensten</h2>
        <div class="divide-y divide-border rounded-card border border-border bg-card shadow-soft">
            @forelse ($services as $service)
                <x-ui.list-item :title="$service->name" :subtitle="duration_label($service->duration)" :chevron="false">
                    <x-slot:trailing><span class="font-semibold tabular-nums">{{ money($service->price_cents) }}</span></x-slot:trailing>
                </x-ui.list-item>
            @empty
                <p class="p-4 text-sm text-muted">Nog geen diensten.</p>
            @endforelse
        </div>

        @if ($staffMembers->count() > 1)
            <h2 class="mt-6 mb-2 text-base font-semibold">Kappers</h2>
            <div class="no-scrollbar -mx-4 flex gap-4 overflow-x-auto px-4">
                @foreach ($staffMembers as $staff)
                    <div class="flex w-16 shrink-0 flex-col items-center gap-1 text-center">
                        <x-ui.avatar :name="$staff->name" :color="$staff->color" size="lg" />
                        <span class="w-full truncate text-xs">{{ $staff->name }}</span>
                        @if ($staff->rating_count > 0)
                            <span class="text-xs text-muted tabular-nums">★ {{ number_format($staff->rating_avg, 1, ',', '') }}</span>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        @if (count($reviews) > 0)
            <h2 id="reviews" class="mt-6 text-base font-semibold">Reviews</h2>
            <p class="text-xs text-muted">Alleen van klanten die er geweest zijn.</p>
            <div class="divide-y divide-border">
                @foreach ($reviews as $review)
                    <x-shop.review :review="$review" :shop-name="$shop->name" />
                @endforeach
            </div>
        @endif

        <h2 class="mt-6 mb-2 text-base font-semibold">Openingstijden</h2>
        <dl class="space-y-1 text-sm">
            @foreach (WeekSchedule::DAY_NAMES as $day => $dayName)
                @php($state = $week[$day] ?? ['open' => false, 'blocks' => []])
                <div @class(['flex justify-between gap-4', 'font-semibold' => $day === $now->dayOfWeekIso])>
                    <dt>{{ $dayName }}</dt>
                    <dd class="text-right tabular-nums {{ $state['open'] ? '' : 'text-muted' }}">
                        {{ $state['open'] ? collect($state['blocks'])->map(fn ($block) => $block['from'].' – '.$block['to'])->implode(', ') : 'Gesloten' }}
                    </dd>
                </div>
            @endforeach
        </dl>

        @if ($preview)
            <div class="mt-6">
                <x-ui.button full>Afspraak maken</x-ui.button>
            </div>
        @endif
    </div>
</div>

@if (! $preview && $bookUrl)
    <x-ui.sticky-footer>
        <x-ui.button :href="$bookUrl" full>Afspraak maken</x-ui.button>
    </x-ui.sticky-footer>
@endif
