@props([
    'paket',                  // Antwort der Seite (StatistikAntwort::paket)
    'schluessel' => 'verlauf',// Teil des Pakets
    'titel' => null,          // null = Titel aus meta
    'hoehe' => 'h-64',
])
{{--
    Notenverlauf als Hülle um das Liniendiagramm (npChart «verlauf»): Zusammenfassung, Tabelle und die Liste der Positionen
    ohne Datum (IPA, Schlussprüfung) stehen serverseitig da. npVerlauf (resources/js/statistik.js) übernimmt bei jedem
    «np-statistik» des Filterformulars Diagramm, Satz, Tabelle und Liste, ohne die Seite zu laden.
--}}
@php
    $skala = \App\Support\NotenSkala::class;
    $d = $paket['diagramm'][$schluessel];
    $tabelle = $paket['tabelle'][$schluessel];
    $satz = $paket['zusammenfassung'][$schluessel];
    $meta = $paket['meta'][$schluessel] ?? [];
    $titel ??= $meta['titel'] ?? null;
    $ohneDatum = $meta['ohneDatum'] ?? [];
@endphp
<x-diagramm :titel="$titel" {{ $attributes->merge(['x-data' => 'npVerlauf(\''.$schluessel.'\')', '@np-statistik.window' => 'aktualisiere($event.detail)']) }}>
    <div x-data="npChart('verlauf', @js($d))" data-np-verlauf-chart tabindex="0" role="group" aria-label="{{ $titel }}" class="rounded-lg">
        <div class="relative {{ $hoehe }}">
            <canvas x-ref="canvas" role="img" aria-label="{{ $satz }}"></canvas>
            <div class="np-diagramm-tipp glass-overlay absolute pointer-events-none z-20 px-3 py-2 text-xs rounded-xl text-text whitespace-nowrap transition-opacity duration-100" style="opacity: 0" aria-hidden="true"></div>
        </div>
        <p class="np-diagramm-ansage sr-only" aria-live="polite"></p>
    </div>
    <p class="mt-3 text-xs text-muted" data-np-satz>{{ $satz }}</p>
    <ul class="{{ $ohneDatum === [] ? 'hidden' : 'mt-2 flex flex-wrap gap-x-4 gap-y-1' }} text-xs text-muted" data-np-ohne-datum role="list">
        @foreach($ohneDatum as $p)
            <li><span class="text-text">{{ $p['titel'] }}</span> <span class="tabular-nums">{{ $skala::format($p['wert'], 1) }}</span></li>
        @endforeach
    </ul>
    <x-slot:tabelle>
        <table class="np-tabelle text-sm" data-np-tabelle>
            <thead>
                <tr>
                    @foreach($tabelle['spalten'] as $i => $spalte)
                        <th scope="col" @class(['text-right' => $i === count($tabelle['spalten']) - 1])>{{ $spalte }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($tabelle['zeilen'] as $zeile)
                    <tr>
                        @foreach($zeile as $i => $zelle)
                            <td @class(['text-right' => $i === count($zeile) - 1])>{{ $zelle }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-slot:tabelle>
</x-diagramm>
