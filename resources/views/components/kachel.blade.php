@props([
    'label',
    'wert',
    'sub' => null,
    'href' => null,
    'ton' => 'neutral', // neutral | accent | rot | gelb | gruen
])
@php
    $farbe = match ($ton) {
        'accent' => 'text-accent',
        'rot' => 'text-red-600 dark:text-red-400',
        'gelb' => 'text-yellow-700 dark:text-yellow-400',
        'gruen' => 'text-green-700 dark:text-green-400',
        default => 'text-text',
    };
    $tag = $href ? 'a' : 'div';
@endphp
<{{ $tag }} @if($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'glass rounded-2xl px-4 py-3.5 block'.($href ? ' glass-lift' : '')]) }}>
    <div class="text-[11px] uppercase tracking-widest text-muted font-medium truncate">{{ $label }}</div>
    <div class="mt-1 text-2xl font-extrabold tabular-nums tracking-tight {{ $farbe }}">{{ $wert }}</div>
    @if($sub)
        <div class="text-xs text-muted truncate">{{ $sub }}</div>
    @endif
</{{ $tag }}>
