{{--
    Balk bovenaan /app in een demozaak (fase 6): bekijken als klant, of opnieuw beginnen met verse gegevens.
    Voor een bezoeker van de openbare demolink staat er ook de knop om de eigen zaak aan te melden.
--}}
@props(['shop'])

@if ($shop->is_demo)
    @php($visitor = auth()->user()?->is_demo_visitor === true)
    <div role="status" class="bg-gold-soft px-4 py-1 text-xs text-gold-ink">
        <form method="POST" action="{{ $visitor ? route('demo.try.restart') : route('demo.start') }}" class="mx-auto flex max-w-2xl items-center justify-between gap-3">
            @csrf
            <span class="shrink-0"><strong>Demo</strong><span class="max-sm:hidden"> · alles is fictief, er wordt niets verstuurd</span></span>
            <span class="flex min-w-0 items-center gap-3 sm:gap-4">
                <a href="{{ $shop->bookingUrl() }}" target="_blank" rel="noopener" class="flex min-h-11 items-center font-semibold underline">@if ($visitor)<span class="sm:hidden">Als klant</span><span class="max-sm:hidden">Bekijk als klant</span>@else Bekijk als klant @endif</a>
                <button type="submit" class="flex min-h-11 items-center font-semibold underline">{{ $visitor ? 'Opnieuw' : 'Opnieuw beginnen' }}</button>
                @if ($visitor)
                    <a href="{{ route('onboarding') }}" class="flex min-h-9 items-center rounded-pill bg-text px-3 font-semibold text-white">Eigen zaak<span class="max-sm:hidden">&nbsp;aanmelden</span></a>
                @endif
            </span>
        </form>
    </div>
@endif
