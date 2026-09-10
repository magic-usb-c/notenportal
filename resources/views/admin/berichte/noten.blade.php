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
        <div class="w-full flex items-center justify-between gap-4 flex-wrap">
            <h2 class="font-semibold text-xl text-text">Notenbericht <span class="text-muted font-normal">· {{ $semesterName }}</span></h2>
            <div class="flex items-center gap-2 print:hidden">
                <button type="button" onclick="window.print()" class="inline-flex items-center px-4 h-10 rounded-xl glass-btn text-text text-sm">Drucken</button>
                <a href="{{ route('admin.berichte.noten.export', request()->only(['semester', 'lehrberuf_id', 'berufsbildner_id'])) }}"
                   class="inline-flex items-center px-4 h-10 rounded-xl glass-btn text-text text-sm">CSV</a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col gap-5">

            <form method="GET" action="{{ route('admin.berichte.noten') }}" class="glass rounded-2xl p-4 grid grid-cols-1 sm:grid-cols-[1fr_1fr_1fr_auto] gap-3 items-end print:hidden">
                <input type="hidden" name="sort" value="{{ $sort }}">
                <input type="hidden" name="dir" value="{{ $dir }}">
                <div>
                    <label for="semester" class="text-xs uppercase tracking-widest text-muted font-medium">Zeitraum</label>
                    <select name="semester" id="semester" onchange="this.form.submit()" class="mt-1 w-full rounded-xl border border-border bg-input text-text focus:ring-2 focus:ring-ring focus:border-ring text-sm">
                        <option value="alle" @selected($sid === null)>Ganze Lehrzeit</option>
                        @foreach($semester as $s)
                            <option value="{{ $s->semester_id }}" @selected($sid === (int) $s->semester_id)>{{ $s->bezeichnung }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="lehrberuf_id" class="text-xs uppercase tracking-widest text-muted font-medium">Lehrberuf</label>
                    <select name="lehrberuf_id" id="lehrberuf_id" onchange="this.form.submit()" class="mt-1 w-full rounded-xl border border-border bg-input text-text focus:ring-2 focus:ring-ring focus:border-ring text-sm">
                        <option value="">Alle Lehrberufe</option>
                        @foreach($lehrberufe as $lb)
                            <option value="{{ $lb->lehrberuf_id }}" @selected($filter['lehrberuf_id'] === (int) $lb->lehrberuf_id)>{{ $lb->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="berufsbildner_id" class="text-xs uppercase tracking-widest text-muted font-medium">Berufsbildner</label>
                    <select name="berufsbildner_id" id="berufsbildner_id" onchange="this.form.submit()" class="mt-1 w-full rounded-xl border border-border bg-input text-text focus:ring-2 focus:ring-ring focus:border-ring text-sm">
                        <option value="">Alle</option>
                        @foreach($berufsbildner as $bb)
                            <option value="{{ $bb->berufsbildner_id }}" @selected($filter['berufsbildner_id'] === (int) $bb->berufsbildner_id)>{{ $bb->nachname }} {{ $bb->vorname }}</option>
                        @endforeach
                    </select>
                </div>
                @if($filterAktiv)
                    <a href="{{ route('admin.berichte.noten') }}" class="inline-flex items-center justify-center px-4 h-10 rounded-xl glass-btn text-text text-sm" aria-label="Filter zurücksetzen">×</a>
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
                    <x-karte titel="Verteilung der Zeugnisnoten">
                        <div class="h-56" x-data="npChart('saeulen', {{ \Illuminate\Support\Js::from($verteilung) }})"><canvas x-ref="canvas" aria-label="Verteilung der Zeugnisnoten" role="img"></canvas></div>
                    </x-karte>

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
                                            <td @class(['px-3 py-2.5 text-right tabular-nums', 'text-red-600 dark:text-red-400 font-semibold' => $kat['ungenuegend'], 'text-muted' => ! $kat['ungenuegend']])>{{ $kat['ungenuegend'] }}</td>
                                            @if($sid)
                                                <td @class(['px-5 py-2.5 text-right tabular-nums', 'text-red-600 dark:text-red-400 font-semibold' => $kat['gefaehrdet'], 'text-muted' => ! $kat['gefaehrdet']])>{{ $kat['gefaehrdet'] }}</td>
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
                                <div class="text-xs text-muted">{{ $s['anzahl'] }} {{ $s['anzahl'] === 1 ? 'Zeugnisnote' : 'Zeugnisnoten' }}@if($s['ungenuegend']) · <span class="text-red-600 dark:text-red-400">{{ $s['ungenuegend'] }} ungenügend</span>@endif</div>
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
                                        <a href="{{ route('admin.lernende.show', $z->id) }}" class="font-medium hover:text-accent">{{ $z->nachname }} {{ $z->vorname }}</a>
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
                                    <td @class(['px-3 py-2.5 text-right tabular-nums', 'text-red-600 dark:text-red-400 font-semibold' => $z->ungenuegend, 'text-muted' => ! $z->ungenuegend])>{{ $z->ungenuegend }}</td>
                                    <td class="px-3 py-2.5 text-right tabular-nums text-muted">{{ $z->pruefungen }}</td>
                                    <td class="px-3 py-2.5 text-right tabular-nums text-muted whitespace-nowrap">{{ $z->letzte ? \Illuminate\Support\Carbon::parse($z->letzte)->format('d.m.Y') : '–' }}</td>
                                    <td class="px-5 py-2.5 text-right print:hidden">
                                        <a href="{{ route('admin.lernende.noten.index', array_filter(['lernender_id' => $z->id, 'semester_id' => $sid])) }}" class="text-xs text-accent hover:underline whitespace-nowrap">Noten →</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-5 py-10 text-center text-sm text-muted">
                                        Keine Lernenden
                                        @if($filterAktiv)
                                            <a href="{{ route('admin.berichte.noten') }}" class="ml-2 text-accent hover:underline">Filter zurücksetzen</a>
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
