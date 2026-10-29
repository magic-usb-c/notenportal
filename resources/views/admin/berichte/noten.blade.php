{{-- Notenbericht: Kennzahlen, Verteilung in Viertelnoten (S10) und Lehrjahre im Vergleich (S11), Kategorien, tiefste Fächer und
     Module, dann alle Lernenden. Die Filter gelten sofort (npFilter): Verteilung und Lehrjahre sind serverseitig gezeichnete
     Bausteine, Kennzahlen, Kategorien, Fächer und Lernende werden gezielt ersetzt. Die Zeile öffnet das Profil, «Noten» die
     Notenliste im gewählten Zeitraum. --}}
@use('App\Models\Semester')
@use('App\Support\NotenSkala')
@use('Illuminate\Support\Carbon')
@php
    $sid = $filter['semester_id'];
    $semesterName = $sid ? (Semester::neutralerName($semester->firstWhere('semester_id', $sid)?->start_datum) ?? __('Semester')) : __('Ganze Lehrzeit');
    $sortUrl = fn (string $spalte, string $start = 'asc') => request()->fullUrlWithQuery([
        'sort' => $spalte,
        'dir' => $sort === $spalte ? ($dir === 'asc' ? 'desc' : 'asc') : $start,
    ]);
    // Richtung für Screenreader über aria-sort am Spaltenkopf, der Pfeil ist nur fürs Auge
    $pfeil = fn (string $spalte) => $sort === $spalte ? new \Illuminate\Support\HtmlString('<span aria-hidden="true">'.($dir === 'asc' ? '↑' : '↓').'</span>') : '';
    $ariaSort = fn (string $spalte) => $sort === $spalte ? ($dir === 'desc' ? 'descending' : 'ascending') : 'none';
    $filterParameter = request()->only(['semester', 'lehrberuf_id', 'berufsbildner_id', 'kategorie', 'lehrjahr']);
    $aktiveFilter = count(array_filter([$filter['lehrberuf_id'], $filter['berufsbildner_id'], $filter['kategorie_id'], $filter['lehrjahr'], request()->filled('semester') ? 1 : null]));
    $kategorieAuswahl = [];
    $konfiguration = \App\Services\Auswertung\Konfiguration::ausDb();
    foreach (array_keys($konfiguration->kategorien) as $kategorieId) {
        $kategorieAuswahl[(int) $kategorieId] = $konfiguration->kategorieName((int) $kategorieId);
    }
    $auswahl = 'np-feld np-feld-klein w-auto max-w-72';
    $k = $kennzahlen;
    $spalten = $sid ? 8 : 7;
    $kopf = 'inline-flex min-h-6 items-center gap-1 hover:text-text';
    // «Kritisch» öffnet die Lernendenliste mit demselben Lehrberuf und Berufsbildner, «Ungenügend» sortiert die Tabelle unten
    $kritischUrl = route('admin.learners.index', array_filter(['warnung' => 'kritisch', 'lehrberuf_id' => $filter['lehrberuf_id'], 'berufsbildner_id' => $filter['berufsbildner_id']]));
    $ungenuegendUrl = $sort === 'ungenuegend' && $dir === 'desc' ? '#lernende' : request()->fullUrlWithQuery(['sort' => 'ungenuegend', 'dir' => 'desc']).'#lernende';
