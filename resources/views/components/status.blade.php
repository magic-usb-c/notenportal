@props(['status', 'text' => null])
@php
    // Farbe nur im Punkt, Wort in Textfarbe (nie nur Farbe)
    [$punkt, $standard] = match ($status) {
        'rot' => ['bg-note-ungenuegend', __('Kritisch')],
        'gelb' => ['bg-note-knapp', __('Beobachten')],
        'neutral' => ['bg-muted', __('Offen')],
        default => ['bg-note-gut', __('Im Plan')],
    };
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex h-6 items-center gap-1.5 whitespace-nowrap text-xs font-medium text-text']) }}>
    <span class="size-2 shrink-0 rounded-full {{ $punkt }}" aria-hidden="true"></span>{{ $text ?? $standard }}
</span>
