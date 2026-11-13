{{--
    Wisselen van account: tussen je zaken, je klantaccount, de demo en het beheer, of inloggen met een ander account.
    Staat één keer in de layout; openen met $dispatch('open-sheet', 'account-switch').
--}}
@auth
    @php
        $user = auth()->user();
        $shops = $user->shops()->whereNull('onboarding_step')->orderBy('shop_user.id')->get()->reject(fn ($shop) => $shop->isExpiredDemo());
        $currentShopId = request()->routeIs('app.*') ? session('current_shop_id') : null;
    @endphp

    <x-ui.bottom-sheet name="account-switch" title="Wissel van account">
        <div class="flex items-center gap-3 pb-4">
            <x-ui.avatar :name="$user->name" />
            <div class="min-w-0">
                <p class="truncate text-base font-semibold">{{ $user->name }}</p>
                <p class="truncate text-sm text-muted">{{ $user->email }}</p>
            </div>
        </div>

        <div class="divide-y divide-border overflow-hidden rounded-card border border-border/70">
            @foreach ($shops as $shop)
                <form method="POST" action="{{ route('switch.shop', $shop) }}">
                    @csrf
                    <button type="submit" class="flex min-h-14 w-full items-center gap-3 px-4 py-3 text-left transition-colors duration-150 hover:bg-surface/70 active:bg-surface">
                        <x-ui.icon-tile name="scissors" :color="$shop->accent_color" />
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-base font-medium">{{ $shop->name }}</span>
                            <span class="block truncate text-sm text-muted">{{ $shop->is_demo ? 'Demozaak' : ($shop->pivot->role === 'owner' ? 'Eigenaar' : 'Kapper') }}{{ $shop->city ? ' · '.$shop->city : '' }}</span>
                        </span>
                        @if ($currentShopId === $shop->id)
                            <x-ui.icon name="check" size="20" class="shrink-0 text-link" />
                        @endif
                    </button>
                </form>
            @endforeach

            <x-ui.list-item :href="route('account')" title="Mijn afspraken" subtitle="Als klant" :chevron="false">
                <x-slot:leading><x-ui.icon-tile name="calendar" color="#34c759" /></x-slot:leading>
                @if (request()->routeIs('account'))
                    <x-slot:trailing><x-ui.icon name="check" size="20" class="text-link" /></x-slot:trailing>
                @endif
            </x-ui.list-item>

            @if ($user->is_pitcher)
                <x-ui.list-item :href="route('demo')" title="Demo" subtitle="Voor pitchers" :chevron="false">
                    <x-slot:leading><x-ui.icon-tile name="eye" color="#5e5ce6" /></x-slot:leading>
                </x-ui.list-item>
            @endif

            @if ($user->is_admin)
                <a href="/admin" class="flex min-h-14 items-center gap-3 px-4 py-3 transition-colors duration-150 hover:bg-surface/70 active:bg-surface">
                    <x-ui.icon-tile name="shield-check" color="#1d1d1f" />
                    <span class="min-w-0 flex-1"><span class="block text-base font-medium">Beheer</span><span class="block text-sm text-muted">Adminpaneel van BarberCuts</span></span>
                </a>
            @endif
        </div>

        {{-- Uitloggen en meteen naar het inlogscherm, om met een ander account in te loggen --}}
        <form method="POST" action="{{ route('logout') }}" class="mt-4">
            @csrf
            <input type="hidden" name="switch" value="1" />
            <x-ui.button type="submit" variant="secondary" icon="user" full>Inloggen met een ander account</x-ui.button>
        </form>
    </x-ui.bottom-sheet>
@endauth
