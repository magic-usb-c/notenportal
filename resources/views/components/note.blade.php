@props([
    'wert' => null,
    'variante' => 'text', // text | badge | hero
    'stellen' => null,    // null = Note (4.25/4.5), Zahl = Durchschnitt mit fester Stellenzahl
])
@php
    // Farbe nur bei knapp/ungenügend, ungenügend zusätzlich unterstrichen (NotenSkala)
    $skala = \App\Support\NotenSkala::class;
    $klasse = match ($variante) {
        'badge' => 'inline-flex h-6 min-w-11 items-center justify-center rounded-md px-1.5 text-sm font-semibold tabular-nums '.$skala::badge($wert),
        'hero' => 'font-semibold '.$skala::text($wert),
        default => 'font-semibold tabular-nums '.$skala::text($wert),
    };
@endphp
<span {{ $attributes->merge(['class' => $klasse]) }}>{{ $skala::format($wert, $stellen) }}</span>
