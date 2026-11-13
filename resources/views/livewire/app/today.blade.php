@php
    $pct = fn (?float $change): ?string => $change === null ? null : ($change >= 0 ? '+' : '').number_format($change * 100, 0, ',', '.').'%';
    $trend = fn (?float $change): ?string => $change === null ? null : ($change >= 0 ? 'up' : 'down');
@endphp

<div>
    <x-ui.page-header :title="$greeting.', '.explode(' ', trim(auth()->user()->name))[0]" :subtitle="$shop->name.' · '.ucfirst(now($shop->timezone)->locale('nl')->isoFormat('dddd D MMMM'))">
        <x-ui.button :href="route('app.appointments.create')" icon="plus" size="sm" class="max-lg:hidden!" wire:navigate>Nieuwe afspraak</x-ui.button>
    </x-ui.page-header>

    <div class="space-y-6 px-4 py-4 lg:space-y-8 lg:py-6">
        @unless ($emailVerified)
            <x-ui.alert type="warning" title="Bevestig je e-mailadres">
                <p>We hebben een link gestuurd naar <strong>{{ auth()->user()->email }}</strong>. Niets ontvangen? Kijk ook bij ongewenste mail.</p>
                <x-ui.button variant="ghost" size="sm" class="-ml-3" wire:click="resendVerification" loading="resendVerification">Stuur de link opnieuw</x-ui.button>
            </x-ui.alert>
        @endunless

        {{-- Kerncijfers --}}
        <div class="stagger grid grid-cols-2 gap-3 lg:grid-cols-3 lg:gap-4 xl:grid-cols-5">
            <x-ui.stat icon="euro" :label="$isOwner ? 'Omzet vandaag' : 'Mijn omzet vandaag'" :value="money($revenue)" :delta="$pct($revenueTodayChange)" :trend="$trend($revenueTodayChange)" hint="t.o.v. gisteren" />
            <x-ui.stat icon="calendar" label="Afspraken vandaag" :value="$appointments->count()" :hint="$remaining > 0 ? $remaining.' nog te gaan' : 'Alles gedaan'" />
            <x-ui.stat icon="trending-up" label="Omzet deze maand" :value="money($revenueMonth['revenue_cents'])" :delta="$pct($revenueMonth['change'])" :trend="$trend($revenueMonth['change'])" hint="t.o.v. vorige maand" />
            <x-ui.stat icon="activity" label="Afspraken deze maand" :value="$appointmentsMonth" />
            <x-ui.stat icon="user-plus" label="Nieuwe klanten" :value="$newCustomers" hint="deze maand" class="col-span-2 lg:col-span-1" />
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3 lg:gap-8">
            <div class="space-y-6 lg:col-span-2 lg:space-y-8">
                {{-- Eerstvolgende en vandaag --}}
                @if ($next)
                    <x-ui.section title="Eerstvolgende">
                        <x-app.appointment-card :appointment="$next" :timezone="$shop->timezone" :show-staff="$isOwner" highlight wire:key="next-{{ $next->id }}" />
                    </x-ui.section>
                @endif

                <x-ui.section title="Vandaag">
                    @if ($appointments->isEmpty())
                        <x-ui.card :padding="false" flat>
                            <x-ui.empty-state icon="calendar" title="Nog geen afspraken vandaag" text="Zodra klanten boeken via je link, zie je hier je dag.">
                                <x-ui.button :href="route('app.appointments.create')" variant="secondary" icon="plus" wire:navigate>Nieuwe afspraak</x-ui.button>
                            </x-ui.empty-state>
                        </x-ui.card>
                    @else
                        <div class="stagger space-y-2">
                            @foreach ($appointments as $appointment)
                                <x-app.appointment-card :appointment="$appointment" :timezone="$shop->timezone" :show-staff="$isOwner" wire:key="appointment-{{ $appointment->id }}" />
                            @endforeach
                        </div>
                    @endif
                </x-ui.section>

                {{-- Omzetgrafiek --}}
                <x-ui.card class="space-y-4" wire:key="revenue-chart">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-medium text-muted">{{ $isOwner ? 'Omzet' : 'Mijn omzet' }} · {{ $chartLabel }}</p>
                            <p class="tnum mt-1 text-[1.625rem] leading-8 font-semibold tracking-tight">{{ money($chart['revenue_cents']) }}</p>
                            @if ($pct($chart['change']) !== null)
                                <p class="mt-1 text-xs text-muted"><span @class(['font-semibold', 'text-completed' => ($chart['change'] ?? 0) >= 0, 'text-no-show' => ($chart['change'] ?? 0) < 0])>{{ $pct($chart['change']) }}</span> t.o.v. de periode ervoor</p>
                            @endif
                        </div>
                        <x-ui.segmented label="Periode van de grafiek" wire:model.live="range" :value="$range" name="range" class="w-full sm:w-auto sm:min-w-64"
                            :options="['7' => '7 dagen', '30' => '30 dagen', 'year' => 'Dit jaar']" />
                    </div>

                    <div class="relative" wire:loading.class="opacity-40" wire:target="range" style="transition: opacity 200ms">
                        <x-ui.area-chart :series="$chartSeries" wire:key="chart-{{ $range }}" />
                    </div>
                </x-ui.card>
            </div>

            <div class="space-y-6 lg:space-y-8">
                <x-ui.card class="space-y-4">
                    <div class="flex items-center justify-between">
                        <h2 class="text-base font-semibold lg:text-sm">Statusverdeling</h2>
                        <span class="text-xs text-muted">deze maand</span>
                    </div>
                    <x-ui.status-bar :items="[
                        ['label' => 'Gepland', 'value' => $status['scheduled'], 'color' => 'var(--color-scheduled)'],
                        ['label' => 'Voltooid', 'value' => $status['completed'], 'color' => 'var(--color-completed)'],
                        ['label' => 'Geannuleerd', 'value' => $status['cancelled'], 'color' => 'var(--color-cancelled)'],
                        ['label' => 'No-show', 'value' => $status['no_show'], 'color' => 'var(--color-no-show)'],
                    ]" />
                </x-ui.card>

                <x-ui.section title="Snelle acties">
                    <x-ui.card :padding="false" class="divide-y divide-border overflow-hidden">
                        <x-ui.list-item :href="route('app.appointments.create')" title="Nieuwe afspraak" subtitle="Telefonisch of aan de balie">
                            <x-slot:leading><span class="flex size-9 items-center justify-center rounded-full bg-gold-soft text-gold-ink"><x-ui.icon name="plus" size="18" /></span></x-slot:leading>
                        </x-ui.list-item>
                        <x-ui.list-item :href="route('app.calendar')" title="Agenda openen" subtitle="Dag, week of maand">
                            <x-slot:leading><span class="flex size-9 items-center justify-center rounded-full bg-surface text-text"><x-ui.icon name="calendar" size="18" /></span></x-slot:leading>
                        </x-ui.list-item>
                        <x-ui.list-item :href="route('app.revenue')" title="Omzet bekijken" subtitle="Per dag, week, maand of jaar">
                            <x-slot:leading><span class="flex size-9 items-center justify-center rounded-full bg-surface text-text"><x-ui.icon name="trending-up" size="18" /></span></x-slot:leading>
                        </x-ui.list-item>
                    </x-ui.card>
                </x-ui.section>

                <x-ui.section title="Recente activiteit">
                    @if ($activity === [])
                        <x-ui.card :padding="false" flat><p class="p-4 text-sm text-muted">Nog geen activiteit. Zodra er afspraken zijn, zie je hier wat er gebeurt.</p></x-ui.card>
                    @else
                        <x-ui.card :padding="false" class="divide-y divide-border overflow-hidden">
                            @foreach ($activity as $item)
                                <x-ui.list-item wire:click="selectAppointment({{ $item['appointment']->id }})" wire:key="activity-{{ $item['appointment']->id }}-{{ $item['at']->timestamp }}" :chevron="false"
                                    :title="$item['appointment']->customer->name" :subtitle="$item['event'].' · '.$item['appointment']->service_name">
                                    <x-slot:leading>
                                        <span @class([
                                            'flex size-9 items-center justify-center rounded-full',
                                            'bg-completed/10 text-completed' => $item['tone'] === 'completed',
                                            'bg-no-show/10 text-no-show' => $item['tone'] === 'no_show',
                                            'bg-cancelled/15 text-muted' => $item['tone'] === 'cancelled',
                                            'bg-scheduled/10 text-scheduled' => $item['tone'] === 'scheduled',
                                        ])><x-ui.icon :name="$item['icon']" size="18" /></span>
                                    </x-slot:leading>
                                    <x-slot:trailing><span class="text-xs text-muted">{{ $item['at']->locale('nl')->diffForHumans(short: true, parts: 1) }}</span></x-slot:trailing>
                                </x-ui.list-item>
                            @endforeach
                        </x-ui.card>
                    @endif
                </x-ui.section>

                @if ($checklist !== [])
                    <x-ui.section title="Maak je zaak af">
                        <x-ui.card :padding="false" class="divide-y divide-border overflow-hidden">
                            @foreach ($checklist as $item)
                                <x-ui.list-item :href="$item['done'] ? null : $item['href']" :title="$item['label']" wire:key="check-{{ $loop->index }}">
                                    <x-slot:leading>
                                        <x-ui.icon :name="$item['done'] ? 'circle-check' : 'circle'" @class(['text-completed' => $item['done'], 'text-border-strong' => ! $item['done']]) />
                                    </x-slot:leading>
                                </x-ui.list-item>
                            @endforeach
                        </x-ui.card>
                    </x-ui.section>
                @endif

                <x-ui.section title="Je boekingslink" text="Deel deze link met je klanten.">
                    <x-ui.copy-link :url="$shop->bookingUrl()" :label="$shop->bookingUrlLabel()" :action="$isOwner ? 'markLinkShared' : null" />
                </x-ui.section>

                <x-app.install-tip />
            </div>
        </div>
    </div>

    <x-app.appointment-sheet :appointment="$selected" :can-update="$canUpdateSelected" :mode="$sheetMode" :timezone="$shop->timezone"
        :custom-tip="$customTip" :tip="$tip" :redeem="$redeem" />
</div>
