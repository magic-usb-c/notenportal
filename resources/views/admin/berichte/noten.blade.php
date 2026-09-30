<x-app-layout>
    <x-slot name="title">{{ __('Berichte') }}</x-slot>
    @php
        $sid = $filter['semester_id'];
        $semesterName = $sid ? (\App\Models\Semester::neutralerName($semester->firstWhere('semester_id', $sid)?->start_datum) ?? __('Semester')) : __('Ganze Lehrzeit');
        $sortUrl = fn (string $spalte, string $start = 'asc') => request()->fullUrlWithQuery([
            'sort' => $spalte,
            'dir' => $sort === $spalte ? ($dir === 'asc' ? 'desc' : 'asc') : $start,
        ]);
        // Richtung für Screenreader über aria-sort am Spaltenkopf, der Pfeil ist nur fürs Auge
        $pfeil = fn (string $spalte) => $sort === $spalte ? new \Illuminate\Support\HtmlString('<span aria-hidden="true">'.($dir === 'asc' ? '↑' : '↓').'</span>') : '';
        $ariaSort = fn (string $spalte) => $sort === $spalte ? ($dir === 'desc' ? 'descending' : 'ascending') : 'none';
        $filterAktiv = request()->hasAny(['semester', 'lehrberuf_id', 'berufsbildner_id']);
        $k = $kennzahlen;
    @endphp

    <x-slot name="header">
        <x-seitenkopf :titel="__('Notenbericht')" :untertitel="$semesterName">
            <x-slot:aktionen>
                <button type="button" onclick="window.print()" class="np-knopf np-knopf-sekundaer print:hidden">{{ __('Drucken') }}</button>
                <a href="{{ route('admin.reports.grades.export', request()->only(['semester', 'lehrberuf_id', 'berufsbildner_id'])) }}"
                   class="np-knopf np-knopf-sekundaer print:hidden">CSV</a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="np-seite mx-auto px-4 sm:px-6 lg:px-8 flex flex-col gap-5">

            <form method="GET" action="{{ route('admin.reports.grades') }}" class="np-karte p-4 grid grid-cols-1 sm:grid-cols-[1fr_1fr_1fr_auto] gap-3 items-end print:hidden">
                <input type="hidden" name="sort" value="{{ $sort }}">
                <input type="hidden" name="dir" value="{{ $dir }}">
                <div>
                    <label for="semester" class="text-sm font-medium text-text">{{ __('Zeitraum') }}</label>
                    <select name="semester" id="semester" onchange="this.form.submit()" class="np-feld mt-1">
                        <option value="alle" @selected($sid === null)>{{ __('Ganze Lehrzeit') }}</option>
                        @foreach($semester as $s)
                            <option value="{{ $s->semester_id }}" @selected($sid === (int) $s->semester_id)>{{ \App\Models\Semester::neutralerName($s->start_datum) ?? $s->bezeichnung }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="lehrberuf_id" class="text-sm font-medium text-text">{{ __('Lehrberuf') }}</label>
                    <select name="lehrberuf_id" id="lehrberuf_id" onchange="this.form.submit()" class="np-feld mt-1">
                        <option value="">{{ __('Alle Lehrberufe') }}</option>
                        @foreach($lehrberufe as $lb)
                            <option value="{{ $lb->lehrberuf_id }}" @selected($filter['lehrberuf_id'] === (int) $lb->lehrberuf_id)>{{ $lb->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="berufsbildner_id" class="text-sm font-medium text-text">{{ __('Berufsbildner') }}</label>
                    <select name="berufsbildner_id" id="berufsbildner_id" onchange="this.form.submit()" class="np-feld mt-1">
                        <option value="">{{ __('Alle') }}</option>
                        @foreach($berufsbildner as $bb)
                            <option value="{{ $bb->berufsbildner_id }}" @selected($filter['berufsbildner_id'] === (int) $bb->berufsbildner_id)>{{ $bb->nachname }} {{ $bb->vorname }}</option>
                        @endforeach
                    </select>
                </div>
                @if($filterAktiv)
                    <a href="{{ route('admin.reports.grades') }}" class="np-knopf np-knopf-schlicht">{{ __('Zurücksetzen') }}</a>
                @endif
            </form>

            <div @class(['grid grid-cols-2 gap-4', 'md:grid-cols-5' => $sid, 'md:grid-cols-4' => ! $sid])>
                <x-kachel :label="__('Lernende')" :wert="$k['lernende']" />
                <x-kachel :label="__('Ø Gesamtnote')" :note="$k['schnitt']" />
                <x-kachel :label="__('Kritisch')" :wert="$k['rot']" :ton="$k['rot'] ? 'rot' : 'neutral'" :sub="__(':gelb beobachten', ['gelb' => $k['gelb']])" :href="$sortUrl('status')" />
                <x-kachel :label="__('Ungenügende Zeugnisnoten')" :wert="$k['ungenuegend']" :ton="$k['ungenuegend'] ? 'rot' : 'neutral'" :sub="__('von :n', ['n' => $k['zeugnisnoten']])" />
                @if($sid)
                    <x-kachel :label="__('Promotion gefährdet')" :wert="$k['gefaehrdet']" :ton="$k['gefaehrdet'] ? 'rot' : 'gruen'" />
                @endif
            </div>

            @if(count($nachLehrjahr) > 1)
                <x-karte :titel="__('Gesamtschnitt nach Lehrjahr')" :polster="false" class="max-w-md">
                    <div class="overflow-x-auto px-2 pb-2">
                        <table class="np-tabelle text-sm">
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('Lehrjahr') }}</th>
                                    <th scope="col" class="text-right">Ø</th>
                                    <th scope="col" class="text-right">{{ __('Lernende') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($nachLehrjahr as $j)
                                    <tr>
                                        <td class="whitespace-nowrap">{{ __(':jahr. Lehrjahr', ['jahr' => $j['jahr']]) }}</td>
                                        <td class="text-right font-semibold {{ \App\Support\NotenSkala::text($j['schnitt']) }}">{{ \App\Support\NotenSkala::format($j['schnitt'], 1) }}</td>
                                        <td class="text-right text-muted">{{ $j['anzahl'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-karte>
            @endif

            @if($k['zeugnisnoten'] > 0)
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                    <x-diagramm :titel="__('Verteilung der Zeugnisnoten')" :frage="__('Wie verteilen sich die Zeugnisnoten auf die Notenskala?')"
                                :fazit="__(':ungenuegend von :gesamt Zeugnisnoten ungenügend, Durchschnitt :schnitt', ['ungenuegend' => $k['ungenuegend'], 'gesamt' => $k['zeugnisnoten'], 'schnitt' => \App\Support\NotenSkala::format($k['schnitt'], 2)])">
                        <x-slot:tabelle>
                            <table class="np-tabelle text-sm">
                                <thead>
                                    <tr><th>{{ __('Note') }}</th><th class="text-right">{{ __('Zeugnisnoten') }}</th><th class="text-right">{{ __('Stufe') }}</th></tr>
                                </thead>
                                <tbody>
                                    @foreach($verteilung['labels'] as $i => $label)
                                        <tr>
                                            <td>{{ $label }}</td>
                                            <td class="text-right">{{ $verteilung['werte'][$i] }}</td>
                                            <td class="text-right text-muted">{{ \App\Support\NotenSkala::stufeName((float) $label) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </x-slot:tabelle>
                        <div class="h-56" x-data="npChart('histogramm', {{ \Illuminate\Support\Js::from($verteilung) }})"><canvas x-ref="canvas" aria-label="{{ __('Verteilung der Zeugnisnoten') }}" role="img"></canvas></div>
                        <x-noten-legende class="mt-2" />
                    </x-diagramm>

                    <x-karte :titel="__('Kategorien')" :polster="false" class="@container">
                        <div class="overflow-x-auto px-2 pb-2">
                            <table class="np-tabelle text-sm">
                                <thead>
                                    <tr>
                                        <th>{{ __('Kategorie') }}</th>
                                        <th class="text-right">Ø</th>
                                        <th class="hidden @xl:table-cell text-right whitespace-nowrap">{{ __('Min – Max') }}</th>
                                        <th class="text-right">{{ __('Ungenügend') }}</th>
                                        @if($sid)<th class="hidden @xl:table-cell text-right whitespace-nowrap">{{ __('Promotion gefährdet') }}</th>@endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($kategorien as $kat)
                                        <tr>
                                            <td class="font-medium">
                                                {{ $kat['name'] }} <span class="text-xs text-muted font-normal">{{ $kat['anzahl'] }}</span>
                                                <span class="block text-xs font-normal text-muted tabular-nums @xl:hidden">{{ __('Min – Max') }} {{ \App\Support\NotenSkala::format($kat['min'], 1) }} – {{ \App\Support\NotenSkala::format($kat['max'], 1) }}@if($sid && $kat['gefaehrdet']) · <span class="text-note-ungenuegend">{{ __('Promotion gefährdet') }} {{ $kat['gefaehrdet'] }}</span>@endif</span>
                                            </td>
                                            <td class="text-right font-semibold {{ \App\Support\NotenSkala::text($kat['schnitt']) }}">{{ \App\Support\NotenSkala::format($kat['schnitt'], 2) }}</td>
                                            <td class="hidden @xl:table-cell text-right text-muted">{{ \App\Support\NotenSkala::format($kat['min'], 1) }} – {{ \App\Support\NotenSkala::format($kat['max'], 1) }}</td>
                                            <td @class(['px-3 py-2.5 text-right tabular-nums', 'text-note-ungenuegend font-semibold' => $kat['ungenuegend'], 'text-muted' => ! $kat['ungenuegend']])>{{ $kat['ungenuegend'] }}</td>
                                            @if($sid)
                                                <td @class(['hidden @xl:table-cell px-5 py-2.5 text-right tabular-nums', 'text-note-ungenuegend font-semibold' => $kat['gefaehrdet'], 'text-muted' => ! $kat['gefaehrdet']])>{{ $kat['gefaehrdet'] }}</td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </x-karte>
                </div>

                <x-karte :titel="__('Tiefste Fächer und Module')">
                    <x-noten-legende class="mb-3" />
                    <ul class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-3">
                        @foreach($schwachstellen as $s)
                            @php $breite = max(2, min(100, ($s['schnitt'] - 1) / 5 * 100)); @endphp
                            <li class="flex min-w-0 flex-col gap-1">
                                <div class="flex items-baseline justify-between gap-3 text-sm">
                                    <span class="min-w-0 break-words sm:truncate text-text font-medium">{{ $s['label'] }} <span class="text-xs text-muted font-normal">{{ $s['kategorie'] }}</span></span>
                                    <span class="font-bold tabular-nums {{ \App\Support\NotenSkala::text($s['schnitt']) }}">{{ \App\Support\NotenSkala::format($s['schnitt'], 2) }}</span>
                                </div>
                                <div class="h-1.5 rounded-full bg-accent/10 overflow-hidden"><div class="h-full rounded-full {{ \App\Support\NotenSkala::balken($s['schnitt']) }}" style="width: {{ $breite }}%"></div></div>
                                <div class="text-xs text-muted">{{ $s['anzahl'] }} {{ $s['anzahl'] === 1 ? __('Zeugnisnote') : __('Zeugnisnoten') }}@if($s['ungenuegend']) · <span class="text-note-ungenuegend">{{ $s['ungenuegend'] }} {{ __('ungenügend') }}</span>@endif</div>
                            </li>
                        @endforeach
                    </ul>
                </x-karte>
            @endif

            {{-- Spalten nach Breite der Karte (Seitenleiste). Was ausgeblendet ist, steht in der Zeile unter dem Namen,
                 die Sortierung der ausgeblendeten Spalten im Kopf der Namensspalte. --}}
            <x-karte :titel="__('Lernende')" :polster="false" class="@container">
                <div class="overflow-x-auto px-2 pb-2">
                    <table class="np-tabelle text-sm">
                        <thead>
                            <tr>
                                <th scope="col" aria-sort="{{ $ariaSort('name') }}">
                                    <span class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                        <a href="{{ $sortUrl('name') }}" class="hover:text-text">{{ __('Name') }} {{ $pfeil('name') }}</a>
                                        <a href="{{ $sortUrl('status') }}" class="hover:text-text @2xl:hidden">{{ __('Status') }} {{ $pfeil('status') }}</a>
                                        @if($sid)<a href="{{ $sortUrl('semester', 'desc') }}" class="hover:text-text @2xl:hidden">{{ __('Semester') }} {{ $pfeil('semester') }}</a>@endif
                                        <a href="{{ $sortUrl('ungenuegend', 'desc') }}" class="hover:text-text @4xl:hidden">{{ __('Ungenügend') }} {{ $pfeil('ungenuegend') }}</a>
                                        <a href="{{ $sortUrl('pruefungen', 'desc') }}" class="hover:text-text @5xl:hidden">{{ __('Prüfungen') }} {{ $pfeil('pruefungen') }}</a>
                                        <a href="{{ $sortUrl('letzte', 'desc') }}" class="hover:text-text @5xl:hidden">{{ __('Letzte Note') }} {{ $pfeil('letzte') }}</a>
                                    </span>
                                </th>
                                <th scope="col" class="hidden @2xl:table-cell" aria-sort="{{ $ariaSort('status') }}"><a href="{{ $sortUrl('status') }}" class="hover:text-text">{{ __('Status') }} {{ $pfeil('status') }}</a></th>
                                <th scope="col" class="text-right whitespace-nowrap" aria-sort="{{ $ariaSort('gesamt') }}"><a href="{{ $sortUrl('gesamt', 'desc') }}" class="hover:text-text">{{ __('Gesamt') }} {{ $pfeil('gesamt') }}</a></th>
                                @if($sid)
                                    <th scope="col" class="hidden @2xl:table-cell text-right whitespace-nowrap" aria-sort="{{ $ariaSort('semester') }}"><a href="{{ $sortUrl('semester', 'desc') }}" class="hover:text-text">{{ __('Semester') }} {{ $pfeil('semester') }}</a></th>
                                @endif
                                <th scope="col" class="hidden @4xl:table-cell text-right whitespace-nowrap" aria-sort="{{ $ariaSort('ungenuegend') }}"><a href="{{ $sortUrl('ungenuegend', 'desc') }}" class="hover:text-text">{{ __('Ungenügend') }} {{ $pfeil('ungenuegend') }}</a></th>
                                <th scope="col" class="hidden @5xl:table-cell text-right whitespace-nowrap" aria-sort="{{ $ariaSort('pruefungen') }}"><a href="{{ $sortUrl('pruefungen', 'desc') }}" class="hover:text-text">{{ __('Prüfungen') }} {{ $pfeil('pruefungen') }}</a></th>
                                <th scope="col" class="hidden @5xl:table-cell text-right whitespace-nowrap" aria-sort="{{ $ariaSort('letzte') }}"><a href="{{ $sortUrl('letzte', 'desc') }}" class="hover:text-text">{{ __('Letzte Note') }} {{ $pfeil('letzte') }}</a></th>
                                <th class="print:hidden"><span class="sr-only">{{ __('Noten') }}</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($zeilen as $z)
                                <tr>
                                    <td>
                                        <a href="{{ route('admin.learners.show', $z->id) }}" class="font-medium hover:text-accent-text">{{ $z->nachname }} {{ $z->vorname }}</a>
                                        @if($z->lehrberuf)<span class="ml-1 text-xs text-muted">{{ $z->lehrberuf }}</span>@endif
                                        <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted tabular-nums @5xl:hidden">
                                            <span class="@2xl:hidden"><x-status :status="$z->stand->status" /></span>
                                            @if($sid)
                                                <span class="whitespace-nowrap @2xl:hidden">{{ __('Semester') }} <span class="font-semibold {{ \App\Support\NotenSkala::text($z->semester) }}">{{ \App\Support\NotenSkala::format($z->semester, 1) }}</span></span>
                                            @endif
                                            @if($z->ungenuegend)
                                                <span class="whitespace-nowrap font-semibold text-note-ungenuegend @4xl:hidden">{{ __(':anzahl ungenügend', ['anzahl' => $z->ungenuegend]) }}</span>
                                            @endif
                                            <span class="whitespace-nowrap">{{ $z->pruefungen === 1 ? __('1 Prüfung') : __(':anzahl Prüfungen', ['anzahl' => $z->pruefungen]) }}</span>
                                            @if($z->letzte)
                                                <span>{{ __('Letzte Note :wann', ['wann' => \Illuminate\Support\Carbon::parse($z->letzte)->format('d.m.Y')]) }}</span>
                                            @endif
                                        </div>
                                        @if($z->stand->gruende)
                                            <div class="mt-1 text-xs text-muted @2xl:hidden">{{ implode(' · ', $z->stand->gruende) }}</div>
                                        @endif
                                    </td>
                                    <td class="hidden @2xl:table-cell">
                                        <x-status :status="$z->stand->status" />
                                        @if($z->stand->gruende)
                                            <div class="mt-1 line-clamp-2 max-w-72 text-xs text-muted" title="{{ implode(' · ', $z->stand->gruende) }}">{{ implode(' · ', $z->stand->gruende) }}</div>
                                        @endif
                                    </td>
                                    <td class="text-right font-semibold {{ \App\Support\NotenSkala::text($z->gesamt) }}">{{ \App\Support\NotenSkala::format($z->gesamt, 1) }}</td>
                                    @if($sid)
                                        <td class="hidden @2xl:table-cell text-right font-semibold {{ \App\Support\NotenSkala::text($z->semester) }}">{{ \App\Support\NotenSkala::format($z->semester, 1) }}</td>
                                    @endif
                                    <td @class(['hidden @4xl:table-cell px-3 py-2.5 text-right tabular-nums', 'text-note-ungenuegend font-semibold' => $z->ungenuegend, 'text-muted' => ! $z->ungenuegend])>{{ $z->ungenuegend }}</td>
                                    <td class="hidden @5xl:table-cell text-right text-muted">{{ $z->pruefungen }}</td>
                                    <td class="hidden @5xl:table-cell text-right text-muted whitespace-nowrap">{{ $z->letzte ? \Illuminate\Support\Carbon::parse($z->letzte)->format('d.m.Y') : '–' }}</td>
                                    <td class="text-right print:hidden">
                                        <a href="{{ route('admin.learners.grades.index', array_filter(['lernender_id' => $z->id, 'semester_id' => $sid])) }}" class="np-ziel inline-flex min-h-6 items-center text-xs text-accent-text hover:underline whitespace-nowrap">{{ __('Noten') }} →</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-5 py-10 text-center text-sm text-muted">
                                        {{ __('Keine Lernenden') }}
                                        @if($filterAktiv)
                                            <a href="{{ route('admin.reports.grades') }}" class="ml-2 text-accent-text hover:underline">{{ __('Filter zurücksetzen') }}</a>
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
