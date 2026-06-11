<x-app-layout>
    <x-slot name="title">Berichte</x-slot>
    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-4 flex-wrap">
            <h2 class="font-semibold text-xl text-text">Schulweite Notenübersicht</h2>
            <div class="flex items-center gap-2">
                <button type="button" onclick="window.print()"
                        class="inline-flex items-center gap-1.5 px-4 py-2 h-10 rounded-xl glass-btn text-text whitespace-nowrap text-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    Drucken
                </button>
                <a href="{{ route('admin.berichte.noten.export', request()->only(['semester_id','lehrberuf_id','berufsbildner_id'])) }}"
                   class="inline-flex items-center gap-1.5 px-4 py-2 h-10 rounded-xl glass-btn text-text whitespace-nowrap text-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    CSV exportieren
                </a>
            </div>
        </div>
    </x-slot>

    <style>
        @media print {
            nav, .no-print, button[onclick*="print"] { display: none !important; }
            body { background: #fff !important; color: #000 !important; }
            .bg-card { background: #fff !important; box-shadow: none !important; }
            details[open] summary ~ * { animation: none !important; }
        }
    </style>

    <div class="py-6">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">

            {{-- Filter --}}
            <div class="glass rounded-2xl p-4">
                <form method="GET" action="{{ route('admin.berichte.noten') }}"
                      class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end">

                    <div>
                        <label class="text-sm font-medium text-muted">Semester</label>
                        <select name="semester_id" onchange="this.form.submit()"
                                class="mt-1 w-full rounded-xl border border-border bg-input text-text focus:ring-2 focus:ring-ring focus:border-ring text-sm">
                            <option value="">Alle Semester</option>
                            @foreach($semester as $s)
                                <option value="{{ $s->semester_id }}" @selected($semesterId == $s->semester_id)>
                                    {{ $s->bezeichnung }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="text-sm font-medium text-muted">Lehrberuf</label>
                        <select name="lehrberuf_id" onchange="this.form.submit()"
                                class="mt-1 w-full rounded-xl border border-border bg-input text-text focus:ring-2 focus:ring-ring focus:border-ring text-sm">
                            <option value="">Alle Lehrberufe</option>
                            @foreach($lehrberufe as $lb)
                                <option value="{{ $lb->lehrberuf_id }}" @selected($lehrberufId == $lb->lehrberuf_id)>
                                    {{ $lb->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="text-sm font-medium text-muted">Berufsbildner</label>
                        <select name="berufsbildner_id" onchange="this.form.submit()"
                                class="mt-1 w-full rounded-xl border border-border bg-input text-text focus:ring-2 focus:ring-ring focus:border-ring text-sm">
                            <option value="">Alle BB</option>
                            @foreach($berufsbildner as $bb)
                                <option value="{{ $bb->berufsbildner_id }}" @selected($berufsbildnerId == $bb->berufsbildner_id)>
                                    {{ $bb->nachname }} {{ $bb->vorname }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex gap-2">
                        <button class="px-4 py-2 h-10 rounded-xl bg-accent text-white np-btn-primary text-sm">
                            Filtern
                        </button>
                        @if($semesterId || $lehrberufId || $berufsbildnerId)
                            <a href="{{ route('admin.berichte.noten') }}"
                               class="px-4 py-2 h-10 rounded-xl glass-btn text-text text-sm flex items-center">
                                ×
                            </a>
                        @endif
                    </div>
                </form>
            </div>

            {{-- Zusammenfassung --}}
            @if($gesamtTotal > 0)
                @php
                    $avgColor = $gesamtAvg === null ? 'text-muted'
                        : ($gesamtAvg >= 5.0 ? 'text-green-600 dark:text-green-400'
                        : ($gesamtAvg >= 4.0 ? 'text-emerald-600 dark:text-emerald-400'
                        : ($gesamtAvg >= 3.5 ? 'text-yellow-600 dark:text-yellow-400'
                        : 'text-red-600 dark:text-red-400')));
                    $passRate = $gesamtTotal > 0 ? round($gesamtPassed / $gesamtTotal * 100) : null;
                @endphp
                <div class="glass rounded-2xl px-5 py-3 flex flex-wrap gap-6 text-sm">
                    <div class="text-center">
                        <div class="text-xs text-muted">Lernende</div>
                        <div class="font-bold text-text text-lg">{{ $lernende->count() }}</div>
                    </div>
                    <div class="text-center">
                        <div class="text-xs text-muted">Noten gesamt</div>
                        <div class="font-bold text-text text-lg">{{ $gesamtTotal }}</div>
                    </div>
                    <div class="text-center">
                        <div class="text-xs text-muted">Ø Schule</div>
                        <div class="font-bold text-lg {{ $avgColor }}">{{ $gesamtAvg !== null ? number_format($gesamtAvg, 2) : '–' }}</div>
                    </div>
                    <div class="text-center">
                        <div class="text-xs text-muted">Bestehensquote</div>
                        <div class="font-bold text-lg {{ $passRate !== null ? ($passRate >= 75 ? 'text-green-600 dark:text-green-400' : ($passRate >= 50 ? 'text-yellow-600 dark:text-yellow-400' : 'text-red-600 dark:text-red-400')) : 'text-muted' }}">
                            {{ $passRate !== null ? $passRate . ' %' : '–' }}
                        </div>
                    </div>
                </div>
            @endif

            {{-- Notenverteilung (Histogramm 1.0–6.0) --}}
            @php $maxBucket = $notenVerteilung->max('count'); @endphp
            @if($gesamtTotal > 0 && $maxBucket > 0)
                <div class="glass rounded-2xl overflow-hidden">
                    <div class="px-5 py-4 border-b border-border flex items-center justify-between">
                        <h3 class="font-semibold text-text">Notenverteilung</h3>
                        <span class="text-[11px] text-muted">{{ $gesamtTotal }} Noten in 0.5er-Schritten</span>
                    </div>
                    <div class="p-5">
                        <div class="flex items-end gap-1.5 h-36">
                            @foreach($notenVerteilung as $nv)
                                @php
                                    $bVal = (float) $nv->bucket;
                                    $hPct = $maxBucket > 0 ? round($nv->count / $maxBucket * 100) : 0;
                                    $barColor = $bVal >= 5.0 ? 'bg-green-500'
                                        : ($bVal >= 4.0 ? 'bg-emerald-500'
                                        : ($bVal >= 3.5 ? 'bg-yellow-500'
                                        : 'bg-red-500'));
                                @endphp
                                <div class="flex-1 flex flex-col items-center justify-end h-full gap-1"
                                     title="Note {{ $nv->bucket }}: {{ $nv->count }} {{ $nv->count === 1 ? 'Note' : 'Noten' }}">
                                    <span class="text-[10px] text-muted tabular-nums leading-none">{{ $nv->count > 0 ? $nv->count : '' }}</span>
                                    <div class="w-full rounded-t {{ $barColor }} {{ $nv->count === 0 ? 'opacity-20' : 'opacity-90' }}"
                                         style="height: {{ max($hPct, $nv->count > 0 ? 3 : 1) }}%"></div>
                                    <span class="text-[10px] text-muted tabular-nums leading-none">{{ $nv->bucket }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            {{-- Tabelle --}}
            <div class="glass rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm text-text">
                        <thead class="bg-bg text-muted">
                            <tr>
                                @php
                                    $sortLink = function ($col, $label) use ($sortBy, $sortDir) {
                                        $isActive = $sortBy === $col;
                                        $nextDir = $isActive && $sortDir === 'asc' ? 'desc' : 'asc';
                                        $arrow = !$isActive ? '<span class="text-muted/50">⇅</span>' : ($sortDir === 'asc' ? '↑' : '↓');
                                        $url = request()->fullUrlWithQuery(['sort' => $col, 'dir' => $nextDir]);
                                        return '<a href="'.$url.'" class="inline-flex items-center gap-1 hover:text-text '.($isActive ? 'text-text font-semibold' : '').'">'.$label.' '.$arrow.'</a>';
                                    };
                                @endphp
                                <th class="text-left p-3">{!! $sortLink('name', 'Lernender') !!}</th>
                                <th class="text-left p-3">Lehrberuf</th>
                                <th class="text-center p-3 whitespace-nowrap">{!! $sortLink('total', 'Noten') !!}</th>
                                <th class="text-center p-3 whitespace-nowrap">{!! $sortLink('avg', 'Ø gewichtet') !!}</th>
                                <th class="text-center p-3 whitespace-nowrap">Bestanden</th>
                                <th class="text-center p-3 whitespace-nowrap">{!! $sortLink('quote', 'Quote') !!}</th>
                                <th class="text-center p-3 whitespace-nowrap">{!! $sortLink('last', 'Letzte Note') !!}</th>
                                <th class="text-right p-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @forelse($lernende as $l)
                                @php
                                    $s = $stats->get((int) $l->lernender_id);
                                    $total  = (int) ($s?->total  ?? 0);
                                    $passed = (int) ($s?->passed ?? 0);
                                    $avg    = $s?->avg_weighted !== null ? (float) $s->avg_weighted : null;
                                    $quote  = $total > 0 ? round($passed / $total * 100) : null;
                                    $last   = $s?->last_entry ? \Carbon\Carbon::parse($s->last_entry) : null;

                                    $avgColor = $avg === null ? 'text-muted'
                                        : ($avg >= 5.0 ? 'text-green-600 dark:text-green-400'
                                        : ($avg >= 4.0 ? 'text-emerald-600 dark:text-emerald-400'
                                        : ($avg >= 3.5 ? 'text-yellow-600 dark:text-yellow-400'
                                        : 'text-red-600 dark:text-red-400')));
                                    $quoteColor = $quote === null ? 'text-muted'
                                        : ($quote >= 75 ? 'text-green-600 dark:text-green-400'
                                        : ($quote >= 50 ? 'text-yellow-600 dark:text-yellow-400'
                                        : 'text-red-600 dark:text-red-400'));
                                @endphp
                                <tr class="hover:bg-bg">
                                    <td class="p-3">
                                        <a href="{{ route('admin.lernende.show', $l->lernender_id) }}"
                                           class="font-medium hover:text-accent">
                                            {{ $l->nachname }} {{ $l->vorname }}
                                        </a>
                                    </td>
                                    <td class="p-3 text-muted">
                                        @if($l->lehrberuf)
                                            {{ $l->lehrberuf }}
                                            @if($l->kuerzel)
                                                <span class="text-xs">({{ $l->kuerzel }})</span>
                                            @endif
                                        @else
                                            <span class="italic">–</span>
                                        @endif
                                    </td>
                                    <td class="p-3 text-center">
                                        @if($total > 0)
                                            {{ $total }}
                                        @else
                                            <span class="text-muted">0</span>
                                        @endif
                                    </td>
                                    <td class="p-3 text-center font-semibold {{ $avgColor }}">
                                        {{ $avg !== null ? number_format($avg, 2) : '–' }}
                                    </td>
                                    <td class="p-3 text-center text-muted">
                                        @if($total > 0)
                                            {{ $passed }} / {{ $total }}
                                        @else
                                            –
                                        @endif
                                    </td>
                                    <td class="p-3 text-center font-semibold {{ $quoteColor }}">
                                        {{ $quote !== null ? $quote . ' %' : '–' }}
                                    </td>
                                    <td class="p-3 text-center text-muted whitespace-nowrap">
                                        {{ $last ? $last->format('d.m.Y') : '–' }}
                                    </td>
                                    <td class="p-3 text-right">
                                        <a href="{{ route('admin.lernende.noten.index', array_merge(['lernender_id' => $l->lernender_id], $semesterId ? ['semester_id' => $semesterId] : [])) }}"
                                           class="text-xs text-accent hover:underline whitespace-nowrap">
                                            Noten →
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="p-10 text-center">
                                        <svg class="mx-auto w-12 h-12 text-muted/30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        <p class="mt-3 text-sm text-muted">Keine Lernenden für diese Filter gefunden.</p>
                                        @if($semesterId || $lehrberufId || $berufsbildnerId)
                                            <a href="{{ route('admin.berichte.noten') }}" class="mt-3 inline-block text-xs text-accent hover:underline">Filter zurücksetzen</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($lernende->isNotEmpty())
                    <div class="px-4 py-2 border-t border-border text-xs text-muted">
                        {{ $lernende->count() }} Lernende
                    </div>
                @endif
            </div>

            {{-- Kategorie-Übersicht --}}
            @if($kategorieStats->isNotEmpty())
                <div class="glass rounded-2xl overflow-hidden">
                    <div class="px-5 py-4 border-b border-border">
                        <h3 class="font-semibold text-text">Übersicht nach Kategorie</h3>
                        <p class="text-xs text-muted mt-0.5">Aggregiert über alle angezeigten Lernenden{{ $semesterId ? ' im gewählten Semester' : '' }}</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm text-text">
                            <thead class="bg-bg text-muted">
                                <tr>
                                    <th class="text-left p-3">Kategorie</th>
                                    <th class="text-center p-3 whitespace-nowrap">Noten</th>
                                    <th class="text-center p-3 whitespace-nowrap">Ø gewichtet</th>
                                    <th class="text-center p-3 whitespace-nowrap">Bestanden</th>
                                    <th class="text-center p-3 whitespace-nowrap">Quote</th>
                                    <th class="text-center p-3 whitespace-nowrap">Min / Max</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                @foreach($kategorieStats as $ks)
                                    @php
                                        $ksAvg    = $ks->avg_weighted !== null ? (float) $ks->avg_weighted : null;
                                        $ksQuote  = $ks->total > 0 ? round($ks->passed / $ks->total * 100) : null;
                                        $ksAvgCol = $ksAvg === null ? 'text-muted'
                                            : ($ksAvg >= 5.0 ? 'text-green-600 dark:text-green-400'
                                            : ($ksAvg >= 4.0 ? 'text-emerald-600 dark:text-emerald-400'
                                            : ($ksAvg >= 3.5 ? 'text-yellow-600 dark:text-yellow-400'
                                            : 'text-red-600 dark:text-red-400')));
                                        $ksQCol   = $ksQuote === null ? 'text-muted'
                                            : ($ksQuote >= 75 ? 'text-green-600 dark:text-green-400'
                                            : ($ksQuote >= 50 ? 'text-yellow-600 dark:text-yellow-400'
                                            : 'text-red-600 dark:text-red-400'));
                                    @endphp
                                    <tr class="hover:bg-bg">
                                        <td class="p-3 font-medium">{{ $ks->kategorie_name }}</td>
                                        <td class="p-3 text-center text-muted">{{ $ks->total }}</td>
                                        <td class="p-3 text-center font-semibold {{ $ksAvgCol }}">
                                            {{ $ksAvg !== null ? number_format($ksAvg, 2) : '–' }}
                                        </td>
                                        <td class="p-3 text-center text-muted">
                                            {{ $ks->passed }} / {{ $ks->total }}
                                        </td>
                                        <td class="p-3 text-center font-semibold {{ $ksQCol }}">
                                            {{ $ksQuote !== null ? $ksQuote . ' %' : '–' }}
                                        </td>
                                        <td class="p-3 text-center text-muted">
                                            {{ number_format((float)$ks->note_min, 1) }} / {{ number_format((float)$ks->note_max, 1) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
