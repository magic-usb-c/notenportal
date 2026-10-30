{{-- Notenbericht: Kennzahlen, Verteilung und Kategorien, tiefste Fächer und Module, dann alle Lernenden.
     Die Zeile öffnet das Profil, «Noten» die Notenliste im gewählten Zeitraum. --}}
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
    // Auswahl für die Filter Kategorie und Lehrjahr (der Controller reicht beide Listen nicht durch)
    $kategorieAuswahl = collect(\App\Services\Auswertung\Konfiguration::ausDb()->kategorien)->map(fn ($kat) => $kat['name']);
    $lehrjahrAuswahl = range(1, max(4, (int) $filter['lehrjahr']));
    $skala = NotenSkala::class;
    $genuegend = $skala::genuegend();
    // Achse der Anzahlen ab 0 mit ganzzahligen Marken; Obergrenze ist ein Vielfaches des Schritts
    $achseAnzahl = function (int $spitze): array {
        $schritt = match (true) {
            $spitze <= 4 => 1,
            $spitze <= 10 => 2,
            $spitze <= 20 => 5,
            $spitze <= 50 => 10,
            default => (int) (ceil($spitze / 5 / 10) * 10),
        };
        $oben = max($schritt, (int) (ceil($spitze / $schritt) * $schritt));

        return [$oben, range(0, $oben, $schritt)];
    };
    $auswahl = 'np-feld np-feld-klein w-auto max-w-72';
    $k = $kennzahlen;
    $hSatz = $statistik['zusammenfassung']['histogramm'];
    $andereFilter = (bool) ($filter['lehrberuf_id'] || $filter['berufsbildner_id'] || $filter['kategorie_id'] || $filter['lehrjahr']);
    $mitLehrjahr = count($nachLehrjahr) > 1;
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
                                :statistik="['json' => false, 'ersetze' => ['[data-np-filterstatus]', '.np-seite.flex-col > :not(:first-child)']]">
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
                        @foreach($kategorieAuswahl as $kid => $kname)
                            <option value="{{ $kid }}" @selected($filter['kategorie_id'] === (int) $kid)>{{ $kname }}</option>
                        @endforeach
                    </select>
                    <label for="lehrjahr" class="sr-only">{{ __('Lehrjahr') }}</label>
                    <select name="lehrjahr" id="lehrjahr" x-on:change="$el.form.requestSubmit()" class="{{ $auswahl }}">
                        <option value="">{{ __('Alle Lehrjahre') }}</option>
                        @foreach($lehrjahrAuswahl as $jahr)
                            <option value="{{ $jahr }}" @selected($filter['lehrjahr'] === $jahr)>{{ __(':jahr. Lehrjahr', ['jahr' => $jahr]) }}</option>
                        @endforeach
                    </select>
                </x-filterleiste>
                {{-- Bleibt beim Tausch stehen; statistik.js schreibt nach jedem Filterwechsel den neuen Satz aus data-np-text hinein --}}
                <p class="sr-only" aria-live="polite" data-np-ansage data-np-text="{{ $hSatz }}"></p>
            </div>

            <div @class(['grid gap-4', 'grid-cols-5' => $sid, 'grid-cols-4' => ! $sid])>
                <x-kachel :label="__('Lernende')" :wert="$k['lernende']" />
                <x-kachel :label="__('Ø Gesamtnote')" :note="$k['schnitt']" />
                <x-kachel :label="__('Kritisch')" :wert="$k['rot']" :ton="$k['rot'] ? 'rot' : 'neutral'" :sub="__(':gelb beobachten', ['gelb' => $k['gelb']])" :href="$kritischUrl" />
                <x-kachel :label="__('Ungenügende Zeugnisnoten')" :wert="$k['ungenuegend']" :ton="$k['ungenuegend'] ? 'rot' : 'neutral'" :sub="__('von :n', ['n' => $k['zeugnisnoten']])" :href="$ungenuegendUrl" />
                @if($sid)
                    <x-kachel :label="__('Promotion gefährdet')" :wert="$k['gefaehrdet']" :ton="$k['gefaehrdet'] ? 'rot' : 'neutral'" />
                @endif
            </div>

            @if($k['zeugnisnoten'] === 0)
                <section class="np-karte">
                    <x-leer symbol="chart-bar" :titel="$hSatz">
                        @if($andereFilter || $sid !== null)
                            <a href="{{ route('admin.reports.grades', ['semester' => 'alle']) }}" class="np-knopf np-knopf-sekundaer">{{ $andereFilter ? __('Filter zurücksetzen') : __('Ganze Lehrzeit anzeigen') }}</a>
                        @elseif($k['lernende'] === 0)
                            <a href="{{ route('admin.learners.create') }}" class="np-knopf np-knopf-sekundaer">{{ __('Lernende erfassen') }}</a>
                        @endif
                    </x-leer>
                </section>
            @endif

            @if($k['zeugnisnoten'] > 0 || $mitLehrjahr)
                <div class="grid grid-cols-2 gap-4">
                    @if($k['zeugnisnoten'] > 0)
                        {{-- Notenverteilung (S10): Viertelnoten ab 0, Genügend-Linie, Median erst ab drei Noten. Die Seite ersetzt npFilter als Ganzes. --}}
                        @php
                            [$hOben, $hMarken] = $achseAnzahl((int) max($verteilung['werte']));
                            $hHoehe = fn (int|float $v) => round($v / $hOben * 100, 2);
                            $hLinks = fn (float $v) => round($skala::breite($v), 2);
                        @endphp
                        <x-diagramm :titel="__('Verteilung der Zeugnisnoten')">
                            <div class="mb-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs tabular-nums text-muted" aria-hidden="true">
                                <span class="inline-flex items-center gap-1.5"><span class="w-4 border-t border-dashed border-muted"></span>{{ __('genügend :wert', ['wert' => $skala::format($genuegend, 1)]) }}</span>
                                @if($verteilung['median'] !== null)
                                    <span class="inline-flex items-center gap-1.5"><span class="h-0.5 w-4 rounded-full bg-text"></span>{{ __('Median') }} {{ $skala::format($verteilung['median'], 2) }}</span>
                                @endif
                            </div>
                            <div class="flex gap-2" role="img" aria-labelledby="np-histogramm-satz">
                                <div class="relative h-64 w-7 shrink-0" aria-hidden="true">
                                    @foreach($hMarken as $marke)
                                        <span class="absolute right-0 translate-y-1/2 text-2xs tabular-nums leading-none text-muted" style="bottom: {{ $hHoehe($marke) }}%">{{ $marke }}</span>
                                    @endforeach
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="relative h-64" aria-hidden="true">
                                        @foreach($hMarken as $marke)
                                            <div class="absolute inset-x-0 border-t {{ $marke === 0 ? 'border-border-strong/60' : 'border-border' }}" style="bottom: {{ $hHoehe($marke) }}%"></div>
                                        @endforeach
                                        <div class="absolute inset-0 flex items-end gap-px">
                                            @foreach($verteilung['werte'] as $i => $anzahl)
                                                <div class="flex h-full min-w-0 flex-1 items-end" title="{{ __('Note ab') }} {{ $verteilung['labels'][$i] }}: {{ $anzahl }}">
                                                    @if($anzahl > 0)
                                                        <div class="w-full rounded-t-sm {{ $skala::balken($verteilung['farben'][$i]) }}" style="height: {{ max(1.5, $hHoehe($anzahl)) }}%"></div>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                        <div class="absolute inset-y-0 border-l border-dashed border-muted" style="left: {{ $hLinks($genuegend) }}%"></div>
                                        @if($verteilung['median'] !== null)
                                            <div class="absolute inset-y-0 w-0.5 -translate-x-1/2 bg-text" style="left: {{ $hLinks($verteilung['median']) }}%"></div>
                                        @endif
                                    </div>
                                    <div class="relative mt-1 h-4" aria-hidden="true">
                                        @foreach([1, 2, 3, 4, 5, 6] as $note)
                                            <span class="absolute -translate-x-1/2 text-2xs tabular-nums text-muted" style="left: {{ $hLinks($note) }}%">{{ $note }}</span>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                            <x-noten-legende class="mt-3" />
                            <p id="np-histogramm-satz" class="mt-2 text-xs text-muted">{{ $hSatz }}</p>
                            <x-slot:tabelle>
                                <table class="np-tabelle text-sm">
                                    <caption class="sr-only">{{ __('Verteilung der Zeugnisnoten') }}</caption>
                                    <thead>
                                        <tr><th scope="col">{{ __('Note ab') }}</th><th scope="col" class="text-right">{{ __('Zeugnisnoten') }}</th><th scope="col">{{ __('Stufe') }}</th></tr>
                                    </thead>
                                    <tbody>
                                        @foreach($verteilung['labels'] as $i => $label)
                                            <tr>
                                                <td>{{ $label }}</td>
                                                <td class="text-right">{{ $verteilung['werte'][$i] }}</td>
                                                <td class="text-muted">{{ NotenSkala::stufeName((float) $label) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </x-slot:tabelle>
                        </x-diagramm>
                    @endif

                    <div class="flex flex-col gap-4">
                        @if($k['zeugnisnoten'] > 0)
                            <x-karte :titel="__('Kategorien')" :polster="false">
                                <div class="px-2 pb-2">
                                    <table class="np-tabelle table-fixed text-sm">
                                        <caption class="sr-only">{{ __('Kategorien') }}</caption>
                                        <thead>
                                            <tr>
                                                <th scope="col">{{ __('Kategorie') }}</th>
                                                <th scope="col" class="w-24 text-right">{{ __('Lernende') }}</th>
                                                <th scope="col" class="w-16 text-right"><span aria-hidden="true">Ø</span><span class="sr-only">{{ __('Durchschnitt') }}</span></th>
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

                        {{-- Lehrjahre im Vergleich (S11): ein Streifen je Lehrjahr, alle auf derselben Achse 1 bis 6, Median erst ab drei Personen --}}
                        @if($mitLehrjahr)
                            @php
                                $lj = $statistik['diagramm']['lehrjahre']['jahre'];
                                $ljTabelle = $statistik['tabelle']['lehrjahre'];
                                $ljSatz = $statistik['zusammenfassung']['lehrjahre'];
                                // Punkte der Breite nach sortiert; berührt ein Punkt den Vorgänger, rückt er in die nächste Reihe
                                $ljBreite = 600;
                                $ljRadius = 4;
                                $ljSchritt = 9;
                                $ljX = fn (float $v) => round($skala::breite($v) / 100 * $ljBreite, 2);
                                $ljLage = [];
                                $ljReihen = 1;
                                foreach ($lj as $zeile => $j) {
                                    $reihen = [];
                                    foreach (collect($j['werte'])->sort()->values() as $wert) {
                                        $px = $ljX((float) $wert);
                                        $reihe = 0;
                                        while (isset($reihen[$reihe]) && $px - $reihen[$reihe] < 2 * $ljRadius + 1) {
                                            $reihe++;
                                        }
                                        $reihen[$reihe] = $px;
                                        $ljLage[$zeile][] = ['x' => $px, 'reihe' => $reihe, 'wert' => $wert];
                                    }
                                    $ljReihen = max($ljReihen, count($reihen));
                                }
                                $ljHoehe = 2 * $ljRadius + 6 + ($ljReihen - 1) * $ljSchritt;
                            @endphp
                            <x-diagramm :titel="__('Gesamtschnitt nach Lehrjahr')">
                                <div class="mb-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs tabular-nums text-muted" aria-hidden="true">
                                    <span class="inline-flex items-center gap-1.5"><span class="w-4 border-t border-dashed border-muted"></span>{{ __('genügend :wert', ['wert' => $skala::format($genuegend, 1)]) }}</span>
                                    @if(collect($lj)->contains(fn ($j) => $j['median'] !== null))
                                        <span class="inline-flex items-center gap-1.5"><span class="h-3 w-0.5 rounded-full bg-text"></span>{{ __('Median') }}</span>
                                    @endif
                                </div>
                                <div class="relative" role="group" aria-label="{{ __('Gesamtschnitt nach Lehrjahr') }}">
                                    <ul class="flex flex-col" role="list">
                                        @foreach($lj as $zeile => $j)
                                            <li class="flex items-center gap-4 py-1.5">
                                                <span class="w-24 shrink-0 text-sm text-text">{{ __(':jahr. Lehrjahr', ['jahr' => $j['jahr']]) }}</span>
                                                <svg class="block min-w-0 flex-1 overflow-visible" viewBox="0 0 {{ $ljBreite }} {{ $ljHoehe }}" role="img"
                                                     aria-label="{{ __(':jahr. Lehrjahr', ['jahr' => $j['jahr']]) }}: {{ $j['anzahl'] }} {{ $j['anzahl'] === 1 ? __('Person') : __('Personen') }}, {{ __('Durchschnitt') }} {{ $skala::format($j['schnitt'], 1) }}@if($j['median'] !== null), {{ __('Median') }} {{ $skala::format($j['median'], 1) }}@endif">
                                                    <line x1="0" x2="{{ $ljBreite }}" y1="{{ $ljHoehe - 1 }}" y2="{{ $ljHoehe - 1 }}" class="stroke-border" stroke-width="1"/>
                                                    @if($j['median'] !== null)
                                                        <rect x="{{ $ljX((float) $j['median']) - 1 }}" y="0" width="2" height="{{ $ljHoehe }}" rx="1" class="fill-text"/>
                                                    @endif
                                                    @foreach($ljLage[$zeile] ?? [] as $punkt)
                                                        <circle cx="{{ $punkt['x'] }}" cy="{{ $ljHoehe - $ljRadius - 3 - $punkt['reihe'] * $ljSchritt }}" r="{{ $ljRadius }}" class="{{ str_replace('bg-', 'fill-', $skala::balken($punkt['wert'])) }}">
                                                            <title>{{ $skala::format($punkt['wert'], 1) }}</title>
                                                        </circle>
                                                    @endforeach
                                                </svg>
                                                <span class="w-44 shrink-0 text-right text-xs tabular-nums text-muted">
                                                    {{ $j['anzahl'] }} {{ $j['anzahl'] === 1 ? __('Person') : __('Personen') }} · <span class="text-sm font-semibold {{ $skala::text($j['schnitt']) }}"><span aria-hidden="true">Ø</span><span class="sr-only">{{ __('Durchschnitt') }}</span> {{ $skala::format($j['schnitt'], 1) }}</span>
                                                </span>
                                            </li>
                                        @endforeach
                                    </ul>
                                    <div class="pointer-events-none absolute inset-y-0 left-28 right-48" aria-hidden="true">
                                        <div class="absolute inset-y-0 border-l border-dashed border-muted" style="left: {{ round($skala::breite($genuegend), 2) }}%"></div>
                                    </div>
                                </div>
                                <div class="relative mb-1 ml-28 mr-48 mt-1 h-4" aria-hidden="true">
                                    @foreach([1, 2, 3, 4, 5, 6] as $note)
                                        <span class="absolute -translate-x-1/2 text-2xs tabular-nums text-muted" style="left: {{ round($skala::breite($note), 2) }}%">{{ $note }}</span>
                                    @endforeach
                                </div>
                                <p class="mt-2 text-xs text-muted">{{ $ljSatz }}</p>
                                <x-slot:tabelle>
                                    <table class="np-tabelle text-sm">
                                        <caption class="sr-only">{{ __('Gesamtschnitt nach Lehrjahr') }}</caption>
                                        <thead>
                                            <tr>
                                                @foreach($ljTabelle['spalten'] as $i => $spalte)
                                                    <th scope="col" @class(['text-right' => $i > 0])>{{ $spalte }}</th>
                                                @endforeach
                                                <th scope="col">{{ __('Stufe') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($ljTabelle['zeilen'] as $ljZeile => $zeile)
                                                <tr>
                                                    @foreach($zeile as $i => $zelle)
                                                        <td @class(['text-right' => $i > 0])>{{ $i === 0 ? __(':jahr. Lehrjahr', ['jahr' => $zelle]) : $zelle }}</td>
                                                    @endforeach
                                                    <td class="text-muted">{{ NotenSkala::stufeName($lj[$ljZeile]['anzahl'] > 0 ? (float) $lj[$ljZeile]['schnitt'] : null) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </x-slot:tabelle>
                            </x-diagramm>
                        @endif
                    </div>
                </div>
            @endif

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

            <x-karte id="lernende" :titel="__('Lernende')" :polster="false">
                <div class="px-2 pb-2">
                    <table class="np-tabelle table-fixed text-sm">
                        <caption class="sr-only">{{ __('Lernende') }}</caption>
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
                                            <div class="mt-0.5 text-pretty text-xs text-muted">{{ implode(' · ', $z->stand->gruende) }}</div>
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
                                    <td colspan="{{ $spalten }}" class="py-10 text-center text-sm text-muted">
                                        @if($aktiveFilter > 0)
                                            {{ __('Keine Lernenden für diese Filter.') }} <a href="{{ route('admin.reports.grades') }}" class="np-knopf np-knopf-schlicht np-knopf-klein">{{ __('Filter zurücksetzen') }}</a>
                                        @else
                                            {{ __('Noch keine Lernenden.') }} <a href="{{ route('admin.learners.create') }}" class="np-knopf np-knopf-schlicht np-knopf-klein">{{ __('Lernende erfassen') }}</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-karte>
        </div>
    </div>
</x-app-layout>
