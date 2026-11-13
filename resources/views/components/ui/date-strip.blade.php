{{--
    Swipebare datumstrip. days: lijst van ['date' => 'Y-m-d', 'disabled' => bool].
    wire:model gaat naar de radio's; zonder Livewire werkt het als gewone radio-groep (name).
    compact: zeven dagen passen naast elkaar op één regel (weekstrip in de agenda).
--}}
@props(['days' => [], 'name' => 'date', 'selected' => null, 'compact' => false])

@php
    $model = $attributes->whereStartsWith('wire:model');
    $today = now('Europe/Amsterdam')->toDateString();
@endphp

<div role="radiogroup" aria-label="Kies een dag"
    {{ $attributes->whereDoesntStartWith('wire:model')->class('no-scrollbar -mx-4 flex snap-x gap-2 overflow-x-auto px-4 py-1') }}>
    @foreach ($days as $day)
        @php
            $date = \Carbon\CarbonImmutable::parse($day['date'])->locale('nl');
            $disabled = (bool) ($day['disabled'] ?? false);
        @endphp
        <label class="relative {{ $compact ? 'min-w-0 flex-1' : 'shrink-0' }} snap-start {{ $disabled ? '' : 'cursor-pointer' }}" wire:key="day-{{ $day['date'] }}">
            <input type="radio" class="peer sr-only" name="{{ $name }}" value="{{ $day['date'] }}"
                @checked($selected === $day['date']) @disabled($disabled) {{ $model }} />
            <span class="flex {{ $compact ? 'h-14 w-full' : 'h-[4.5rem] w-14' }} flex-col items-center justify-center rounded-card border border-transparent bg-surface text-text transition-[background-color,border-color,color,transform] duration-150 ease-out-soft hover:bg-border active:scale-[0.97] peer-checked:border-accent peer-checked:bg-accent peer-checked:text-on-accent peer-focus-visible:ring-2 peer-focus-visible:ring-[var(--focus)] peer-focus-visible:ring-offset-2 peer-disabled:bg-transparent peer-disabled:text-muted peer-disabled:opacity-50">
                <span class="text-xs font-medium opacity-70">{{ $day['date'] === $today && ! $compact ? 'nu' : rtrim($date->isoFormat('dd'), '.') }}</span>
                <span class="text-lg font-bold leading-6 tabular-nums">{{ $date->day }}</span>
                @unless ($compact)
                    <span class="text-xs opacity-70">{{ rtrim($date->isoFormat('MMM'), '.') }}</span>
                @endunless
            </span>
        </label>
    @endforeach
</div>
