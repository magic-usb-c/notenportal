<x-app-layout>
    <x-slot name="title">Berichte</x-slot>
    @php
        $sid = $filter['semester_id'];
        $semesterName = $sid ? ($semester->firstWhere('semester_id', $sid)?->bezeichnung ?? 'Semester') : 'Ganze Lehrzeit';
        $sortUrl = fn (string $spalte, string $start = 'asc') => request()->fullUrlWithQuery([
            'sort' => $spalte,
            'dir' => $sort === $spalte ? ($dir === 'asc' ? 'desc' : 'asc') : $start,
        ]);
        $pfeil = fn (string $spalte) => $sort === $spalte ? ($dir === 'asc' ? '↑' : '↓') : '';
        $filterAktiv = request()->hasAny(['semester', 'lehrberuf_id', 'berufsbildner_id']);
        $k = $kennzahlen;
    @endphp

    <x-slot name="header">
        <x-seitenkopf titel="Notenbericht" :untertitel="$semesterName">
            <x-slot:aktionen>
                <button type="button" onclick="window.print()" class="inline-flex items-center px-4 h-9 rounded-lg glass-btn text-text text-sm print:hidden">Drucken</button>
                <a href="{{ route('admin.reports.grades.export', request()->only(['semester', 'lehrberuf_id', 'berufsbildner_id'])) }}"
                   class="inline-flex items-center px-4 h-9 rounded-lg glass-btn text-text text-sm print:hidden">CSV</a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col gap-5">

            <form method="GET" action="{{ route('admin.reports.grades') }}" class="rounded-2xl border border-border bg-card p-4 grid grid-cols-1 sm:grid-cols-[1fr_1fr_1fr_auto] gap-3 items-end print:hidden">
                <input type="hidden" name="sort" value="{{ $sort }}">
                <input type="hidden" name="dir" value="{{ $dir }}">
                <div>
                    <label for="semester" class="text-sm font-medium text-text">Zeitraum</label>
                    <select name="semester" id="semester" onchange="this.form.submit()" class="mt-1 w-full rounded-xl border border-border bg-input text-text focus:ring-2 focus:ring-ring focus:border-ring text-sm">
                        <option value="alle" @selected($sid === null)>Ganze Lehrzeit</option>
                        @foreach($semester as $s)
                            <option value="{{ $s->semester_id }}" @selected($sid === (int) $s->semester_id)>{{ $s->bezeichnung }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="lehrberuf_id" class="text-sm font-medium text-text">Lehrberuf</label>
                    <select name="lehrberuf_id" id="lehrberuf_id" onchange="this.form.submit()" class="mt-1 w-full rounded-xl border border-border bg-input text-text focus:ring-2 focus:ring-ring focus:border-ring text-sm">
                        <option value="">Alle Lehrberufe</option>
                        @foreach($lehrberufe as $lb)
                            <option value="{{ $lb->lehrberuf_id }}" @selected($filter['lehrberuf_id'] === (int) $lb->lehrberuf_id)>{{ $lb->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="berufsbildner_id" class="text-sm font-medium text-text">Berufsbildner</label>
                    <select name="berufsbildner_id" id="berufsbildner_id" onchange="this.form.submit()" class="mt-1 w-full rounded-xl border border-border bg-input text-text focus:ring-2 focus:ring-ring focus:border-ring text-sm">
                        <option value="">Alle</option>
                        @foreach($berufsbildner as $bb)
                            <option value="{{ $bb->berufsbildner_id }}" @selected($filter['berufsbildner_id'] === (int) $bb->berufsbildner_id)>{{ $bb->nachname }} {{ $bb->vorname }}</option>
                        @endforeach
                    </select>
                </div>
                @if($filterAktiv)
                    <a href="{{ route('admin.reports.grades') }}" class="inline-flex items-center justify-center px-4 h-10 rounded-xl glass-btn text-text text-sm" aria-label="Filter zurücksetzen">×</a>
                @endif
            </form>

            <div @class(['grid grid-cols-2 gap-4', 'md:grid-cols-5' => $sid, 'md:grid-cols-4' => ! $sid])>
                <x-kachel label="Lernende" :wert="$k['lernende']" />
                <x-kachel label="Ø Gesamtnote" :note="$k['schnitt']" />
                <x-kachel label="Kritisch" :wert="$k['rot']" :ton="$k['rot'] ? 'rot' : 'neutral'" :sub="$k['gelb'].' beobachten'" :href="$sortUrl('status')" />
                <x-kachel label="Ungenügende Zeugnisnoten" :wert="$k['ungenuegend']" :ton="$k['ungenuegend'] ? 'rot' : 'neutral'" :sub="'von '.$k['zeugnisnoten']" />
                @if($sid)
                    <x-kachel label="Promotion gefährdet" :wert="$k['gefaehrdet']" :ton="$k['gefaehrdet'] ? 'rot' : 'gruen'" />
                @endif
            </div>

            @if($k['zeugnisnoten'] > 0)
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                    <x-diagramm titel="Verteilung der Zeugnisnoten" frage="Wie verteilen sich die Zeugnisnoten auf die Notenskala?"
                                :fazit="$k['ungenuegend'].' von '.$k['zeugnisnoten'].' Zeugnisnoten ungenügend, Durchschnitt '.\App\Support\NotenSkala::format($k['schnitt'], 2)">
                        <x-slot:tabelle>
                            <table class="w-full text-sm tabular-nums">
                                <thead class="text-2xs text-muted">
                                    <tr><th class="text-left px-3 py-2 font-medium">Note</th><th class="text-right px-3 py-2 font-medium">Zeugnisnoten</th></tr>
                                </thead>
                                <tbody>
                                    @foreach($verteilung['labels'] as $i => $label)
                                        <tr class="border-t border-border">
                                            <td class="px-3 py-2">{{ $label }}</td>
                                            <td class="px-3 py-2 text-right">{{ $verteilung['werte'][$i] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </x-slot:tabelle>
                        <div class="h-56" x-data="npChart('histogramm', {{ \Illuminate\Support\Js::from($verteilung) }})"><canvas x-ref="canvas" aria-label="Verteilung der Zeugnisnoten" role="img"></canvas></div>
                    </x-diagramm>

                    <x-karte titel="Kategorien" :polster="false">
                        <div class="overflow-x-auto">
                            <table class="min-w-full text-sm text-text">
                                <thead class="text-muted text-xs">
                                    <tr class="border-b border-border">
                                        <th class="text-left px-5 py-2 font-medium">Kategorie</th>
                                        <th class="text-right px-3 py-2 font-medium">Ø</th>
                                        <th class="text-right px-3 py-2 font-medium whitespace-nowrap">Min – Max</th>
                                        <th class="text-right px-3 py-2 font-medium">Ungenügend</th>
                                        @if($sid)<th class="text-right px-5 py-2 font-medium whitespace-nowrap">Promotion gefährdet</th>@endif
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    @foreach($kategorien as $kat)
                                        <tr>
                                            <td class="px-5 py-2.5 font-medium">{{ $kat['name'] }} <span class="text-xs text-muted font-normal">{{ $kat['anzahl'] }}</span></td>
                                            <td class="px-3 py-2.5 text-right font-bold tabular-nums {{ \App\Support\NotenSkala::text($kat['schnitt']) }}">{{ \App\Support\NotenSkala::format($kat['schnitt'], 2) }}</td>
                                            <td class="px-3 py-2.5 text-right tabular-nums text-muted">{{ \App\Support\NotenSkala::format($kat['min'], 1) }} – {{ \App\Support\NotenSkala::format($kat['max'], 1) }}</td>
                                            <td @class(['px-3 py-2.5 text-right tabular-nums', 'text-note-ungenuegend font-semibold' => $kat['ungenuegend'], 'text-muted' => ! $kat['ungenuegend']])>{{ $kat['ungenuegend'] }}</td>
                                            @if($sid)
                                                <td @class(['px-5 py-2.5 text-right tabular-nums', 'text-note-ungenuegend font-semibold' => $kat['gefaehrdet'], 'text-muted' => ! $kat['gefaehrdet']])>{{ $kat['gefaehrdet'] }}</td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </x-karte>
                </div>

                <x-karte titel="Tiefste Fächer und Module">
                    <ul class="grid md:grid-cols-2 gap-x-8 gap-y-3">
                        @foreach($schwachstellen as $s)
                            @php $breite = max(2, min(100, ($s['schnitt'] - 1) / 5 * 100)); @endphp
                            <li class="flex flex-col gap-1">
                                <div class="flex items-baseline justify-between gap-3 text-sm">
                                    <span class="truncate text-text font-medium">{{ $s['label'] }} <span class="text-xs text-muted font-normal">{{ $s['kategorie'] }}</span></span>
                                    <span class="font-bold tabular-nums {{ \App\Support\NotenSkala::text($s['schnitt']) }}">{{ \App\Support\NotenSkala::format($s['schnitt'], 2) }}</span>
                                </div>
                                <div class="h-1.5 rounded-full bg-accent/10 overflow-hidden"><div class="h-full rounded-full {{ \App\Support\NotenSkala::balken($s['schnitt']) }}" style="width: {{ $breite }}%"></div></div>
                                <div class="text-xs text-muted">{{ $s['anzahl'] }} {{ $s['anzahl'] === 1 ? 'Zeugnisnote' : 'Zeugnisnoten' }}@if($s['ungenuegend']) · <span class="text-note-ungenuegend">{{ $s['ungenuegend'] }} ungenügend</span>@endif</div>
                            </li>
                        @endforeach
                    </ul>
                </x-karte>
            @endif

            <x-karte titel="Lernende" :polster="false">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm text-text">
                        <thead class="text-muted text-xs">
                            <tr class="border-b border-border">
                                <th class="text-left px-5 py-2 font-medium"><a href="{{ $sortUrl('name') }}" class="hover:text-text">Name {{ $pfeil('name') }}</a></th>
                                <th class="text-left px-3 py-2 font-medium"><a href="{{ $sortUrl('status') }}" class="hover:text-text">Status {{ $pfeil('status') }}</a></th>
                                <th class="text-right px-3 py-2 font-medium whitespace-nowrap"><a href="{{ $sortUrl('gesamt', 'desc') }}" class="hover:text-text">Gesamt {{ $pfeil('gesamt') }}</a></th>
                                @if($sid)
                                    <th class="text-right px-3 py-2 font-medium whitespace-nowrap"><a href="{{ $sortUrl('semester', 'desc') }}" class="hover:text-text">Semester {{ $pfeil('semester') }}</a></th>
                                @endif
                                <th class="text-right px-3 py-2 font-medium whitespace-nowrap"><a href="{{ $sortUrl('ungenuegend', 'desc') }}" class="hover:text-text">Ungenügend {{ $pfeil('ungenuegend') }}</a></th>
                                <th class="text-right px-3 py-2 font-medium whitespace-nowrap"><a href="{{ $sortUrl('pruefungen', 'desc') }}" class="hover:text-text">Prüfungen {{ $pfeil('pruefungen') }}</a></th>
                                <th class="text-right px-3 py-2 font-medium whitespace-nowrap"><a href="{{ $sortUrl('letzte', 'desc') }}" class="hover:text-text">Letzte Note {{ $pfeil('letzte') }}</a></th>
                                <th class="px-5 py-2 print:hidden"><span class="sr-only">Noten</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @forelse($zeilen as $z)
                                <tr class="hover:bg-accent/5">
                                    <td class="px-5 py-2.5 whitespace-nowrap">
                                        <a href="{{ route('admin.learners.show', $z->id) }}" class="font-medium hover:text-accent">{{ $z->nachname }} {{ $z->vorname }}</a>
                                        @if($z->lehrberuf)<span class="ml-1 text-xs text-muted">{{ $z->lehrberuf }}</span>@endif
                                    </td>
                                    <td class="px-3 py-2.5">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <x-status :status="$z->stand->status" />
                                            @if($z->stand->gruende)
                                                <span class="text-xs text-muted truncate max-w-64" title="{{ implode(' · ', $z->stand->gruende) }}">{{ $z->stand->gruende[0] }}@if(count($z->stand->gruende) > 1) +{{ count($z->stand->gruende) - 1 }}@endif</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-3 py-2.5 text-right font-bold tabular-nums {{ \App\Support\NotenSkala::text($z->gesamt) }}">{{ \App\Support\NotenSkala::format($z->gesamt, 1) }}</td>
                                    @if($sid)
                                        <td class="px-3 py-2.5 text-right font-semibold tabular-nums {{ \App\Support\NotenSkala::text($z->semester) }}">{{ \App\Support\NotenSkala::format($z->semester, 1) }}</td>
                                    @endif
                                    <td @class(['px-3 py-2.5 text-right tabular-nums', 'text-note-ungenuegend font-semibold' => $z->ungenuegend, 'text-muted' => ! $z->ungenuegend])>{{ $z->ungenuegend }}</td>
                                    <td class="px-3 py-2.5 text-right tabular-nums text-muted">{{ $z->pruefungen }}</td>
                                    <td class="px-3 py-2.5 text-right tabular-nums text-muted whitespace-nowrap">{{ $z->letzte ? \Illuminate\Support\Carbon::parse($z->letzte)->format('d.m.Y') : '–' }}</td>
                                    <td class="px-5 py-2.5 text-right print:hidden">
                                        <a href="{{ route('admin.learners.grades.index', array_filter(['lernender_id' => $z->id, 'semester_id' => $sid])) }}" class="text-xs text-accent hover:underline whitespace-nowrap">Noten →</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-5 py-10 text-center text-sm text-muted">
                                        Keine Lernenden
                                        @if($filterAktiv)
                                            <a href="{{ route('admin.reports.grades') }}" class="ml-2 text-accent hover:underline">Filter zurücksetzen</a>
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
