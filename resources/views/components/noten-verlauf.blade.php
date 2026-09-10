{{-- resources/views/components/noten-verlauf.blade.php
     SVG-Diagramm Notenverlauf: Einzelnoten als Punkte auf einer Zeitachse nach Prüfungsdatum,
     dazu der gleitende Durchschnitt der letzten 5 Noten.
     Erwartet $points: Collection/Array von Objekten mit ->datum (date-string), ->wert (float), ->label (string|null) --}}
@props([
    'points',
    'title' => 'Notenverlauf',
    'subtitle' => null,
])

@php
    $pts = collect($points)->sortBy('datum')->values();
@endphp

@if($pts->count() >= 2)
    @php
        $w = 600; $h = 170;
        $padL = 30; $padR = 14; $padT = 14; $padB = 26;
        $innerW = $w - $padL - $padR;
        $innerH = $h - $padT - $padB;

        // Y: volle Notenskala 1–6, nie abgeschnitten
        $yFor = fn (float $v) => $padT + $innerH * (1 - (($v - 1) / 5));

        // X: proportional zum Prüfungsdatum
        $zeit = $pts->map(fn ($p) => \Carbon\Carbon::parse($p->datum)->getTimestamp());
        $tMin = $zeit->min();
        $tSpanne = max(1, $zeit->max() - $tMin);
        $xFor = fn (int $t) => $padL + $innerW * (($t - $tMin) / $tSpanne);

        $farbe = fn (float $v) => match (true) {
            $v >= 5.0 => 'fill-green-600 dark:fill-green-400',
            $v >= 4.0 => 'fill-emerald-600 dark:fill-emerald-400',
            $v >= 3.5 => 'fill-yellow-600 dark:fill-yellow-400',
            default => 'fill-red-600 dark:fill-red-400',
        };

        $coords = $pts->map(function ($p, $i) use ($pts, $zeit, $xFor, $yFor, $farbe) {
            $fenster = $pts->slice(max(0, $i - 4), min(5, $i + 1));

            return [
                'x' => round($xFor($zeit[$i]), 1),
                'y' => round($yFor((float) $p->wert), 1),
                'yGleitend' => round($yFor((float) $fenster->avg(fn ($f) => (float) $f->wert)), 1),
                'wert' => (float) $p->wert,
                'farbe' => $farbe((float) $p->wert),
                'datum' => \Carbon\Carbon::parse($p->datum)->format('d.m.Y'),
                'label' => $p->label ?? null,
            ];
        });

        $gleitend = $coords->map(fn ($c) => $c['x'].','.$c['yGleitend'])->implode(' ');
        $firstDate = \Carbon\Carbon::parse($pts->first()->datum)->format('d.m.y');
        $lastDate = \Carbon\Carbon::parse($pts->last()->datum)->format('d.m.y');
    @endphp

    <div class="glass rounded-2xl overflow-hidden">
        <div class="px-5 py-4 border-b border-border flex items-center justify-between">
            <h3 class="font-semibold text-text">{{ $title }}</h3>
            @if($subtitle)
                <span class="text-[11px] text-muted">{{ $subtitle }}</span>
            @endif
        </div>
        <div class="p-4">
            <svg viewBox="0 0 {{ $w }} {{ $h }}" class="w-full h-auto" role="img" aria-label="{{ $title }}">
                @foreach([1, 2, 3, 4, 5, 6] as $g)
                    @php $gy = round($yFor((float) $g), 1); @endphp
                    <line x1="{{ $padL }}" y1="{{ $gy }}" x2="{{ $w - $padR }}" y2="{{ $gy }}"
                          stroke="currentColor" class="{{ $g === 4 ? 'text-muted' : 'text-border' }}"
                          stroke-width="{{ $g === 4 ? '1.5' : '1' }}"
                          @if($g === 4) stroke-dasharray="5 4" @endif
                          opacity="{{ $g === 4 ? '0.8' : '0.45' }}" />
                    <text x="{{ $padL - 8 }}" y="{{ $gy + 3.5 }}" text-anchor="end"
                          class="fill-current text-muted" font-size="10">{{ $g }}</text>
                @endforeach

                <polyline points="{{ $gleitend }}" fill="none"
                          stroke="currentColor" class="text-accent"
                          stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />

                @foreach($coords as $c)
                    <circle cx="{{ $c['x'] }}" cy="{{ $c['y'] }}" r="3.5"
                            class="{{ $c['farbe'] }} stroke-card" stroke-width="1">
                        <title>{{ $c['datum'] }}{{ $c['label'] ? ' · '.$c['label'] : '' }} — Note {{ $c['wert'] }}</title>
                    </circle>
                @endforeach

                <text x="{{ $padL }}" y="{{ $h - 8 }}" text-anchor="start"
                      class="fill-current text-muted" font-size="10">{{ $firstDate }}</text>
                <text x="{{ $w - $padR }}" y="{{ $h - 8 }}" text-anchor="end"
                      class="fill-current text-muted" font-size="10">{{ $lastDate }}</text>
            </svg>
            <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] text-muted">
                <span class="inline-flex items-center gap-1.5">
                    <span class="inline-block w-4 h-0.5 rounded-full bg-accent"></span> Ø der letzten 5 Noten
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <span class="inline-block w-2.5 h-2.5 rounded-full bg-green-600 dark:bg-green-400"></span> ≥ 5.0
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <span class="inline-block w-2.5 h-2.5 rounded-full bg-emerald-600 dark:bg-emerald-400"></span> ≥ 4.0
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <span class="inline-block w-2.5 h-2.5 rounded-full bg-yellow-600 dark:bg-yellow-400"></span> ≥ 3.5
                </span>
                <span class="inline-flex items-center gap-1.5">
                    <span class="inline-block w-2.5 h-2.5 rounded-full bg-red-600 dark:bg-red-400"></span> &lt; 3.5
                </span>
            </div>
        </div>
    </div>
@endif
