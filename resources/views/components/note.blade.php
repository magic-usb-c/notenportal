@props([
    'wert' => null,
    'variante' => 'text', // text | badge | hero
    'stellen' => null,    // null = Note (4.25/4.5), Zahl = Durchschnitt mit fester Stellenzahl
    'stufe' => null,      // Stufe statt Zahl (Sport: A/B/C/d) – neutral dargestellt, rechnet nie
])
@php
    // Farbe nur bei knapp/ungenügend, ungenügend zusätzlich unterstrichen (NotenSkala)
    $skala = \App\Support\NotenSkala::class;
    $klasse = match ($variante) {
        'badge' => 'inline-flex h-6 min-w-11 items-center justify-center rounded-md px-1.5 text-sm font-semibold tabular-nums '.$skala::badge($wert),
        'hero' => 'font-semibold '.$skala::text($wert),
        default => 'font-semibold tabular-nums '.$skala::text($wert),
    };
    // Präferenz «Notenanzeige»: nur beim bisherigen Standardfall (Durchschnitt, 1 Nachkommastelle)
    // durch die persönliche Einstellung ersetzt – Notenblatt/Exporte/Mails rufen NotenSkala::format()
    // direkt auf und bleiben unberührt; eine feste andere Stellenzahl bleibt unverändert.
    $anzeigeStellen = $stellen === 1
        ? (int) (\App\Support\Darstellung::fuer(auth()->user())['notenanzeige'] ?? \App\Support\Darstellung::NOTENANZEIGE_1)
        : $stellen;
@endphp
@if($stufe !== null && $stufe !== '')
    <span {{ $attributes->merge(['class' => $variante === 'badge'
        ? 'inline-flex h-6 min-w-11 items-center justify-center rounded-md bg-surface-2 px-1.5 text-sm font-semibold text-text'
        : 'font-semibold text-text']) }} title="{{ $skala::stufeText($stufe) }}">{{ $skala::stufeKurz($stufe) }}</span>
@else
    <span {{ $attributes->merge(['class' => $klasse]) }}>{{ $skala::format($wert, $anzeigeStellen) }}</span>
@endif
