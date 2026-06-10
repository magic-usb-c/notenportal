{{-- resources/views/components/noten-verlauf.blade.php
     Wiederverwendbares SVG-Liniendiagramm für den Notenverlauf.
     Erwartet $points: Collection/Array von Objekten mit ->datum (date-string), ->wert (float), ->label (string|null) --}}
@props([
    'points',
    'title' => 'Notenverlauf',
    'subtitle' => null,
])

@php
    $pts = collect($points)->values();
@endphp

@if($pts->count() >= 2)
    @php
        // Chart-Geometrie (viewBox-Koordinaten)
        $w = 600; $h = 170;
        $padL = 30; $padR = 14; $padT = 14; $padB = 26;
        $innerW = $w - $padL - $padR;
        $innerH = $h - $padT - $padB;

        // Y-Skala: Noten 1–6 (Schweizer System)
        $yFor = fn(float $v) => $padT + $innerH * (1 - (($v - 1) / 5));

        $n = $pts->count();
        $xFor = fn(int $i) => $n > 1 ? $padL + $innerW * ($i / ($n - 1)) : $padL + $innerW / 2;

        $coords = $pts->map(fn($p, $i) => [
            'x' => round($xFor($i), 1),
            'y' => round($yFor((float) $p->wert), 1),
            'wert' => (float) $p->wert,
            'datum' => \Carbon\Carbon::parse($p->datum)->format('d.m.Y'),
            'label' => $p->label ?? null,
        ]);

        $polyline = $coords->map(fn($c) => $c['x'] . ',' . $c['y'])->implode(' ');
        // Fläche unter der Linie (für dezenten Verlauf)
        $area = $polyline . ' ' . round($padL + $innerW, 1) . ',' . round($padT + $innerH, 1) . ' ' . $padL . ',' . round($padT + $innerH, 1);

        $dotColor = function (float $v): string {
            if ($v >= 5.0) return '#16a34a';
            if ($v >= 4.0) return '#10b981';
            if ($v >= 3.5) return '#ca8a04';
            return '#dc2626';
        };

        $firstDate = \Carbon\Carbon::parse($pts->first()->datum)->format('d.m.y');
        $lastDate  = \Carbon\Carbon::parse($pts->last()->datum)->format('d.m.y');
    @endphp

    <div class="bg-card border border-border rounded-2xl shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-border flex items-center justify-between">
            <h3 class="font-semibold text-text">{{ $title }}</h3>
            @if($subtitle)
                <span class="text-[11px] text-muted">{{ $subtitle }}</span>
            @endif
        </div>
        <div class="p-4">
            <svg viewBox="0 0 {{ $w }} {{ $h }}" class="w-full h-auto" role="img" aria-label="{{ $title }}">
                {{-- Horizontale Hilfslinien + Y-Beschriftung (Noten 1–6) --}}
                @foreach([2, 3, 4, 5, 6] as $g)
                    @php $gy = round($yFor((float) $g), 1); @endphp
                    <line x1="{{ $padL }}" y1="{{ $gy }}" x2="{{ $w - $padR }}" y2="{{ $gy }}"
                          stroke="currentColor" class="text-border" stroke-width="1"
                          @if($g === 4) stroke-dasharray="5 4" stroke-width="1.5" @endif
                          opacity="{{ $g === 4 ? '0.9' : '0.45' }}" />
                    <text x="{{ $padL - 8 }}" y="{{ $gy + 3.5 }}" text-anchor="end"
                          class="fill-current text-muted" font-size="10">{{ $g }}</text>
                @endforeach

                {{-- Fläche unter der Linie --}}
                <polygon points="{{ $area }}" class="text-accent" fill="currentColor" opacity="0.07" />

                {{-- Verlaufslinie --}}
                <polyline points="{{ $polyline }}" fill="none"
                          stroke="currentColor" class="text-accent"
                          stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />

                {{-- Punkte mit Tooltip --}}
                @foreach($coords as $c)
                    <circle cx="{{ $c['x'] }}" cy="{{ $c['y'] }}" r="3.5"
                            fill="{{ $dotColor($c['wert']) }}" stroke="white" stroke-width="1">
                        <title>{{ $c['datum'] }}{{ $c['label'] ? ' · ' . $c['label'] : '' }} — Note {{ number_format($c['wert'], 1) }}</title>
                    </circle>
                @endforeach

                {{-- X-Beschriftung: erster und letzter Termin --}}
                <text x="{{ $padL }}" y="{{ $h - 8 }}" text-anchor="start"
                      class="fill-current text-muted" font-size="10">{{ $firstDate }}</text>
                <text x="{{ $w - $padR }}" y="{{ $h - 8 }}" text-anchor="end"
                      class="fill-current text-muted" font-size="10">{{ $lastDate }}</text>
            </svg>
            <div class="mt-2 flex items-center gap-4 text-[11px] text-muted">
                <span class="inline-flex items-center gap-1.5">
                    <span class="inline-block w-2.5 h-2.5 rounded-full" style="background:#16a34a"></span> ≥ 5.0
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <span class="inline-block w-2.5 h-2.5 rounded-full" style="background:#10b981"></span> ≥ 4.0
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <span class="inline-block w-2.5 h-2.5 rounded-full" style="background:#ca8a04"></span> ≥ 3.5
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <span class="inline-block w-2.5 h-2.5 rounded-full" style="background:#dc2626"></span> &lt; 3.5
                </span>
                <span class="ml-auto hidden sm:inline">gestrichelte Linie = Note 4.0</span>
            </div>
        </div>
    </div>
@endif
