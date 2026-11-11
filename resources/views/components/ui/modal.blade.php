{{-- Modal: dezelfde component als de bottom sheet (sheet op mobiel, dialoog op desktop). --}}
@props(['name', 'title' => null, 'size' => 'md'])

<x-ui.bottom-sheet :name="$name" :title="$title" :size="$size" {{ $attributes }}>{{ $slot }}</x-ui.bottom-sheet>
