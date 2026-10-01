@props([
    'label',
    'wert' => null,
    'note' => false,   // Notenwert: formatiert mit einer Stelle, Farbe nur bei knapp/ungenügend
    'sub' => null,
    'href' => null,
    'ton' => 'neutral', // neutral | accent | rot | gelb | gruen – Farbe nur mit Bedeutung
])
@php
    $istNote = $note !== false;
    $farbe = $istNote ? \App\Support\NotenSkala::text($note) : match ($ton) {
        'accent' => 'text-accent-text',
        'rot' => 'text-note-ungenuegend',
        'gelb' => 'text-note-knapp',
        default => 'text-text',
    };
    $leer = $istNote ? $note === null : ($wert === null || $wert === '');
    $anzeige = $istNote ? \App\Support\NotenSkala::format($note, 1) : $wert;
    $tag = $href ? 'a' : 'div';
@endphp
<{{ $tag }} @if($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'np-karte block px-4 py-3.5'
    .($href ? ' transition-colors duration-100 hover:border-border-strong/50 hover:bg-surface-2/60 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring' : '')]) }}>
    <div class="text-xs font-medium text-muted">{{ $label }}</div>
    <div class="mt-1 text-xl font-semibold tabular-nums {{ $farbe }}">@if($leer)<span class="font-normal text-muted">{{ \App\Support\NotenSkala::format(null) }}</span>@else{{ $anzeige }}@endif</div>
    @if($sub)
        <div class="truncate text-xs text-muted">{{ $sub }}</div>
    @endif
</{{ $tag }}>
