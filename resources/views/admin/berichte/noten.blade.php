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
    $filterParameter = request()->only(['semester', 'lehrberuf_id', 'berufsbildner_id']);
    $aktiveFilter = count(array_filter([$filter['lehrberuf_id'], $filter['berufsbildner_id'], request()->filled('semester') ? 1 : null]));
    $auswahl = 'np-feld np-feld-klein w-auto max-w-72';
    $k = $kennzahlen;
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
                <a href="{{ route('admin.reports.grades.export', $filterParameter) }}" class="np-knopf np-knopf-sekundaer print:hidden">
                    <x-symbol name="arrow-down-tray" />{{ __('Exportieren') }}
                </a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="np-seite mx-auto flex flex-col gap-6 px-8">
            <div class="print:hidden">
                <x-filterleiste :action="route('admin.reports.grades')" :aktive-filter="$aktiveFilter" :zurueck="route('admin.reports.grades')">
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
                </x-filterleiste>
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

            @if($k['zeugnisnoten'] > 0 || $mitLehrjahr)
                <div class="grid grid-cols-2 items-start gap-4">
                    @if($k['zeugnisnoten'] > 0)
                        <x-diagramm :titel="__('Verteilung der Zeugnisnoten')"
                                    :fazit="__(':ungenuegend von :gesamt Zeugnisnoten ungenügend, Durchschnitt :schnitt', ['ungenuegend' => $k['ungenuegend'], 'gesamt' => $k['zeugnisnoten'], 'schnitt' => NotenSkala::format($k['schnitt'], 2)])">
                            <x-slot:tabelle>
                                <table class="np-tabelle text-sm">
                                    <thead>
                                        <tr><th scope="col">{{ __('Note') }}</th><th scope="col" class="text-right">{{ __('Zeugnisnoten') }}</th><th scope="col" class="text-right">{{ __('Stufe') }}</th></tr>
                                    </thead>
                                    <tbody>
                                        @foreach($verteilung['labels'] as $i => $label)
                                            <tr>
                                                <td>{{ $label }}</td>
                                                <td class="text-right">{{ $verteilung['werte'][$i] }}</td>
                                                <td class="text-right text-muted">{{ NotenSkala::stufeName((float) $label) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </x-slot:tabelle>
                            <div class="h-80" x-data="npChart('histogramm', {{ \Illuminate\Support\Js::from($verteilung) }})"><canvas x-ref="canvas" aria-label="{{ __('Verteilung der Zeugnisnoten') }}" role="img"></canvas></div>
                            <x-noten-legende class="mt-2" />
                        </x-diagramm>
                    @endif

                    <div class="flex flex-col gap-4">
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

                        @if($mitLehrjahr)
                            <x-karte :titel="__('Gesamtschnitt nach Lehrjahr')" :polster="false">
                                <div class="px-2 pb-2">
                                    <table class="np-tabelle table-fixed text-sm">
                                        <thead>
                                            <tr>
                                                <th scope="col">{{ __('Lehrjahr') }}</th>
                                                <th scope="col" class="w-24 text-right">{{ __('Lernende') }}</th>
                                                <th scope="col" class="w-16 text-right">Ø</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($nachLehrjahr as $j)
                                                <tr>
                                                    <td class="text-text">{{ __(':jahr. Lehrjahr', ['jahr' => $j['jahr']]) }}</td>
                                                    <td class="text-right text-muted">{{ $j['anzahl'] }}</td>
                                                    <td class="text-right font-semibold {{ NotenSkala::text($j['schnitt']) }}">{{ NotenSkala::format($j['schnitt'], 1) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </x-karte>
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
