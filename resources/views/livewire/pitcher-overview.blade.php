<div class="mx-auto max-w-3xl px-4 py-8 md:py-12">
    <p class="rise-in text-sm font-semibold text-link">Pitcher</p>
    <h1 class="rise-in mt-1 text-[1.75rem] leading-9 font-bold tracking-tight md:text-4xl" style="--rise-delay: 60ms">Mijn aanmeldingen</h1>
    <p class="rise-in mt-2 text-base text-muted" style="--rise-delay: 120ms">Hoi {{ explode(' ', $pitcher->name)[0] }}. Hier zie je welke zaken jij hebt aangebracht en wie je moet bellen.</p>

    <x-pitcher.tabs active="overview" />

    {{-- De eigen aanmeldlink --}}
    <section class="mt-8 grid gap-5 rounded-[1.75rem] bg-card p-5 shadow-soft ring-1 ring-border/70 md:grid-cols-[1fr_auto] md:items-center md:p-6" data-reveal>
        <div class="min-w-0">
            <h2 class="text-lg font-bold tracking-tight">Jouw aanmeldlink</h2>
            <p class="mt-1 text-sm text-muted">Een zaak die via deze link of QR-code aanmeldt, wordt aan jou gekoppeld. Laat de kapper de QR scannen met zijn eigen telefoon.</p>
            <x-ui.copy-link :url="$link" :label="str_replace('https://', '', $link)" class="mt-4" />
            <p class="mt-3 text-xs text-muted">Aanmelden zonder deze link? Mail hallo@barbercuts.nl met de naam van de zaak, dan koppelen we hem achteraf aan jou.</p>
        </div>
        <div class="mx-auto rounded-2xl bg-white p-3 ring-1 ring-border/70" aria-label="QR-code van je aanmeldlink" role="img">
            <div class="size-40 [&>svg]:size-full">{!! $qr !!}</div>
        </div>
    </section>

    {{-- De eigen demolink --}}
    <x-pitcher.demo-link :url="$demoLink" :starts="$pitcher->demo_link_starts" class="mt-4" />

    {{-- Persoonlijke demo: een demo met de naam van de zaak van één kapper --}}
    <section class="mt-4 rounded-[1.75rem] bg-card p-5 shadow-soft ring-1 ring-border/70 md:p-6" data-reveal>
        <h2 class="text-lg font-bold tracking-tight">Demo klaarzetten voor een zaak</h2>
        <p class="mt-1 text-sm text-muted">Vul de naam van de zaak in. De kapper opent dan een demo met zijn eigen naam erin: "Zo ziet Fadez eruit in BarberCuts".</p>

        <form wire:submit="createInvite" class="mt-4 space-y-3" novalidate>
            <x-ui.input label="Naam van de zaak" wire:model="inviteShopName" placeholder="Bijv. Fadez Barbershop" autocomplete="off" maxlength="60" />
            <x-ui.input label="Plaats" wire:model="inviteCity" placeholder="Bijv. Zwolle" autocomplete="off" optional maxlength="60" />
            <div>
                <p class="text-sm font-medium text-text">Kleur <span class="font-normal text-muted">· optioneel, bijvoorbeeld de kleur van hun logo</span></p>
                <x-ui.color-swatches :colors="$colors" wire:model="inviteColor" :value="$inviteColor" name="inviteColor" label="Kleur van de demo" class="mt-2" />
            </div>
            <x-ui.button type="submit" loading="createInvite" full>Demo klaarzetten</x-ui.button>
        </form>

        @if ($invites->isNotEmpty())
            <ul class="mt-5 space-y-3">
                @foreach ($invites as $invite)
                    @php
                        $inviteUrl = $invite->url();
                        $inviteMessage = "Hoi! Ik heb alvast een demo van BarberCuts klaargezet voor {$invite->shop_name}. Zo zie je in een minuut hoe jouw agenda en omzet eruitzien, zonder account: {$inviteUrl}";
                    @endphp
                    <li wire:key="invite-{{ $invite->id }}" @class(['rounded-[1.25rem] p-4 ring-1', 'bg-gold-soft/60 ring-link/30' => $newInviteId === $invite->id, 'bg-surface ring-border/50' => $newInviteId !== $invite->id])>
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="size-3 shrink-0 rounded-full ring-2 ring-white" style="background-color: {{ $invite->accent_color ?? '#1f3a5f' }}"></span>
                                <div class="min-w-0">
                                    <p class="truncate text-base font-semibold">{{ $invite->shop_name }}</p>
                                    <p class="text-xs text-muted">
                                        {{ $invite->city ? $invite->city.' · ' : '' }}
                                        @if ($invite->starts > 0)
                                            {{ $invite->starts }}× geopend, laatst {{ $invite->last_started_at?->diffForHumans() }}
                                        @else
                                            Nog niet geopend
                                        @endif
                                        @if ($invite->signed_up_count > 0)
                                            · <span class="font-semibold text-completed">aangemeld</span>
                                        @endif
                                    </p>
                                </div>
                            </div>
                            <x-ui.icon-button icon="trash" label="Verwijderen" wire:click="deleteInvite({{ $invite->id }})" wire:confirm="Deze demolink verwijderen? De link werkt daarna als gewone demo." class="-mt-2 -mr-2" />
                        </div>
                        <x-ui.copy-link :url="$inviteUrl" :label="preg_replace('#^https?://#', '', $inviteUrl)" class="mt-3 bg-card" />
                        <x-ui.button variant="secondary" size="sm" icon="message-circle" full class="mt-2" target="_blank" rel="noopener"
                            :href="'https://wa.me/?text='.rawurlencode($inviteMessage)">Stuur via WhatsApp</x-ui.button>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- Video's om te delen (geüpload in /admin) --}}
    @if ($videos->isNotEmpty())
        <section class="mt-4 rounded-[1.75rem] bg-card p-5 shadow-soft ring-1 ring-border/70 md:p-6" data-reveal>
            <h2 class="text-lg font-bold tracking-tight">Video's om te delen</h2>
            <p class="mt-1 text-sm text-muted">Stuur een video mee in WhatsApp of zet hem in je Instagram-story. Een video speelt daar meteen af en valt meer op dan een link. Je demolink gaat mee in de tekst.</p>
            <div class="mt-4 grid gap-6 sm:grid-cols-2">
                @foreach ($videos as $video)
                    @php($shareText = "Zo werkt BarberCuts in 30 seconden. Probeer het zelf, zonder account: {$demoLink}")
                    <div wire:key="video-{{ $video->id }}"
                        x-data="{
                            busy: false,
                            async share() {
                                this.busy = true;
                                try {
                                    const blob = await (await fetch(@js($video->url()))).blob();
                                    const file = new File([blob], @js($video->downloadName()), { type: blob.type || 'video/mp4' });
                                    if (navigator.canShare && navigator.canShare({ files: [file] })) {
                                        await navigator.share({ files: [file], text: @js($shareText) });
                                    } else {
                                        this.$refs.download.click();
                                    }
                                } catch (error) {
                                } finally {
                                    this.busy = false;
                                }
                            },
                        }">
                        <x-promo-video :video="$video" />
                        <p class="mt-3 text-sm font-semibold">{{ $video->title }}</p>
                        <div class="mt-2 grid grid-cols-2 gap-2">
                            <x-ui.button variant="secondary" size="sm" icon="share" x-on:click="share()" x-bind:disabled="busy">
                                <span x-text="busy ? 'Even geduld…' : 'Delen'">Delen</span>
                            </x-ui.button>
                            <x-ui.button variant="secondary" size="sm" icon="download" :href="$video->url()" x-ref="download" download="{{ $video->downloadName() }}">Downloaden</x-ui.button>
                        </div>
                        <x-ui.copy-link :url="$shareText" label="Tekst met je demolink" class="mt-2" />
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Wie heeft er een demo bekeken --}}
    @if ($visits->isNotEmpty())
        <section class="mt-4 rounded-[1.75rem] bg-card p-5 shadow-soft ring-1 ring-border/70 md:p-6" data-reveal>
            <h2 class="text-lg font-bold tracking-tight">Recente demo's</h2>
            <p class="mt-1 text-sm text-muted">Gestart via jouw links. Je krijgt meteen een melding als iemand begint.</p>
            <ul class="mt-3 divide-y divide-border">
                @foreach ($visits as $visit)
                    <li class="flex items-center justify-between gap-3 py-3" wire:key="visit-{{ $visit->id }}">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold">{{ $visit->invite?->shop_name ?? 'Via je demolink' }}</p>
                            <p class="text-xs text-muted">{{ ucfirst($visit->created_at->diffForHumans()) }}</p>
                        </div>
                        @if ($visit->signedUpShop)
                            <x-ui.badge status="completed">Aangemeld</x-ui.badge>
                        @else
                            <span class="shrink-0 text-xs text-muted">Nog niet aangemeld</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- Aantallen --}}
    <div class="mt-6 grid grid-cols-2 gap-3 md:grid-cols-4" data-reveal>
        <x-ui.stat label="Aangemeld" :value="$totals['signedUp']" icon="user-plus" />
        <x-ui.stat label="Online" :value="$totals['live']" icon="store" hint="Aanmelden afgerond" />
        @if ($billing)
            <x-ui.stat label="In proef" :value="$totals['trial']" icon="clock" />
            <x-ui.stat label="Betalend" :value="$totals['paying']" icon="credit-card" />
        @else
            <x-ui.stat label="Bezig" :value="$totals['signedUp'] - $totals['live']" icon="clock" hint="Nog niet klaar" />
            <x-ui.stat label="Betalend" value="–" icon="credit-card" hint="Betalen staat nog uit" />
        @endif
    </div>

    @if ($totals['attention'] > 0)
        <div class="mt-5 flex items-start gap-3 rounded-[1.5rem] bg-gold-soft p-4 text-gold-ink" role="status" data-reveal>
            <x-ui.icon name="bell" size="20" class="mt-0.5 shrink-0" />
            <p class="text-sm"><strong>{{ $totals['attention'] }} {{ $totals['attention'] === 1 ? 'zaak heeft' : 'zaken hebben' }} aandacht nodig.</strong> De proef loopt bijna af of is voorbij. Bel ze, dan weten ze nog hoe ze een abonnement afsluiten.</p>
        </div>
    @endif

    {{-- De zaken --}}
    <h2 class="mt-9 text-xl font-bold tracking-tight" data-reveal>Jouw zaken</h2>

    @if ($rows->isEmpty())
        <div class="mt-4 rounded-[1.75rem] bg-card p-8 text-center shadow-soft ring-1 ring-border/70" data-reveal>
            <span class="mx-auto flex size-14 items-center justify-center rounded-full bg-surface text-muted"><x-ui.icon name="store" /></span>
            <p class="mt-4 text-lg font-bold">Nog geen aanmeldingen</p>
            <p class="mx-auto mt-1 max-w-sm text-sm text-muted">Deel je link of laat een kapper de QR-code scannen. Zodra hij zijn zaak aanmeldt, staat hij hier.</p>
        </div>
    @else
        <div class="stagger mt-4 space-y-3">
            @foreach ($rows as $row)
                @php($shop = $row['shop'])
                <article wire:key="shop-{{ $shop->id }}" @class(['rounded-[1.5rem] bg-card p-4 shadow-soft ring-1 md:p-5', 'ring-star/60' => $row['attention'], 'ring-border/70' => ! $row['attention']])>
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="truncate text-base font-bold tracking-tight">{{ $shop->name }}</h3>
                            <p class="truncate text-sm text-muted">{{ $shop->city }}@if ($row['owner']) · {{ $row['owner'] }}@endif · aangemeld {{ nl_date($shop->created_at, 'D MMM YYYY') }}</p>
                        </div>
                        @if (! $row['finished'])
                            <x-ui.badge status="neutral">Bezig met aanmelden</x-ui.badge>
                        @elseif (! $billing)
                            <x-ui.badge status="completed">Online</x-ui.badge>
                        @else
                            @switch($row['state'])
                                @case(\App\Enums\AccessState::Active)
                                    <x-ui.badge status="completed">Betaalt</x-ui.badge>
                                    @break
                                @case(\App\Enums\AccessState::Trial)
                                    <x-ui.badge :status="$row['attention'] ? 'gold' : 'scheduled'">Proef · nog {{ $row['daysLeft'] }} {{ $row['daysLeft'] === 1 ? 'dag' : 'dagen' }}</x-ui.badge>
                                    @break
                                @case(\App\Enums\AccessState::Expired)
                                    <x-ui.badge status="no_show">Proef voorbij</x-ui.badge>
                                    @break
                                @case(\App\Enums\AccessState::PastDue)
                                    <x-ui.badge status="no_show">Betaling mislukt</x-ui.badge>
                                    @break
                                @case(\App\Enums\AccessState::Free)
                                    <x-ui.badge status="gold">Gratis periode</x-ui.badge>
                                    @break
                                @default
                                    <x-ui.badge status="neutral">{{ $row['state']->label() }}</x-ui.badge>
                            @endswitch
                        @endif
                    </div>

                    @if ($row['attention'] || $shop->phone)
                        <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-border/70 pt-3">
                            <p class="text-sm {{ $row['attention'] ? 'font-medium text-gold-ink' : 'text-muted' }}">
                                @if ($row['state'] === \App\Enums\AccessState::Expired && $row['attention'])
                                    Klanten kunnen niet meer boeken. Help hem een abonnement af te sluiten.
                                @elseif ($row['state'] === \App\Enums\AccessState::PastDue && $row['attention'])
                                    De laatste betaling is mislukt. Wijs hem op Meer → Abonnement.
                                @elseif ($row['attention'])
                                    Bel hem voordat de proef afloopt.
                                @elseif (! $row['finished'])
                                    Hij is nog niet klaar. Help hem de laatste stappen af te maken.
                                @else
                                    Alles loopt.
                                @endif
                            </p>
                            <div class="flex flex-wrap gap-2">
                                @if ($billing && $row['finished'] && in_array($row['state'], [\App\Enums\AccessState::Trial, \App\Enums\AccessState::Expired], true))
                                    @if (in_array($shop->id, $openExtensions, true))
                                        <span class="inline-flex min-h-11 items-center rounded-input bg-surface px-3 text-sm font-medium text-muted lg:min-h-9">Verzoek verstuurd</span>
                                    @else
                                        <x-ui.button variant="ghost" size="sm" wire:click="openExtension({{ $shop->id }})">Proef verlengen</x-ui.button>
                                    @endif
                                @endif
                                @if ($shop->phone)
                                    <x-ui.button :href="'tel:'.$shop->phone" variant="secondary" size="sm" icon="phone">Bel</x-ui.button>
                                @endif
                            </div>
                        </div>
                    @endif
                </article>
            @endforeach
        </div>
    @endif

    {{-- Een wens of vraag doorgeven --}}
    <section class="mt-9 rounded-[1.75rem] bg-card p-5 shadow-soft ring-1 ring-border/70 md:p-6" data-reveal>
        <h2 class="text-lg font-bold tracking-tight">Een wens of vraag doorgeven</h2>
        <p class="mt-1 text-sm text-muted">Vroeg een kapper iets wat BarberCuts nog niet kan, of weet je iets niet? Schrijf het op. Dat helpt ons bepalen wat we als eerste bouwen.</p>
        <form wire:submit="submitWish" class="mt-4 space-y-3" novalidate>
            <x-ui.textarea label="Wat wil of vraag je?" wire:model="wish" rows="3" />
            <x-ui.button type="submit" variant="secondary" loading="submitWish">Versturen</x-ui.button>
        </form>

        @if ($requests->isNotEmpty())
            <h3 class="mt-6 text-sm font-semibold">Je laatste verzoeken</h3>
            <ul class="mt-2 divide-y divide-border/70 text-sm">
                @foreach ($requests as $request)
                    <li class="py-2.5" wire:key="request-{{ $request->id }}">
                        <div class="flex items-center justify-between gap-3">
                            <span class="min-w-0 truncate font-medium">{{ $request->type->label() }}@if ($request->barbershop) · {{ $request->barbershop->name }}@endif</span>
                            <x-ui.badge :status="$request->isOpen() ? 'scheduled' : ($request->status === \App\Enums\PitcherRequestStatus::Done ? 'completed' : 'neutral')">{{ $request->status->label() }}</x-ui.badge>
                        </div>
                        <p class="mt-0.5 truncate text-muted">{{ $request->message }}</p>
                        @if ($request->answer)<p class="mt-1 text-text">Antwoord: {{ $request->answer }}</p>@endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <x-ui.bottom-sheet name="extension" title="Proef verlengen aanvragen">
        <form wire:submit="submitExtension" class="space-y-4 pb-2" novalidate>
            <p class="text-sm text-muted">Vertel kort waarom {{ $extensionShopId ? 'de zaak' : 'de zaak' }} meer tijd nodig heeft. Wij bekijken het en laten het je weten.</p>
            <x-ui.textarea label="Reden" wire:model="extensionReason" rows="3" hint="Bijvoorbeeld: veel drukte, nog geen tijd gehad om te proberen." />
            <x-ui.button type="submit" full loading="submitExtension">Verzoek versturen</x-ui.button>
        </form>
    </x-ui.bottom-sheet>

    </div>
