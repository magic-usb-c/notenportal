@props([
    'wert' => null,
    'variante' => 'text', // text | badge | hero
    'stellen' => null,    // null = Note (4.25/4.5), Zahl = Durchschnitt mit fester Stellenzahl
])
@php
    $skala = \App\Support\NotenSkala::class;
    $klasse = match ($variante) {
        'badge' => 'inline-flex items-center justify-center min-w-12 px-2 py-1 rounded-xl font-bold text-sm tabular-nums '.$skala::badge($wert),
        'hero' => 'font-extrabold tabular-nums tracking-tight '.$skala::text($wert).' '.$skala::glow($wert),
        default => 'font-semibold tabular-nums '.$skala::text($wert),
    };
@endphp
<span {{ $attributes->merge(['class' => $klasse]) }}>{{ $skala::format($wert, $stellen) }}</span>
