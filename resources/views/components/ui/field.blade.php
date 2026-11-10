{{-- Label + veld + hint/foutmelding. Wordt gebruikt door input, textarea, select, phone-input en money-input. --}}
@props(['label' => null, 'for' => null, 'name' => null, 'hint' => null, 'optional' => false])

@php($error = $name ? $errors->first($name) : null)

<div {{ $attributes->class('space-y-1.5') }}>
    @if ($label)
        <label for="{{ $for }}" class="flex items-baseline justify-between text-sm font-medium text-text">
            <span>{{ $label }}</span>
            @if ($optional)
                <span class="text-xs font-normal text-muted">optioneel</span>
            @endif
        </label>
    @endif

    {{ $slot }}

    @if ($error)
        <p id="{{ $for }}-error" class="text-xs text-danger" role="alert">{{ $error }}</p>
    @elseif ($hint)
        <p id="{{ $for }}-hint" class="text-xs text-muted">{{ $hint }}</p>
    @endif
</div>
