@props(['status', 'text' => null])
@php
    // Farbe nur im Punkt, Wort in Textfarbe (nie nur Farbe)
    [$punkt, $standard] = match ($status) {
        'rot' => ['bg-note-ungenuegend', __('kritisch')],
        'gelb' => ['bg-note-knapp', __('beobachten')],
        'neutral' => ['bg-muted', __('offen')],
        default => ['bg-note-gut', __('im Plan')],
    };
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex h-6 items-center gap-1.5 whitespace-nowrap rounded-md bg-surface-2 px-2 text-xs font-medium text-text']) }}>
    <span class="size-1.5 shrink-0 rounded-full {{ $punkt }}" aria-hidden="true"></span>{{ $text ?? $standard }}
</span>
