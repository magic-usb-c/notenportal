@props([
    'paket',                         // Antwort der Seite (StatistikAntwort::paket)
    'schluessel' => 'punktstreifen', // Teil des Pakets
    'id' => 'np-punktstreifen',      // Pflicht für den Ersatz nach einer Filteränderung (npFilter)
    'titel' => null,                 // null = Titel aus meta
])
{{--
    Punktstreifen (Strip Plot): ein Punkt je Person auf der Notenachse 1–6, Haarlinie bei genügend, der Median erst ab
    drei Personen (bei weniger liesse er sich auf eine Person zurückführen). Punkte, die sich berühren, rücken in eine
    zweite Reihe. Der Name steht im <title> jedes Punkts und in der Tabelle darunter. Tastatur: der Streifen nimmt den
    Fokus, ←/→/Home/End führen durch die Punkte (von links nach rechts), ein Ring markiert den gewählten Punkt und die
    Ansage nennt Name, Wert und Stufe (der Absatz trägt keine Alpine-Bindung: npFilter setzt beim Ersetzen den alten Absatz
    wieder ein, die Tastatur schreibt den Text deshalb direkt hinein). Ungenügende Punkte sind hohl (Form), nicht nur rot. Ohne Personen entfällt die
    Tabelle. Serverseitig gezeichneter Baustein (npFilter ersetzt ihn nach einer Filteränderung).
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
    $hohl = fn (array $p) => $skala::stufe($p['gesamt']) === $skala::UNGENUEGEND;
    $ansagen = array_map(fn (array $g) => implode(', ', [$g['p']['name'], $skala::format($g['p']['gesamt'], 1), $skala::stufeName($g['p']['gesamt'])]), $gesetzt);
    $lagen = array_map(fn (array $g) => [$g['x'], $y($g['reihe'])], $gesetzt);
    $hatHohle = collect($gesetzt)->contains(fn (array $g) => $hohl($g['p']));
@endphp
<x-diagramm :titel="$titel" {{ $attributes->merge(['id' => $id, 'data-np-baustein' => $schluessel]) }}>
    @if($punkte === [])
        <p class="py-6 text-center text-sm text-muted">{{ $satz }}</p>
        <p class="sr-only" aria-live="polite" data-np-ansage>{{ $satz }}</p>
    @else
        <div x-data="{ i: -1, satz: @js($satz), texte: @js($ansagen), lagen: @js($lagen), gehe(k) { if (! this.texte.length) return; this.i = Math.max(0, Math.min(this.texte.length - 1, k)); this.sag(this.texte[this.i]) }, sag(text) { const p = this.$root.closest('[data-np-baustein]')?.querySelector('[data-np-ansage]'); if (p) p.textContent = text } }">
        <div class="mb-2 flex flex-wrap items-center gap-4 text-xs tabular-nums text-muted" aria-hidden="true">
            <span class="inline-flex items-center gap-1.5"><span class="w-4 border-t border-dashed border-muted"></span>{{ __('genügend :wert', ['wert' => $skala::format($d['grenze'], 1)]) }}</span>
            @if($median !== null)
                <span class="inline-flex items-center gap-1.5"><span class="h-3 w-0.5 rounded-full bg-text"></span>{{ __('Median') }} {{ $skala::format($median, 1) }}</span>
            @endif
            @if($hatHohle)
                <span class="inline-flex items-center gap-1.5 text-note-ungenuegend"><svg class="size-3" viewBox="0 0 12 12"><circle cx="6" cy="6" r="4" fill="none" stroke="currentColor" stroke-width="2"/></svg>{{ $skala::stufenNamen()[$skala::UNGENUEGEND] }}</span>
            @endif
        </div>
        <div class="rounded-lg" tabindex="0" role="group" aria-label="{{ $titel }}: {{ $satz }}"
             @keydown.arrow-right.prevent="gehe(i < 0 ? 0 : i + 1)"
             @keydown.arrow-left.prevent="gehe(i < 0 ? texte.length - 1 : i - 1)"
             @keydown.home.prevent="gehe(0)"
             @keydown.end.prevent="gehe(texte.length - 1)"
             @keydown.escape="i = -1; sag(satz)"
             @blur="i = -1">
        <svg class="w-full" viewBox="0 0 {{ $breite }} {{ $hoehe }}" aria-hidden="true">
            <line x1="{{ $rand }}" x2="{{ $breite - $rand }}" y1="{{ $hoehe - 1 }}" y2="{{ $hoehe - 1 }}" class="stroke-border" stroke-width="1"/>
            <line x1="{{ $x((float) $d['grenze']) }}" x2="{{ $x((float) $d['grenze']) }}" y1="0" y2="{{ $hoehe }}" class="stroke-muted" stroke-width="1" stroke-dasharray="3 3"/>
            @if($median !== null)
                <rect x="{{ $x((float) $median) - 1 }}" y="0" width="2" height="{{ $hoehe }}" rx="1" class="fill-text"/>
            @endif
            @foreach($gesetzt as $g)
                @if($hohl($g['p']))
                    <circle cx="{{ $g['x'] }}" cy="{{ $y($g['reihe']) }}" r="{{ $radius - 1 }}" class="text-note-ungenuegend" fill="none" stroke="currentColor" stroke-width="2" data-form="hohl">
                        <title>{{ $g['p']['name'] }}, {{ $skala::format($g['p']['gesamt'], 1) }}</title>
                    </circle>
                @else
                    <circle cx="{{ $g['x'] }}" cy="{{ $y($g['reihe']) }}" r="{{ $radius }}" class="{{ str_replace('bg-', 'fill-', $skala::balken($g['p']['gesamt'])) }}">
                        <title>{{ $g['p']['name'] }}, {{ $skala::format($g['p']['gesamt'], 1) }}</title>
                    </circle>
                @endif
            @endforeach
            <circle x-show="i >= 0" style="display: none" :cx="lagen[i]?.[0] ?? 0" :cy="lagen[i]?.[1] ?? 0" r="7" class="text-text" fill="none" stroke="currentColor" stroke-width="1.5"/>
        </svg>
        </div>
        <div class="relative mt-1 h-4 text-2xs tabular-nums text-muted" aria-hidden="true">
            @foreach([1, 2, 3, 4, 5, 6] as $note)
                <span class="absolute -translate-x-1/2" style="left: {{ round($x((float) $note) / $breite * 100, 2) }}%">{{ $note }}</span>
            @endforeach
        </div>
        <p class="mt-2 text-xs text-muted">{{ $satz }}</p>
        <p class="sr-only" aria-live="polite" data-np-ansage>{{ $satz }}</p>
        </div>
    @endif
    @if($punkte !== [])
    <x-slot:tabelle>
        <table class="np-tabelle text-sm">
            <caption class="sr-only">{{ $titel }}</caption>
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
                            @if($i === 0)
                                <th scope="row" class="text-left font-normal text-text">{{ $zelle }}</th>
                            @else
                                <td @class(['text-right' => $i === 1])>{{ $zelle }}</td>
                            @endif
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-slot:tabelle>
    @endif
</x-diagramm>
