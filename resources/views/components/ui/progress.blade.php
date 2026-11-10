{{-- Voortgang van een meerstappenflow: "Stap 2 van 6" met balk. --}}
@props(['step', 'total'])

<div {{ $attributes }}>
    <p class="text-xs font-medium text-muted">Stap {{ $step }} van {{ $total }}</p>
    <div class="mt-2 h-1 rounded-pill bg-border" role="progressbar" aria-valuemin="1" aria-valuemax="{{ $total }}" aria-valuenow="{{ $step }}">
        <div class="h-1 rounded-pill bg-accent transition-all" style="width: {{ round($step / $total * 100) }}%"></div>
    </div>
</div>
