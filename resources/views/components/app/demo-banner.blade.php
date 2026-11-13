{{-- Balk bovenaan /app in een demozaak (fase 6): bekijken als klant, of opnieuw beginnen met verse gegevens. --}}
@props(['shop'])

@if ($shop->is_demo)
    <div role="status" class="bg-gold-soft px-4 py-1 text-xs text-gold-ink">
        <form method="POST" action="{{ route('demo.start') }}" class="mx-auto flex max-w-2xl items-center justify-between gap-3">
            @csrf
            <span><strong>Demo</strong><span class="max-sm:hidden"> · alles is fictief, er wordt niets verstuurd</span></span>
            <span class="flex shrink-0 items-center gap-4">
                <a href="{{ $shop->bookingUrl() }}" target="_blank" rel="noopener" class="flex min-h-11 items-center font-semibold underline">Bekijk als klant</a>
                <button type="submit" class="min-h-11 font-semibold underline">Opnieuw beginnen</button>
            </span>
        </form>
    </div>
@endif
