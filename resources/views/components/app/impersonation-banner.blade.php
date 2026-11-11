{{-- Duidelijke banner als een platformadmin als iemand anders in /app kijkt, met een knop om terug te gaan (§11). --}}
@if (session()->has('impersonator_id'))
    <div role="status" class="bg-primary px-4 py-2 text-xs text-white">
        <form method="POST" action="{{ route('impersonate.stop') }}" class="mx-auto flex max-w-2xl items-center justify-between gap-3">
            @csrf
            <span>Je bekijkt als <strong>{{ auth()->user()?->name }}</strong>@isset($currentShop) bij {{ $currentShop->name }}@endisset.</span>
            <button type="submit" class="min-h-11 shrink-0 font-semibold underline">Terug naar admin</button>
        </form>
    </div>
@endif
