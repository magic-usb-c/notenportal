@props([
    'paket',                         // Antwort der Seite (StatistikAntwort::paket)
    'schluessel' => 'punktstreifen', // Teil des Pakets
    'id' => 'np-punktstreifen',      // Pflicht für den Ersatz nach einer Filteränderung (npFilter)
    'titel' => null,                 // null = Titel aus meta
])
{{--
    Punktstreifen (Strip Plot): ein Punkt je Person auf der Notenachse 1–6, Haarlinie bei genügend, der Median erst ab
    drei Personen (bei weniger liesse er sich auf eine Person zurückführen). Punkte, die sich berühren, rücken in eine
    zweite Reihe. Der Name steht im <title> jedes Punkts und in der Tabelle darunter. Serverseitig gezeichneter Baustein
    (npFilter ersetzt ihn nach einer Filteränderung).
--}}
@php
    $skala = \App\Support\NotenSkala::class;
    $d = $paket['diagramm'][$schluessel];
    $tabelle = $paket['tabelle'][$schluessel];
    $satz = $paket['zusammenfassung'][$schluessel];
    $titel ??= $paket['meta'][$schluessel]['titel'] ?? null;
    $punkte = $d['punkte'];
    $median = $d['median'];

    // Zeichenfläche in Einheiten; Punkte der Breite nach sortiert, jeder in die erste Reihe ohne Nachbarn in Reichweite
    $breite = 600;
    $rand = 8;
    $radius = 5;
    $schritt = 11;
    $x = fn (float $v) => round($rand + ($skala::breite($v) / 100) * ($breite - 2 * $rand), 2);
    $sortiert = collect($punkte)->sortBy('gesamt')->values();
    $reihen = [];
    $gesetzt = [];
    foreach ($sortiert as $p) {
        $px = $x((float) $p['gesamt']);
        $reihe = 0;
        while (isset($reihen[$reihe]) && $px - $reihen[$reihe] < 2 * $radius + 1) {
            $reihe++;
        }
        $reihen[$reihe] = $px;
        $gesetzt[] = ['x' => $px, 'reihe' => $reihe, 'p' => $p];
    }
    $zahlReihen = max(1, count($reihen));
    $hoehe = 2 * $radius + 6 + ($zahlReihen - 1) * $schritt;
    $y = fn (int $reihe) => $hoehe - $radius - 3 - $reihe * $schritt;
@endphp
<x-diagramm :titel="$titel" {{ $attributes->merge(['id' => $id, 'data-np-baustein' => $schluessel]) }}>
    @if($punkte === [])
        <p class="py-6 text-center text-sm text-muted">{{ $satz }}</p>
    @else
        <div class="mb-2 flex flex-wrap items-center gap-4 text-xs tabular-nums text-muted" aria-hidden="true">
            <span>{{ __('genügend :wert', ['wert' => $skala::format($d['grenze'], 1)]) }}</span>
            @if($median !== null)
                <span>{{ __('Median') }} {{ $skala::format($median, 1) }}</span>
            @endif
        </div>
        <svg class="w-full" viewBox="0 0 {{ $breite }} {{ $hoehe }}" role="group" aria-label="{{ $titel }}: {{ $satz }}">
            <line x1="{{ $rand }}" x2="{{ $breite - $rand }}" y1="{{ $hoehe - 1 }}" y2="{{ $hoehe - 1 }}" class="stroke-border" stroke-width="1"/>
            <line x1="{{ $x((float) $d['grenze']) }}" x2="{{ $x((float) $d['grenze']) }}" y1="0" y2="{{ $hoehe }}" class="stroke-muted" stroke-width="1" stroke-dasharray="3 3"/>
            @if($median !== null)
                <rect x="{{ $x((float) $median) - 1 }}" y="0" width="2" height="{{ $hoehe }}" rx="1" class="fill-chart-1"/>
            @endif
            @foreach($gesetzt as $g)
                <circle cx="{{ $g['x'] }}" cy="{{ $y($g['reihe']) }}" r="{{ $radius }}" class="{{ str_replace('bg-', 'fill-', $skala::balken($g['p']['gesamt'])) }}">
                    <title>{{ $g['p']['name'] }}, {{ $skala::format($g['p']['gesamt'], 1) }}</title>
                </circle>
            @endforeach
        </svg>
        <div class="relative mt-1 h-4 text-2xs tabular-nums text-muted" aria-hidden="true">
            @foreach([1, 2, 3, 4, 5, 6] as $note)
                <span class="absolute -translate-x-1/2" style="left: {{ round($skala::breite($note), 2) }}%">{{ $note }}</span>
            @endforeach
        </div>
        <p class="mt-2 text-xs text-muted">{{ $satz }}</p>
    @endif
    <p class="sr-only" aria-live="polite" data-np-ansage>{{ $satz }}</p>
    <x-slot:tabelle>
        <table class="np-tabelle text-sm">
            <thead>
                <tr>
                    @foreach($tabelle['spalten'] as $i => $spalte)
                        <th scope="col" @class(['text-right' => $i === 1])>{{ $spalte }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($tabelle['zeilen'] as $zeile)
                    <tr>
                        @foreach($zeile as $i => $zelle)
                            <td @class(['text-right' => $i === 1])>{{ $zelle }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-slot:tabelle>
</x-diagramm>
