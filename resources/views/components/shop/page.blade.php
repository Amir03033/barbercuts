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
    $link = fn (?string $url): string => $preview ? '#' : (string) $url;

    // Snelle acties onder de naam: alleen wat de zaak heeft ingevuld.
    $actions = array_values(array_filter([
        $shop->phone ? ['icon' => 'phone', 'label' => 'Bellen', 'href' => 'tel:'.$shop->phone, 'external' => false] : null,
        $address !== '' ? ['icon' => 'map-pin', 'label' => 'Route', 'href' => 'https://maps.google.com/?q='.rawurlencode($shop->name.', '.$address), 'external' => true] : null,
        $shop->instagramUrl() ? ['icon' => 'instagram', 'label' => 'Instagram', 'href' => $shop->instagramUrl(), 'external' => true] : null,
        $shop->website ? ['icon' => 'globe', 'label' => 'Website', 'href' => $shop->website, 'external' => true] : null,
    ]));
@endphp

<div {{ $attributes->class(['bg-bg text-text', 'overflow-hidden rounded-card border border-border' => $preview]) }}
    @if ($preview) data-theme="{{ $themeMode }}" style="--accent: {{ $accentColor }}; --on-accent: {{ Color::onColor($accentColor) }}" @endif>

    {{-- Omslag: de foto van de zaak, of een rustig vlak in de kleur van de zaak --}}
    <div class="relative h-52 overflow-hidden bg-accent sm:h-64">
        @if ($coverUrl)
            <img src="{{ $coverUrl }}" alt="" class="size-full object-cover" width="800" height="416" />
        @else
            <div class="absolute inset-0 bg-[radial-gradient(120%_90%_at_10%_0%,rgb(255_255_255/0.28),transparent_60%)]"></div>
        @endif
        <div class="absolute inset-x-0 bottom-0 h-24 bg-gradient-to-t from-black/45 to-transparent"></div>
    </div>

    <div class="relative px-4 pb-6">
        <div class="-mt-10 flex items-end justify-between gap-3">
            <div class="relative flex size-20 shrink-0 items-center justify-center overflow-hidden rounded-[1.375rem] border-4 border-bg bg-accent text-2xl font-bold text-on-accent shadow-lift">
                @if ($logoUrl)
                    <img src="{{ $logoUrl }}" alt="Logo {{ $shop->name }}" class="size-full object-cover" width="80" height="80" />
                @else
                    {{ mb_strtoupper(mb_substr($shop->name, 0, 1)) }}
                @endif
            </div>
            <span @class(['mb-1 inline-flex items-center gap-1.5 rounded-pill px-3 py-1 text-xs font-semibold', 'bg-completed/10 text-completed' => $current !== null, 'bg-surface text-muted' => $current === null])>
                <span @class(['size-1.5 rounded-full', 'bg-completed' => $current !== null, 'bg-muted' => $current === null])></span>{{ $status }}
            </span>
        </div>

        <h1 class="mt-3 text-[1.75rem] leading-9 font-bold tracking-tight">{{ $shop->name }}</h1>

        @if ($shop->hasRating())
            <a href="{{ $preview ? '#' : '#reviews' }}" class="mt-1 flex min-h-8 items-center gap-2 text-sm">
                <x-ui.stars :rating="$shop->rating_avg" />
                <span class="font-semibold tabular-nums">{{ number_format($shop->rating_avg, 1, ',', '') }}</span>
                <span class="text-muted">· {{ $shop->rating_count }} {{ $shop->rating_count === 1 ? 'review' : 'reviews' }}</span>
            </a>
        @endif

        @if ($address !== '')
            <p class="mt-1 text-sm text-muted">{{ $address }}</p>
        @endif

        @if ($shop->description)
            <p class="mt-3 text-base">{{ $shop->description }}</p>
        @endif

        @if (count($actions) > 0)
            <div class="mt-5 grid gap-2" style="grid-template-columns: repeat({{ count($actions) }}, minmax(0, 1fr))">
                @foreach ($actions as $action)
                    <a href="{{ $link($action['href']) }}" @if ($action['external'] && ! $preview) target="_blank" rel="noopener" @endif
                        class="flex min-h-16 flex-col items-center justify-center gap-1 rounded-card bg-surface text-xs font-medium transition-[background-color,transform] duration-150 ease-out-soft hover:bg-border active:scale-[0.97]">
                        <x-ui.icon :name="$action['icon']" size="20" />
                        <span>{{ $action['label'] }}</span>
                    </a>
                @endforeach
            </div>
        @endif

        {{-- Banner: mededeling of actie van de zaak --}}
        @if ($shop->hasBanner())
            <div class="mt-5 overflow-hidden rounded-card bg-accent text-on-accent">
                @if ($shop->bannerUrl())
                    <img src="{{ $shop->bannerUrl() }}" alt="" class="aspect-[16/9] w-full object-cover" width="640" height="360" loading="lazy" />
                @endif
                @if ($shop->banner_text)
                    <p class="flex items-start gap-3 px-4 py-3 text-base font-semibold">
                        <x-ui.icon name="megaphone" size="20" class="mt-0.5 shrink-0" />
                        <span>{{ $shop->banner_text }}</span>
                    </p>
                @endif
            </div>
        @endif

        @if ($loyaltyCard)
            <p class="mt-3 flex items-center gap-3 rounded-card bg-surface px-4 py-3 text-sm">
                <x-ui.icon name="gift" size="20" class="shrink-0" />
                <span><span class="font-semibold">{{ $loyaltyCard->name }}:</span> spaar {{ $loyaltyCard->stamps_required }} stempels voor {{ $loyaltyCard->rewardLabel() }}.</span>
            </p>
        @endif

        <h2 class="mt-8 mb-3 text-lg font-bold">Diensten</h2>
        <div class="divide-y divide-border overflow-hidden rounded-card border border-border/70 bg-card">
            @forelse ($services as $service)
                <x-ui.list-item :title="$service->name" :subtitle="duration_label($service->duration)" :chevron="false">
                    <x-slot:trailing><span class="font-semibold tabular-nums">{{ money($service->price_cents) }}</span></x-slot:trailing>
                </x-ui.list-item>
            @empty
                <p class="p-4 text-sm text-muted">Nog geen diensten.</p>
            @endforelse
        </div>

        @if ($staffMembers->count() > 1)
            <h2 class="mt-8 mb-3 text-lg font-bold">Het team</h2>
            <div class="no-scrollbar -mx-4 flex gap-3 overflow-x-auto px-4">
                @foreach ($staffMembers as $staff)
                    <div class="flex w-24 shrink-0 flex-col items-center gap-1.5 rounded-card border border-border/70 bg-card px-2 py-4 text-center">
                        <x-ui.avatar :name="$staff->name" :color="$staff->color" size="lg" />
                        <span class="w-full truncate text-sm font-medium">{{ $staff->name }}</span>
                        @if ($staff->rating_count > 0)
                            <span class="flex items-center gap-1 text-xs text-muted tabular-nums">
                                <x-ui.icon name="star" size="12" class="text-star" />{{ number_format($staff->rating_avg, 1, ',', '') }}
                            </span>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        @if (count($images) > 0)
            <h2 class="mt-8 mb-3 text-lg font-bold">Foto's</h2>
            {{-- De eerste foto groot, de rest in een raster; tik opent de foto. --}}
            <div class="grid grid-cols-3 gap-1.5 overflow-hidden rounded-card">
                @foreach ($images as $image)
                    <a href="{{ $link($image->url()) }}" @unless ($preview) target="_blank" rel="noopener" @endunless
                        @class(['block overflow-hidden bg-surface', 'col-span-2 row-span-2' => $loop->first])>
                        <img src="{{ $image->url() }}" alt="Foto van {{ $shop->name }}" loading="lazy" width="400" height="400"
                            class="aspect-square size-full object-cover transition-transform duration-300 ease-out-soft hover:scale-105" />
                    </a>
                @endforeach
            </div>
        @endif

        @if (count($reviews) > 0)
            <h2 id="reviews" class="mt-8 text-lg font-bold">Reviews</h2>
            <p class="text-xs text-muted">Alleen van klanten die er geweest zijn.</p>
            <div class="mt-3 divide-y divide-border overflow-hidden rounded-card border border-border/70 bg-card px-4">
                @foreach ($reviews as $review)
                    <x-shop.review :review="$review" :shop-name="$shop->name" />
                @endforeach
            </div>
        @endif

        <h2 class="mt-8 mb-3 text-lg font-bold">Openingstijden</h2>
        <dl class="divide-y divide-border overflow-hidden rounded-card border border-border/70 bg-card text-sm">
            @foreach (WeekSchedule::DAY_NAMES as $day => $dayName)
                @php($state = $week[$day] ?? ['open' => false, 'blocks' => []])
                <div @class(['flex justify-between gap-4 px-4 py-3', 'font-semibold' => $day === $now->dayOfWeekIso])>
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
