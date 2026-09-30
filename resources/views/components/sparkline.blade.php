@props([
    'werte' => [],    // Noten je Semester (null = keine Note), Skala 1–6
    'breite' => 96,
    'hoehe' => 28,
    'zahl' => true,   // letzter Wert als Zahl daneben
    'label' => __('Verlauf'),
])
{{-- Sparkline (Tufte): graues Band von genügend bis 6, Linie in der Diagrammfarbe, letzter Punkt wie die Balken
     (knapp/ungenügend in Notenfarbe) --}}
@php
    $skala = \App\Support\NotenSkala::class;
    $werte = array_values($werte);
    $n = count($werte);
    $grenze = $skala::genuegend();
    $y = fn (float $v) => round($hoehe - 2 - ($v - 1) / 5 * ($hoehe - 4), 2);
    $x = fn (int $i) => $n > 1 ? round(2 + $i * ($breite - 4) / ($n - 1), 2) : $breite / 2;
    $punkte = [];
    foreach ($werte as $i => $v) {
        if ($v !== null) {
            $punkte[] = [$x($i), $y((float) $v), (float) $v];
        }
    }
    $letzter = end($punkte) ?: null;
    $text = collect($punkte)->map(fn ($p) => $skala::format($p[2], 1))->implode(', ');
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5']) }}>
    <svg class="shrink-0 overflow-visible" width="{{ $breite }}" height="{{ $hoehe }}" viewBox="0 0 {{ $breite }} {{ $hoehe }}" role="img"
         aria-label="{{ $label }}{{ $text !== '' ? ': '.$text : ': '.__('keine Noten') }}">
        <rect x="0" y="{{ $y(6) }}" width="{{ $breite }}" height="{{ round($y($grenze) - $y(6), 2) }}" rx="3" class="fill-muted/12"/>
        @if(count($punkte) > 1)
            <polyline fill="none" class="stroke-chart-1" stroke-width="1.5" stroke-linejoin="round" stroke-linecap="round"
                      points="{{ collect($punkte)->map(fn ($p) => $p[0].','.$p[1])->implode(' ') }}"/>
        @endif
        @if($letzter)
            <circle cx="{{ $letzter[0] }}" cy="{{ $letzter[1] }}" r="2.75" class="{{ str_replace('bg-', 'fill-', $skala::balken($letzter[2])) }}"/>
        @endif
    </svg>
    @if($zahl && $letzter)
        <span class="text-xs font-medium tabular-nums {{ $skala::text($letzter[2]) }}" aria-hidden="true">{{ $skala::format($letzter[2], 1) }}</span>
    @endif
</span>
