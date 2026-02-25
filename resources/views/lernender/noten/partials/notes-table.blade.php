{{-- resources/views/lernender/noten/index.blade.php --}}
<x-app-layout>
    @php
        // ---------- Pagination vs Collection (robust) ----------
        $notesPaginator = null;

        if ($notes instanceof \Illuminate\Pagination\LengthAwarePaginator || $notes instanceof \Illuminate\Pagination\Paginator) {
            $notesPaginator = $notes;
            $notes = $notesPaginator->getCollection();
        } elseif ($notes instanceof \Illuminate\Support\Collection) {
            // ok
        } else {
            $notes = collect($notes ?? []);
        }

        // ---------- Semester Auswahl ----------
        $selectedSemesterId = request('semester_id');
        if (!$selectedSemesterId && isset($semester) && $semester->count() > 0) {
            $selectedSemesterId = (string) $semester->last()->semester_id;
        }

        $semIndex = null;
        if (!empty($selectedSemesterId) && isset($semester)) {
            $semIndex = $semester->values()->search(fn($s) => (string)$s->semester_id === (string)$selectedSemesterId);
        }

        $prevSem = ($semIndex !== null && $semIndex !== false && $semIndex > 0) ? $semester->values()[$semIndex - 1] : null;
        $nextSem = ($semIndex !== null && $semIndex !== false && $semIndex < ($semester->count() - 1)) ? $semester->values()[$semIndex + 1] : null;

        $selectedSemLabel = $semester?->firstWhere('semester_id', (int)$selectedSemesterId)?->bezeichnung ?? 'Semester';

        $queryBase = request()->except('page');
        $queryWith = function(array $extra) use ($queryBase) {
            return array_merge($queryBase, $extra);
        };

        // ---------- Gewichteter Schnitt (null/leer -> 100) ----------
        $weightedAvg = function($items) {
            $wSum = 0.0; $sum = 0.0;
            foreach ($items as $n) {
                $w = $n->gewichtung_prozent;
                if ($w === null || $w === '') $w = 100.0;
                $w = (float)$w;
                $wSum += $w;
                $sum  += ((float)$n->note_wert) * $w;
            }
            return $wSum > 0 ? round($sum / $wSum, 2) : null;
        };

        // ---------- Gruppieren: Fächer / Module ----------
        $fachGroups = $notes
            ->filter(fn($n) => !empty($n->fach_id) && $n->fach)
            ->groupBy(fn($n) => $n->fach->fach_id);

        $modulGroups = $notes
            ->filter(fn($n) => empty($n->fach_id) && $n->modulBelegung && $n->modulBelegung->modul)
            ->groupBy(fn($n) => $n->modulBelegung->modul->modul_id);
    @endphp

    <x-slot name="header">
        {{-- 1 Zeile / 3 Spalten, ohne Umbruch --}}
        <div class="w-full flex items-center justify-between gap-4">
            <div class="flex-none">
                <h2 class="font-semibold text-xl text-gray-900 dark:text-gray-100 whitespace-nowrap">
                    Meine Noten
                </h2>
            </div>

            <div class="flex-1 flex items-center justify-center gap-2 min-w-0">
                <a
                    @class([
                        'inline-flex items-center justify-center shrink-0',
                        'w-12 h-10 rounded-xl border',
                        'border-gray-300 text-gray-700 hover:bg-gray-100',
                        'dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800',
                        !$prevSem ? 'pointer-events-none opacity-40' : ''
                    ])
                    href="{{ $prevSem ? route('lernender.noten.index', $queryWith(['semester_id' => $prevSem->semester_id])) : '#' }}"
                    title="Vorheriges Semester"
                    aria-label="Vorheriges Semester"
                >
                    <span class="text-2xl leading-none">‹</span>
                </a>

                <div class="px-4 py-2 h-10 flex items-center rounded-xl border border-gray-300 dark:border-gray-700
                            bg-white/80 dark:bg-gray-900/70 text-gray-900 dark:text-gray-100 text-sm font-semibold
                            whitespace-nowrap">
                    {{ $selectedSemLabel }}
                </div>

                <a
                    @class([
                        'inline-flex items-center justify-center shrink-0',
                        'w-12 h-10 rounded-xl border',
                        'border-gray-300 text-gray-700 hover:bg-gray-100',
                        'dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800',
                        !$nextSem ? 'pointer-events-none opacity-40' : ''
                    ])
                    href="{{ $nextSem ? route('lernender.noten.index', $queryWith(['semester_id' => $nextSem->semester_id])) : '#' }}"
                    title="Nächstes Semester"
                    aria-label="Nächstes Semester"
                >
                    <span class="text-2xl leading-none">›</span>
                </a>
            </div>

            <div class="flex-none">
                <a href="{{ route('lernender.noten.create') }}"
                   class="inline-flex items-center gap-2 px-4 py-2 h-10 rounded-xl bg-blue-600 text-white hover:bg-blue-700 shadow-sm whitespace-nowrap">
                    <span class="text-lg leading-none">+</span>
                    Neue Note
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-5">

            @if (session('status'))
                <div class="rounded-xl border border-green-200 bg-green-50 text-green-800 px-4 py-3
                            dark:border-green-900/40 dark:bg-green-900/30 dark:text-green-200">
                    {{ session('status') }}
                </div>
            @endif

            {{-- Filter + Summary: gleiche Zeile, zwei Cards (2/3 + 1/3) --}}
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
                {{-- Filter (2/3) --}}
                <div class="lg:col-span-8">
                    <div class="bg-white dark:bg-gray-800/70 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-sm p-4">
                        <div class="flex items-center justify-between mb-3">
                            <div class="font-semibold text-gray-900 dark:text-gray-100">Filter</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">Semester bleibt aktiv</div>
                        </div>

                        <form method="GET" action="{{ route('lernender.noten.index') }}" class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                            <input type="hidden" name="semester_id" value="{{ $selectedSemesterId }}">

                            <div class="md:col-span-8">
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Kategorie</label>
                                <select name="kategorie_id"
                                        class="mt-1 w-full rounded-xl border border-gray-300 dark:border-gray-700
                                               bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100
                                               focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">Alle</option>
                                    @foreach($kategorien as $k)
                                        <option value="{{ $k->kategorie_id }}" @selected(request('kategorie_id') == $k->kategorie_id)>
                                            {{ $k->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="md:col-span-4 flex gap-2">
                                <button
                                    class="w-full px-4 py-2 h-10 rounded-xl bg-gray-900 text-white hover:bg-black
                                           dark:bg-gray-700 dark:hover:bg-gray-600 shadow-sm">
                                    Anwenden
                                </button>

                                <a href="{{ route('lernender.noten.index', ['semester_id' => $selectedSemesterId]) }}"
                                   class="w-full px-4 py-2 h-10 rounded-xl bg-gray-100 text-gray-900 hover:bg-gray-200
                                          dark:bg-gray-900 dark:text-gray-100 dark:hover:bg-gray-950 text-center shadow-sm">
                                    Reset
                                </a>
                            </div>

                            @if(($missingWeights ?? 0) > 0)
                                <div class="md:col-span-12 rounded-xl border border-yellow-200 bg-yellow-50 text-yellow-800 px-4 py-2
                                            dark:border-yellow-900/40 dark:bg-yellow-900/30 dark:text-yellow-200 text-sm">
                                    Hinweis: {{ $missingWeights }} Note(n) ohne Gewichtung werden logisch mit 100% behandelt.
                                </div>
                            @endif
                        </form>
                    </div>
                </div>

                {{-- Summary (1/3) --}}
                <div class="lg:col-span-4">
                    <div class="bg-white dark:bg-gray-800/70 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-sm p-4 h-full">
                        <div class="font-semibold text-gray-900 dark:text-gray-100 mb-3">Summary</div>

                        <div class="grid grid-cols-3 lg:grid-cols-1 gap-3">
                            <div class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/60 p-3">
                                <div class="text-[11px] text-gray-600 dark:text-gray-300">Noten</div>
                                <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $count }}</div>
                            </div>

                            <div class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/60 p-3">
                                <div class="text-[11px] text-gray-600 dark:text-gray-300">Ø ungewichtet</div>
                                <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $avgUnweighted ?? '-' }}</div>
                            </div>

                            <div class="rounded-2xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/60 p-3">
                                <div class="text-[11px] text-gray-600 dark:text-gray-300">Ø gewichtet</div>
                                <div class="mt-1 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ $avgWeighted ?? '-' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Fächer --}}
            <div class="space-y-2">
                <div class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400 px-1">Fächer</div>

                @forelse($fachGroups as $fachId => $items)
                    @php
                        $fachName = $items->first()->fach->name ?? 'Fach';
                        $avg = $weightedAvg($items);
                    @endphp

                    <details class="group bg-white dark:bg-gray-800/70 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-sm overflow-hidden">
                        <summary class="cursor-pointer select-none px-4 py-3 flex items-center justify-between
                                        hover:bg-gray-50/80 dark:hover:bg-gray-700/30 list-none">
                            <div class="flex items-center gap-3 min-w-0">
                                <span class="text-gray-400 transition-transform duration-200 group-open:rotate-90 shrink-0">
                                    <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L10.94 10 7.23 6.29a.75.75 0 1 1 1.06-1.06l4.24 4.24c.3.3.3.77 0 1.06l-4.24 4.24a.75.75 0 0 1-1.06.02z" clip-rule="evenodd"/>
                                    </svg>
                                </span>

                                <div class="min-w-0">
                                    <div class="font-semibold text-gray-900 dark:text-gray-100 truncate">{{ $fachName }}</div>
                                    <div class="text-xs text-gray-600 dark:text-gray-300">{{ $items->count() }} Note(n)</div>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 shrink-0">
                                <span class="text-xs text-gray-500 dark:text-gray-400">Ø</span>
                                <span class="inline-flex items-center justify-center min-w-[4rem] px-3 py-1 rounded-xl
                                             bg-gray-900 text-white dark:bg-gray-950 shadow-sm">
                                    {{ $avg ?? '-' }}
                                </span>
                            </div>
                        </summary>

                        <div class="border-t border-gray-200 dark:border-gray-700">
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-sm text-gray-900 dark:text-gray-100">
                                    <thead class="bg-gray-50 dark:bg-gray-900/60 text-gray-700 dark:text-gray-200">
                                        <tr>
                                            <th class="text-left p-3 whitespace-nowrap">Datum</th>
                                            <th class="text-left p-3">Titel</th>
                                            <th class="text-right p-3 whitespace-nowrap">Gew. %</th>
                                            <th class="text-right p-3 whitespace-nowrap">Note</th>
                                            <th class="text-right p-3 whitespace-nowrap">Aktionen</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                        @foreach($items as $n)
                                            <tr class="hover:bg-gray-50/70 dark:hover:bg-gray-700/20">
                                                <td class="p-3 whitespace-nowrap">{{ optional($n->pruefungsdatum)->format('d.m.Y') }}</td>
                                                <td class="p-3">{{ $n->titel ?? '-' }}</td>
                                                <td class="p-3 text-right whitespace-nowrap">{{ $n->gewichtung_prozent ?? 100 }}</td>
                                                <td class="p-3 text-right font-semibold whitespace-nowrap">{{ $n->note_wert }}</td>
                                                <td class="p-3 text-right whitespace-nowrap">
                                                    <a class="text-blue-600 hover:underline" href="{{ route('lernender.noten.edit', $n->note_id) }}">Bearbeiten</a>
                                                    <form method="POST" action="{{ route('lernender.noten.destroy', $n->note_id) }}" class="inline"
                                                          onsubmit="return confirm('Note wirklich löschen?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button class="text-red-600 hover:underline ms-3">Löschen</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </details>
                @empty
                    <div class="text-sm text-gray-600 dark:text-gray-300 px-1">Keine Fachnoten gefunden.</div>
                @endforelse
            </div>

            {{-- Module --}}
            <div class="space-y-2 pt-2">
                <div class="text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400 px-1">Module</div>

                @forelse($modulGroups as $modulId => $items)
                    @php
                        $m = $items->first()->modulBelegung->modul ?? null;
                        $modulTitle = $m ? ($m->modul_nummer . ' – ' . $m->titel) : 'Modul';
                        $avg = $weightedAvg($items);
                    @endphp

                    <details class="group bg-white dark:bg-gray-800/70 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-sm overflow-hidden">
                        <summary class="cursor-pointer select-none px-4 py-3 flex items-center justify-between
                                        hover:bg-gray-50/80 dark:hover:bg-gray-700/30 list-none">
                            <div class="flex items-center gap-3 min-w-0">
                                <span class="text-gray-400 transition-transform duration-200 group-open:rotate-90 shrink-0">
                                    <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L10.94 10 7.23 6.29a.75.75 0 1 1 1.06-1.06l4.24 4.24c.3.3.3.77 0 1.06l-4.24 4.24a.75.75 0 0 1-1.06.02z" clip-rule="evenodd"/>
                                    </svg>
                                </span>

                                <div class="min-w-0">
                                    <div class="font-semibold text-gray-900 dark:text-gray-100 truncate">{{ $modulTitle }}</div>
                                    <div class="text-xs text-gray-600 dark:text-gray-300">{{ $items->count() }} Note(n)</div>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 shrink-0">
                                <span class="text-xs text-gray-500 dark:text-gray-400">Ø</span>
                                <span class="inline-flex items-center justify-center min-w-[4rem] px-3 py-1 rounded-xl
                                             bg-gray-900 text-white dark:bg-gray-950 shadow-sm">
                                    {{ $avg ?? '-' }}
                                </span>
                            </div>
                        </summary>

                        <div class="border-t border-gray-200 dark:border-gray-700">
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-sm text-gray-900 dark:text-gray-100">
                                    <thead class="bg-gray-50 dark:bg-gray-900/60 text-gray-700 dark:text-gray-200">
                                        <tr>
                                            <th class="text-left p-3 whitespace-nowrap">Datum</th>
                                            <th class="text-left p-3">Titel</th>
                                            <th class="text-right p-3 whitespace-nowrap">Gew. %</th>
                                            <th class="text-right p-3 whitespace-nowrap">Note</th>
                                            <th class="text-right p-3 whitespace-nowrap">Aktionen</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                        @foreach($items as $n)
                                            <tr class="hover:bg-gray-50/70 dark:hover:bg-gray-700/20">
                                                <td class="p-3 whitespace-nowrap">{{ optional($n->pruefungsdatum)->format('d.m.Y') }}</td>
                                                <td class="p-3">{{ $n->titel ?? '-' }}</td>
                                                <td class="p-3 text-right whitespace-nowrap">{{ $n->gewichtung_prozent ?? 100 }}</td>
                                                <td class="p-3 text-right font-semibold whitespace-nowrap">{{ $n->note_wert }}</td>
                                                <td class="p-3 text-right whitespace-nowrap">
                                                    <a class="text-blue-600 hover:underline" href="{{ route('lernender.noten.edit', $n->note_id) }}">Bearbeiten</a>
                                                    <form method="POST" action="{{ route('lernender.noten.destroy', $n->note_id) }}" class="inline"
                                                          onsubmit="return confirm('Note wirklich löschen?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button class="text-red-600 hover:underline ms-3">Löschen</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </details>
                @empty
                    <div class="text-sm text-gray-600 dark:text-gray-300 px-1">Keine Modulnoten gefunden.</div>
                @endforelse
            </div>

            @if($notesPaginator)
                <div class="bg-white dark:bg-gray-800/70 border border-gray-200 dark:border-gray-700 rounded-2xl shadow-sm p-3">
                    {{ $notesPaginator->links() }}
                </div>
            @endif

        </div>
    </div>

    <style>
        summary::-webkit-details-marker { display: none; }
        summary { list-style: none; }
    </style>
</x-app-layout>
