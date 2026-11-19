{{--
    Wachtwoordveld met een oogje om het te tonen of te verbergen. Met meter="true" laat een sterktebalk zien hoe sterk het is
    (alleen als hulp; de echte eisen staan in de validatie).
--}}
@props(['label' => null, 'name' => null, 'hint' => null, 'meter' => false])

@php
    $name ??= $attributes->whereStartsWith('wire:model')->first();
    $id = $attributes->get('id') ?? 'f-'.str_replace(['.', '[', ']'], '-', (string) $name);
    $invalid = $name && $errors->has($name);
@endphp

<x-ui.field :label="$label" :for="$id" :name="$name" :hint="$meter ? null : $hint">
    <div x-data="{
            show: false, value: '',
            get score() {
                const v = this.value;
                if (v.length < 8) { return v.length ? 1 : 0; }
                const mixed = /[a-z]/.test(v) && /[A-Z]/.test(v) && /\d/.test(v);
                return 2 + (v.length >= 12 ? 1 : 0) + (mixed ? 1 : 0);
            },
            get verdict() { return ['', 'Te kort', 'Redelijk', 'Goed', 'Sterk'][Math.min(4, this.score)]; },
        }" x-init="value = $refs.field.value">
        <div class="relative">
            <input x-ref="field" :type="show ? 'text' : 'password'" id="{{ $id }}" name="{{ $name }}"
                aria-invalid="{{ $invalid ? 'true' : 'false' }}"
                @if ($invalid) aria-describedby="{{ $id }}-error" @elseif ($hint) aria-describedby="{{ $id }}-hint" @endif
                x-on:input="value = $event.target.value"
                {{ $attributes->except('id')->class('bc-input pr-12') }} />
            <button type="button" x-on:click="show = !show" :aria-pressed="show" :aria-label="show ? 'Verberg wachtwoord' : 'Toon wachtwoord'"
                class="absolute inset-y-0 right-0 flex w-12 items-center justify-center rounded-r-input text-muted transition-colors hover:text-text focus-visible:ring-2 focus-visible:ring-[var(--focus)] focus-visible:outline-none">
                <x-ui.icon name="eye" size="20" x-show="!show" />
                <x-ui.icon name="eye-off" size="20" x-show="show" x-cloak />
            </button>
        </div>

        @if ($meter)
            <div class="mt-2" x-show="value.length" x-cloak x-transition.opacity>
                <div class="grid grid-cols-4 gap-1.5" aria-hidden="true">
                    @foreach (range(1, 4) as $segment)
                        <span class="h-1.5 rounded-full transition-colors duration-300"
                            :class="score >= {{ $segment }} ? (score <= 1 ? 'bg-danger' : (score === 2 ? 'bg-star' : 'bg-switch')) : 'bg-border'"></span>
                    @endforeach
                </div>
                <p class="mt-1.5 text-xs text-muted" role="status"><span x-text="verdict" class="font-medium text-text"></span> · {{ $hint ?? 'Minimaal 8 tekens.' }}</p>
            </div>
            @unless ($invalid)
                <p class="mt-1.5 text-xs text-muted" x-show="!value.length">{{ $hint ?? 'Minimaal 8 tekens.' }}</p>
            @endunless
        @endif
    </div>
</x-ui.field>