@endphp
<x-app-layout>
    <x-slot name="title">{{ __('Berichte') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :titel="__('Berichte')" :untertitel="$semesterName">
            <x-slot:aktionen>
                <button type="button" x-data x-on:click="window.print()" class="np-knopf np-knopf-sekundaer print:hidden">{{ __('Drucken') }}</button>
                <a href="{{ route('admin.reports.grades.export', $filterParameter) }}" data-behalte-filter class="np-knopf np-knopf-sekundaer print:hidden">
                    <x-symbol name="arrow-down-tray" />{{ __('Exportieren') }}
                </a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="np-seite mx-auto flex flex-col gap-6 px-8">
            <div class="print:hidden">
                <x-filterleiste :action="route('admin.reports.grades')" :aktive-filter="$aktiveFilter" :zurueck="route('admin.reports.grades')"
                                :statistik="['json' => false, 'ersetze' => ['[data-np-filterstatus]', '[data-np-kennzahlen]', '[data-np-kategorien]', '[data-np-schwach]', '#lernende']]">
                    <x-slot:hidden>
                        <input type="hidden" name="sort" value="{{ $sort }}">
                        <input type="hidden" name="dir" value="{{ $dir }}">
                    </x-slot:hidden>
                    <label for="semester" class="sr-only">{{ __('Zeitraum') }}</label>
                    <select name="semester" id="semester" x-on:change="$el.form.requestSubmit()" class="{{ $auswahl }}">
                        <option value="alle" @selected($sid === null)>{{ __('Ganze Lehrzeit') }}</option>
                        @foreach($semester as $s)
                            <option value="{{ $s->semester_id }}" @selected($sid === (int) $s->semester_id)>{{ Semester::neutralerName($s->start_datum) ?? $s->bezeichnung }}</option>
                        @endforeach
                    </select>
                    <label for="lehrberuf_id" class="sr-only">{{ __('Lehrberuf') }}</label>
                    <select name="lehrberuf_id" id="lehrberuf_id" x-on:change="$el.form.requestSubmit()" class="{{ $auswahl }}">
                        <option value="">{{ __('Alle Lehrberufe') }}</option>
                        @foreach($lehrberufe as $lb)
                            <option value="{{ $lb->lehrberuf_id }}" @selected($filter['lehrberuf_id'] === (int) $lb->lehrberuf_id)>{{ $lb->name }}</option>
                        @endforeach
                    </select>
                    <label for="berufsbildner_id" class="sr-only">{{ __('Berufsbildner') }}</label>
                    <select name="berufsbildner_id" id="berufsbildner_id" x-on:change="$el.form.requestSubmit()" class="{{ $auswahl }}">
                        <option value="">{{ __('Alle Berufsbildner') }}</option>
                        @foreach($berufsbildner as $bb)
                            <option value="{{ $bb->berufsbildner_id }}" @selected($filter['berufsbildner_id'] === (int) $bb->berufsbildner_id)>{{ $bb->nachname }} {{ $bb->vorname }}</option>
                        @endforeach
                    </select>
                    <label for="kategorie" class="sr-only">{{ __('Kategorie') }}</label>
                    <select name="kategorie" id="kategorie" x-on:change="$el.form.requestSubmit()" class="{{ $auswahl }}">
                        <option value="">{{ __('Alle Kategorien') }}</option>
                        @foreach($kategorieAuswahl as $id => $text)
                            <option value="{{ $id }}" @selected($filter['kategorie_id'] === $id)>{{ $text }}</option>
                        @endforeach
                    </select>
                    <label for="lehrjahr" class="sr-only">{{ __('Lehrjahr') }}</label>
                    <select name="lehrjahr" id="lehrjahr" x-on:change="$el.form.requestSubmit()" class="{{ $auswahl }}">
                        <option value="">{{ __('Alle Lehrjahre') }}</option>
                        @foreach([1, 2, 3, 4] as $jahr)
                            <option value="{{ $jahr }}" @selected($filter['lehrjahr'] === $jahr)>{{ __(':jahr. Lehrjahr', ['jahr' => $jahr]) }}</option>
                        @endforeach
                    </select>
                </x-filterleiste>
            </div>

            <div @class(['grid gap-4', 'grid-cols-5' => $sid, 'grid-cols-4' => ! $sid]) data-np-kennzahlen>
                <x-kachel :label="__('Lernende')" :wert="$k['lernende']" />
                <x-kachel :label="__('Ø Gesamtnote')" :note="$k['schnitt']" />
                <x-kachel :label="__('Kritisch')" :wert="$k['rot']" :ton="$k['rot'] ? 'rot' : 'neutral'" :sub="__(':gelb beobachten', ['gelb' => $k['gelb']])" :href="$kritischUrl" />
                <x-kachel :label="__('Ungenügende Zeugnisnoten')" :wert="$k['ungenuegend']" :ton="$k['ungenuegend'] ? 'rot' : 'neutral'" :sub="__('von :n', ['n' => $k['zeugnisnoten']])" :href="$ungenuegendUrl" />
                @if($sid)
                    <x-kachel :label="__('Promotion gefährdet')" :wert="$k['gefaehrdet']" :ton="$k['gefaehrdet'] ? 'rot' : 'neutral'" />
                @endif
            </div>

            {{-- Verteilung (S10) und Lehrjahre im Vergleich (S11): die Bausteine stehen immer da, auch ohne Noten, damit npFilter sie ersetzen kann --}}
            @php
                $hist = $statistik['diagramm']['histogramm'];
                $histTabelle = $statistik['tabelle']['histogramm'];
                $histSatz = $statistik['zusammenfassung']['histogramm'];
                $jahre = $statistik['diagramm']['lehrjahre']['jahre'];
                $jahreTabelle = $statistik['tabelle']['lehrjahre'];
                $jahreSatz = $statistik['zusammenfassung']['lehrjahre'];
                $grenze = (float) $statistik['diagramm']['lehrjahre']['grenze'];
                $zahl = fn (?float $v, int $stellen = 1) => NotenSkala::format($v, $stellen);
            @endphp
            <div class="grid grid-cols-2 items-start gap-4">
                <x-diagramm :titel="__('Verteilung der Zeugnisnoten')" id="np-histogramm" data-np-baustein="histogramm">
                    @if($hist['n'] === 0)
                        <p class="py-6 text-center text-sm text-muted" id="np-histogramm-satz" aria-live="polite" data-np-ansage>{{ $histSatz }}</p>
                    @else
                        @php
                            // Klassen in Viertelnoten auf der Notenachse 1–6; die Achse beginnt bei 0 Zeugnisnoten
                            $klassen = count($hist['werte']);
                            $spitze = max([1, ...$hist['werte']]);
                            $schritt = 1;
                            foreach ([1, 2, 5, 10, 20, 50, 100, 200, 500, 1000, 2000, 5000] as $kandidat) {
                                $schritt = $kandidat;
                                if ($spitze / $kandidat <= 4) {
                                    break;
                                }
                            }
                            $oben = (int) (ceil($spitze / $schritt) * $schritt);
                            $teilstriche = range(0, $oben, $schritt);
                            $breite = 600;
                            $hoehe = 200;
                            $achsenrand = 30;
                            $kopfrand = 8;
                            $achse = $breite - $achsenrand - 4;
                            // Die Klassen lückenlos von der ersten Untergrenze (1.0) bis 6.0
                            $notenVon = (float) $hist['farben'][0];
                            $notenBis = $notenVon + $klassen * $hist['breite'];
                            $x = fn (float $note) => round($achsenrand + ($note - $notenVon) / ($notenBis - $notenVon) * $achse, 2);
                            $y = fn (float|int $v) => round($hoehe - ($v / $oben) * ($hoehe - $kopfrand), 2);
                            $platz = $achse / $klassen;
                        @endphp
                        <div class="mb-2 flex flex-wrap items-center gap-4 text-xs tabular-nums text-muted" aria-hidden="true">
                            <span class="inline-flex items-center gap-2">
                                <svg class="w-4" viewBox="0 0 16 2"><line x1="0" x2="16" y1="1" y2="1" class="stroke-muted" stroke-width="1.5" stroke-dasharray="3 3"/></svg>
                                {{ __('Genügend :wert', ['wert' => $zahl($grenze)]) }}
                            </span>
                            @if($hist['median'] !== null)
                                <span class="inline-flex items-center gap-2">
                                    <svg class="w-4" viewBox="0 0 16 2"><line x1="0" x2="16" y1="1" y2="1" class="stroke-text" stroke-width="1.5"/></svg>
                                    {{ __('Median') }} {{ $zahl($hist['median'], 2) }}
                                </span>
                            @endif
                        </div>
                        <div class="relative">
                            <svg class="block w-full" viewBox="0 0 {{ $breite }} {{ $hoehe }}" role="img" aria-label="{{ __('Verteilung der Zeugnisnoten') }}" aria-describedby="np-histogramm-satz">
                                @foreach($teilstriche as $strich)
                                    <line x1="{{ $achsenrand }}" x2="{{ $breite }}" y1="{{ $y($strich) }}" y2="{{ $y($strich) }}" @class(['stroke-border-strong' => $strich === 0, 'stroke-border' => $strich !== 0]) stroke-width="1"/>
                                @endforeach
                                @foreach($hist['werte'] as $i => $anzahl)
                                    @php
                                        $von = (float) $hist['farben'][$i];
                                        $bis = $von + $hist['breite'];
                                        $letzte = $i === $klassen - 1;
                                    @endphp
                                    <g>
                                        <title>{{ number_format($von, 2, '.', '') }}–{{ number_format($letzte ? $bis : $bis - 0.01, 2, '.', '') }}: {{ $anzahl === 1 ? __('1 Zeugnisnote') : __(':anzahl Zeugnisnoten', ['anzahl' => $anzahl]) }}</title>
                                        <rect x="{{ $x($von) }}" y="0" width="{{ round($platz, 2) }}" height="{{ $hoehe }}" class="fill-transparent"/>
                                        @if($anzahl > 0)
                                            <rect x="{{ round($x($von) + 0.75, 2) }}" y="{{ $y($anzahl) }}" width="{{ round($platz - 1.5, 2) }}" height="{{ round($hoehe - $y($anzahl), 2) }}" rx="2" class="{{ str_replace('bg-', 'fill-', NotenSkala::balken($von)) }}"/>
                                        @endif
                                    </g>
                                @endforeach
                                <line x1="{{ $x($grenze) }}" x2="{{ $x($grenze) }}" y1="0" y2="{{ $hoehe }}" class="stroke-muted" stroke-width="1.5" stroke-dasharray="3 3"/>
                                @if($hist['median'] !== null)
                                    <line x1="{{ $x((float) $hist['median']) }}" x2="{{ $x((float) $hist['median']) }}" y1="0" y2="{{ $hoehe }}" class="stroke-text" stroke-width="1.5"/>
                                @endif
                            </svg>
                            <div class="pointer-events-none absolute inset-0" aria-hidden="true">
                                @foreach($teilstriche as $strich)
                                    <span class="absolute left-0 -translate-y-1/2 text-2xs tabular-nums text-muted" style="top: {{ round($y($strich) / $hoehe * 100, 2) }}%">{{ $strich }}</span>
                                @endforeach
                            </div>
                        </div>
                        <div class="relative mt-1 h-4 text-2xs tabular-nums text-muted" aria-hidden="true">
                            @foreach([1, 2, 3, 4, 5, 6] as $note)
                                <span class="absolute -translate-x-1/2" style="left: {{ round($x((float) $note) / $breite * 100, 2) }}%">{{ $note }}</span>
                            @endforeach
                        </div>
                        <x-noten-legende class="mt-3" />
                        <p class="mt-2 text-xs text-muted" id="np-histogramm-satz" aria-live="polite" data-np-ansage>{{ $histSatz }}</p>
                    @endif
                    <x-slot:tabelle>
                        <table class="np-tabelle text-sm">
                            <thead>
                                <tr>
                                    @foreach($histTabelle['spalten'] as $i => $spalte)
                                        <th scope="col" @class(['text-right' => $i > 0])>{{ $spalte }}</th>
                                    @endforeach
                                    <th scope="col" class="text-right">{{ __('Stufe') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($histTabelle['zeilen'] as $zeile)
                                    <tr>
                                        <td>{{ $zeile[0] }}</td>
                                        <td class="text-right tabular-nums">{{ $zeile[1] }}</td>
                                        <td class="text-right text-muted">{{ NotenSkala::stufeName((float) $zeile[0]) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </x-slot:tabelle>
                </x-diagramm>

                <div class="flex flex-col gap-4">
                    <div class="contents" data-np-kategorien>
                        @if($k['zeugnisnoten'] > 0)
                                <x-karte :titel="__('Kategorien')" :polster="false">
                                    <div class="px-2 pb-2">
                                        <table class="np-tabelle table-fixed text-sm">
                                            <thead>
                                                <tr>
                                                    <th scope="col">{{ __('Kategorie') }}</th>
                                                    <th scope="col" class="w-24 text-right">{{ __('Lernende') }}</th>
                                                    <th scope="col" class="w-16 text-right">Ø</th>
                                                    <th scope="col" class="w-28 text-right">{{ __('Min – Max') }}</th>
                                                    <th scope="col" class="w-28 text-right">{{ __('Ungenügend') }}</th>
                                                    @if($sid)<th scope="col" class="w-28 text-right">{{ __('Gefährdet') }}</th>@endif
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($kategorien as $kat)
                                                    <tr>
                                                        <td class="truncate font-medium text-text">{{ $kat['name'] }}</td>
                                                        <td class="text-right text-muted">{{ $kat['anzahl'] }}</td>
                                                        <td class="text-right font-semibold {{ NotenSkala::text($kat['schnitt']) }}">{{ NotenSkala::format($kat['schnitt'], 2) }}</td>
                                                        <td class="whitespace-nowrap text-right text-muted">{{ NotenSkala::format($kat['min'], 1) }} – {{ NotenSkala::format($kat['max'], 1) }}</td>
                                                        <td @class(['text-right', 'font-semibold text-note-ungenuegend' => $kat['ungenuegend'], 'text-muted' => ! $kat['ungenuegend']])>{{ $kat['ungenuegend'] }}</td>
                                                        @if($sid)
                                                            <td @class(['text-right', 'font-semibold text-note-ungenuegend' => $kat['gefaehrdet'], 'text-muted' => ! $kat['gefaehrdet']])>{{ $kat['gefaehrdet'] }}</td>
                                                        @endif
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </x-karte>
                        @endif
                    </div>

                    {{-- Lehrjahre im Vergleich: ein Streifen je Lehrjahr auf derselben Notenachse 1–6, ein Punkt je Person, Median ab drei Personen --}}
                    <div class="contents" id="np-lehrjahre" data-np-baustein="lehrjahre">
                        @if(count($jahre) > 1)
                            @php
                                $breite = 600;
                                $rand = 8;
                                $radius = 5;
                                $schritt = 11;
                                $px = fn (float $note) => round($rand + ($note - 1) / 5 * ($breite - 2 * $rand), 2);
                            @endphp
                            <x-diagramm :titel="__('Gesamtschnitt nach Lehrjahr')">
                                <div class="mb-2 flex flex-wrap items-center gap-4 text-xs tabular-nums text-muted" aria-hidden="true">
                                    <span class="inline-flex items-center gap-2">
                                        <svg class="w-4" viewBox="0 0 16 2"><line x1="0" x2="16" y1="1" y2="1" class="stroke-muted" stroke-width="1.5" stroke-dasharray="3 3"/></svg>
                                        {{ __('Genügend :wert', ['wert' => $zahl($grenze)]) }}
                                    </span>
                                    <span class="inline-flex items-center gap-2">
                                        <svg class="w-4" viewBox="0 0 16 2"><line x1="0" x2="16" y1="1" y2="1" class="stroke-text" stroke-width="1.5"/></svg>
                                        {{ __('Median') }}
                                    </span>
                                </div>
                                <ul class="flex flex-col" role="list">
                                    <li class="flex items-center gap-3" aria-hidden="true">
                                        <span class="w-28 shrink-0"></span>
                                        <span class="min-w-0 flex-1"></span>
                                        <span class="w-12 shrink-0 text-right text-2xs text-muted">Ø</span>
                                    </li>
                                    @foreach($jahre as $j)
                                        @php
                                            // Punkte der Note nach sortiert, jeder in die erste Reihe ohne Nachbarn in Reichweite
                                            $reihen = [];
                                            $gesetzt = [];
                                            foreach (collect($j['werte'])->sort()->values() as $wert) {
                                                $cx = $px((float) $wert);
                                                $reihe = 0;
                                                while (isset($reihen[$reihe]) && $cx - $reihen[$reihe] < 2 * $radius + 1) {
                                                    $reihe++;
                                                }
                                                $reihen[$reihe] = $cx;
                                                $gesetzt[] = ['x' => $cx, 'reihe' => $reihe, 'wert' => (float) $wert];
                                            }
                                            $hoeheJahr = 2 * $radius + 6 + (max(1, count($reihen)) - 1) * $schritt;
                                        @endphp
                                        <li class="flex items-center gap-3 py-1.5">
                                            <span class="w-28 shrink-0 text-sm text-text">
                                                {{ __(':jahr. Lehrjahr', ['jahr' => $j['jahr']]) }}
                                                <span class="block text-xs text-muted">{{ $j['anzahl'] === 1 ? __('1 Person') : __(':anzahl Personen', ['anzahl' => $j['anzahl']]) }}</span>
                                            </span>
                                            <svg class="min-w-0 flex-1" viewBox="0 0 {{ $breite }} {{ $hoeheJahr }}" aria-hidden="true">
                                                <line x1="{{ $rand }}" x2="{{ $breite - $rand }}" y1="{{ $hoeheJahr - 1 }}" y2="{{ $hoeheJahr - 1 }}" class="stroke-border" stroke-width="1"/>
                                                <line x1="{{ $px($grenze) }}" x2="{{ $px($grenze) }}" y1="0" y2="{{ $hoeheJahr }}" class="stroke-muted" stroke-width="1.5" stroke-dasharray="3 3"/>
                                                @foreach($gesetzt as $g)
                                                    <circle cx="{{ $g['x'] }}" cy="{{ $hoeheJahr - $radius - 3 - $g['reihe'] * $schritt }}" r="{{ $radius }}" class="{{ str_replace('bg-', 'fill-', NotenSkala::balken($g['wert'])) }} stroke-card" stroke-width="1">
                                                        <title>{{ $zahl($g['wert']) }}</title>
                                                    </circle>
                                                @endforeach
                                                {{-- Median über den Punkten, damit er nicht hinter einem Punkt verschwindet --}}
                                                @if($j['median'] !== null)
                                                    <line x1="{{ $px((float) $j['median']) }}" x2="{{ $px((float) $j['median']) }}" y1="0" y2="{{ $hoeheJahr }}" class="stroke-text" stroke-width="1.5"/>
                                                @endif
                                            </svg>
                                            <span class="w-12 shrink-0 text-right text-sm font-semibold tabular-nums {{ NotenSkala::text($j['schnitt']) }}"><span class="sr-only">Ø </span>{{ $zahl($j['schnitt']) }}</span>
                                        </li>
                                    @endforeach
                                    <li class="flex items-center gap-3" aria-hidden="true">
                                        <span class="w-28 shrink-0"></span>
                                        <div class="relative h-4 min-w-0 flex-1 text-2xs tabular-nums text-muted">
                                            @foreach([1, 2, 3, 4, 5, 6] as $note)
                                                <span class="absolute -translate-x-1/2" style="left: {{ round($px((float) $note) / $breite * 100, 2) }}%">{{ $note }}</span>
                                            @endforeach
                                        </div>
                                        <span class="w-12 shrink-0"></span>
                                    </li>
                                </ul>
                                <p class="mt-3 text-xs text-muted" aria-live="polite" data-np-ansage>{{ $jahreSatz }}</p>
                                <x-slot:tabelle>
                                    <table class="np-tabelle text-sm">
                                        <thead>
                                            <tr>
                                                @foreach($jahreTabelle['spalten'] as $i => $spalte)
                                                    <th scope="col" @class(['text-right' => $i > 0])>{{ $spalte }}</th>
                                                @endforeach
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($jahreTabelle['zeilen'] as $zeile)
                                                <tr>
                                                    @foreach($zeile as $i => $zelle)
                                                        <td @class(['text-right tabular-nums' => $i > 0])>{{ $zelle }}</td>
                                                    @endforeach
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </x-slot:tabelle>
                            </x-diagramm>
                        @else
                            {{-- Ansage auch ohne Streifen, damit ein Filterwechsel auf ein einziges Lehrjahr gesprochen wird --}}
                            <p class="sr-only" aria-live="polite" data-np-ansage>{{ $jahreSatz }}</p>
                        @endif
                    </div>
                </div>
            </div>

            <div class="contents" data-np-schwach>
                @if($k['zeugnisnoten'] > 0)
                    <x-karte :titel="__('Tiefste Fächer und Module')">
                        <x-noten-legende class="mb-4" />
                        <ul class="grid grid-cols-2 gap-x-10 gap-y-4">
                            @foreach($schwachstellen as $s)
                                @php $breite = max(2, min(100, ($s['schnitt'] - 1) / 5 * 100)); @endphp
                                <li class="flex min-w-0 flex-col gap-1.5">
                                    <div class="flex items-baseline justify-between gap-3 text-sm">
                                        <span class="min-w-0 truncate font-medium text-text">{{ $s['label'] }} <span class="text-xs font-normal text-muted">{{ $s['kategorie'] }}</span></span>
                                        <span class="font-semibold tabular-nums {{ NotenSkala::text($s['schnitt']) }}">{{ NotenSkala::format($s['schnitt'], 2) }}</span>
                                    </div>
                                    <div class="h-1.5 overflow-hidden rounded-full bg-fill"><div class="h-full rounded-full {{ NotenSkala::balken($s['schnitt']) }}" style="width: {{ $breite }}%"></div></div>
                                    <div class="text-xs text-muted">{{ $s['anzahl'] === 1 ? __('1 Zeugnisnote') : __(':anzahl Zeugnisnoten', ['anzahl' => $s['anzahl']]) }}@if($s['ungenuegend']) · <span class="text-note-ungenuegend">{{ __(':anzahl ungenügend', ['anzahl' => $s['ungenuegend']]) }}</span>@endif</div>
                                </li>
                            @endforeach
                        </ul>
                    </x-karte>
                @endif
            </div>

            <x-karte id="lernende" :titel="__('Lernende')" :polster="false">
                <div class="px-2 pb-2">
                    <table class="np-tabelle table-fixed text-sm">
                        <thead>
                            <tr>
                                <th scope="col" class="w-72" aria-sort="{{ $ariaSort('name') }}"><a href="{{ $sortUrl('name') }}" class="{{ $kopf }}">{{ __('Name') }} {{ $pfeil('name') }}</a></th>
                                <th scope="col" aria-sort="{{ $ariaSort('status') }}"><a href="{{ $sortUrl('status') }}" class="{{ $kopf }}">{{ __('Status') }} {{ $pfeil('status') }}</a></th>
                                <th scope="col" class="w-24 text-right" aria-sort="{{ $ariaSort('gesamt') }}"><a href="{{ $sortUrl('gesamt', 'desc') }}" class="{{ $kopf }}">{{ __('Gesamt') }} {{ $pfeil('gesamt') }}</a></th>
                                @if($sid)
                                    <th scope="col" class="w-28 text-right" aria-sort="{{ $ariaSort('semester') }}"><a href="{{ $sortUrl('semester', 'desc') }}" class="{{ $kopf }}">{{ __('Semester') }} {{ $pfeil('semester') }}</a></th>
                                @endif
                                <th scope="col" class="w-32 text-right" aria-sort="{{ $ariaSort('ungenuegend') }}"><a href="{{ $sortUrl('ungenuegend', 'desc') }}" class="{{ $kopf }}">{{ __('Ungenügend') }} {{ $pfeil('ungenuegend') }}</a></th>
                                <th scope="col" class="w-28 text-right" aria-sort="{{ $ariaSort('pruefungen') }}"><a href="{{ $sortUrl('pruefungen', 'desc') }}" class="{{ $kopf }}">{{ __('Prüfungen') }} {{ $pfeil('pruefungen') }}</a></th>
                                <th scope="col" class="w-32 text-right" aria-sort="{{ $ariaSort('letzte') }}"><a href="{{ $sortUrl('letzte', 'desc') }}" class="{{ $kopf }}">{{ __('Letzte Note') }} {{ $pfeil('letzte') }}</a></th>
                                <th scope="col" class="w-24 print:hidden"><span class="sr-only">{{ __('Aktionen') }}</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($zeilen as $z)
                                @php $name = $z->nachname.' '.$z->vorname; @endphp
                                <tr data-href="{{ route('admin.learners.show', $z->id) }}">
                                    <td>
                                        <div class="flex min-w-0 items-baseline gap-2">
                                            <a href="{{ route('admin.learners.show', $z->id) }}" class="truncate font-medium text-text">{{ $name }}</a>
                                            @if($z->lehrberuf)<span class="shrink-0 text-xs text-muted">{{ $z->lehrberuf }}</span>@endif
                                        </div>
                                    </td>
                                    <td>
                                        <x-status :status="$z->stand->status" />
                                        @if($z->stand->gruende)
                                            <div class="mt-0.5 text-xs text-muted">{{ implode(' · ', $z->stand->gruende) }}</div>
                                        @endif
                                    </td>
                                    <td class="text-right font-semibold {{ NotenSkala::text($z->gesamt) }}">{{ NotenSkala::format($z->gesamt, 1) }}</td>
                                    @if($sid)
                                        <td class="text-right font-semibold {{ NotenSkala::text($z->semester) }}">{{ NotenSkala::format($z->semester, 1) }}</td>
                                    @endif
                                    <td @class(['text-right', 'font-semibold text-note-ungenuegend' => $z->ungenuegend, 'text-muted' => ! $z->ungenuegend])>{{ $z->ungenuegend }}</td>
                                    <td class="text-right text-muted">{{ $z->pruefungen }}</td>
                                    <td class="whitespace-nowrap text-right text-muted">{{ $z->letzte ? Carbon::parse($z->letzte)->format('d.m.Y') : '–' }}</td>
                                    <td class="text-right print:hidden">
                                        <x-zeilen-link :href="route('admin.learners.grades.index', array_filter(['lernender_id' => $z->id, 'semester_id' => $sid]))" :label="__('Noten')" :zeile="$name" />
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $spalten }}" class="py-10 text-center text-sm text-muted">{{ __('Keine Lernenden für diese Filter.') }} <a href="{{ route('admin.reports.grades') }}" class="np-knopf np-knopf-schlicht np-knopf-klein">{{ __('Filter zurücksetzen') }}</a></td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-karte>
        </div>
    </div>
</x-app-layout>
