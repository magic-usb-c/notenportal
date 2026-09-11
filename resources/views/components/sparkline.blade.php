@props([
    'werte' => [],    // Noten je Semester (null = keine Note), Skala 1–6
    'breite' => 96,
    'hoehe' => 28,
])
@php
    $werte = array_values($werte);
    $n = count($werte);
    $grenze = \App\Support\NotenSkala::genuegend();
    $y = fn (float $v) => round($hoehe - 2 - ($v - 1) / 5 * ($hoehe - 4), 2);
    $x = fn (int $i) => $n > 1 ? round(2 + $i * ($breite - 4) / ($n - 1), 2) : $breite / 2;
    $punkte = [];
    foreach ($werte as $i => $v) {
        if ($v !== null) {
            $punkte[] = [$x($i), $y((float) $v), (float) $v];
        }
    }
    $letzter = end($punkte) ?: null;
@endphp
<svg {{ $attributes->merge(['class' => 'overflow-visible']) }} width="{{ $breite }}" height="{{ $hoehe }}" viewBox="0 0 {{ $breite }} {{ $hoehe }}" role="img"
     aria-label="Verlauf {{ collect($werte)->filter()->map(fn ($v) => \App\Support\NotenSkala::format($v))->implode(', ') }}">
    <line x1="0" x2="{{ $breite }}" y1="{{ $y($grenze) }}" y2="{{ $y($grenze) }}" class="stroke-border-strong/60" stroke-width="1" stroke-dasharray="3 3"/>
    @if(count($punkte) > 1)
        <polyline fill="none" class="stroke-accent" stroke-width="1.75" stroke-linejoin="round" stroke-linecap="round"
                  points="{{ collect($punkte)->map(fn ($p) => $p[0].','.$p[1])->implode(' ') }}"/>
    @endif
    @if($letzter)
        <circle cx="{{ $letzter[0] }}" cy="{{ $letzter[1] }}" r="2.75" class="{{ str_replace('bg-', 'fill-', \App\Support\NotenSkala::punkt($letzter[2])) }}"/>
    @endif
</svg>
