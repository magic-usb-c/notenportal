{{-- resources/views/lernender/noten/index.blade.php --}}
<x-app-layout>
    @php
        // ---------- Semester-Label für Anzeige (selectedSemesterId kommt vom Controller) ----------
        $selectedSemLabel = $semester?->firstWhere('semester_id', (int)$selectedSemesterId)?->bezeichnung ?? 'Semester';

        // Hilfsfunktion: aktuellen Query mit überschriebenen Parametern bauen
        $queryBase = request()->except('page');
        $queryWith = fn(array $extra) => array_merge($queryBase, $extra);

        // ---------- Gewichteter Schnitt pro Gruppe (null/leer → 100%) ----------
        $weightedAvg = function($items) {
            $wSum = 0.0; $sum = 0.0;
            foreach ($items as $n) {
                $w = ($n->gewichtung_prozent === null || $n->gewichtung_prozent === '') ? 100.0 : (float)$n->gewichtung_prozent;
                $wSum += $w;
                $sum  += (float)$n->note_wert * $w;
            }
            return $wSum > 0 ? round($sum / $wSum, 2) : null;
        };

        // ---------- Gruppieren: Fächer nach fach_id, Module nach modul_belegung_id ----------
        // Gruppierung nach modul_belegung_id (nicht modul_id), damit mehrere Belegungen desselben Moduls
        // korrekt getrennt dargestellt werden.
        $fachGroups = $notes
            ->filter(fn($n) => !empty($n->fach_id) && $n->fach)
            ->groupBy(fn($n) => $n->fach->fach_id);

        $modulGroups = $notes
            ->filter(fn($n) => !empty($n->modul_belegung_id) && $n->modulBelegung && $n->modulBelegung->modul)
            ->groupBy(fn($n) => $n->modul_belegung_id);

        // Lernender darf eigene Noten verwalten
        $canManage  = true;
        $editUrl    = fn(int $id) => route('lernender.noten.edit', $id);
        $destroyUrl = fn(int $id) => route('lernender.noten.destroy', $id);
    @endphp

    <x-slot name="header">
        {{-- 1 Zeile / 3 Bereiche --}}
        <div class="w-full flex items-center justify-between gap-4">
            <div class="flex-none">
                <h2 class="font-semibold text-xl text-text whitespace-nowrap">Meine Noten</h2>
            </div>

            <div class="flex-1 flex items-center justify-center gap-2 min-w-0">
                <a
                    @class([
                        'inline-flex items-center justify-center shrink-0 w-12 h-10 rounded-xl border border-border',
                        'text-text hover:bg-card/60',
                        !$prevSemesterId ? 'pointer-events-none opacity-40' : ''
                    ])
                    href="{{ $prevSemesterId ? route('lernender.noten.index', $queryWith(['semester_id' => $prevSemesterId])) : '#' }}"
                    title="Vorheriges Semester"
                >
                    <span class="text-2xl leading-none">‹</span>
                </a>

                <div class="px-4 py-2 h-10 flex items-center rounded-xl border border-border bg-card text-text text-sm font-semibold whitespace-nowrap">
                    {{ $selectedSemLabel }}
                </div>

                <a
                    @class([
                        'inline-flex items-center justify-center shrink-0 w-12 h-10 rounded-xl border border-border',
                        'text-text hover:bg-card/60',
                        !$nextSemesterId ? 'pointer-events-none opacity-40' : ''
                    ])
                    href="{{ $nextSemesterId ? route('lernender.noten.index', $queryWith(['semester_id' => $nextSemesterId])) : '#' }}"
                    title="Nächstes Semester"
                >
                    <span class="text-2xl leading-none">›</span>
                </a>
            </div>

            <div class="flex-none flex gap-2">
                <a href="{{ route('lernender.noten.drucken') }}"
                   target="_blank"
                   class="inline-flex items-center gap-1.5 px-3 py-2 h-10 rounded-xl bg-card text-text border border-border hover:bg-bg whitespace-nowrap text-sm"
                   title="Notenblatt drucken">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    <span class="hidden sm:inline">Drucken</span>
                </a>
                <a href="{{ route('lernender.noten.export') }}"
                   class="inline-flex items-center gap-1.5 px-3 py-2 h-10 rounded-xl bg-card text-text border border-border hover:bg-bg whitespace-nowrap text-sm"
                   title="Noten als CSV exportieren">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    <span class="hidden sm:inline">CSV</span>
                </a>
                <a href="{{ route('lernender.noten.rechner') }}"
                   class="inline-flex items-center gap-1.5 px-3 py-2 h-10 rounded-xl bg-card text-text border border-border hover:bg-bg whitespace-nowrap text-sm"
                   title="Noten-Rechner: Welche Note brauche ich?">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m-6 4h6m-6 4h4m5 4H5a2 2 0 01-2-2V5a2 2 0 012-2h10l4 4v11a2 2 0 01-2 2z"/>
                    </svg>
                    <span class="hidden sm:inline">Rechner</span>
                </a>
                <a href="{{ route('lernender.noten.create') }}"
                   class="inline-flex items-center gap-2 px-4 py-2 h-10 rounded-xl bg-accent text-white hover:opacity-90 whitespace-nowrap">
                    <span class="text-lg leading-none">+</span>
                    Neue Note
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-5">

            @if (session('status'))
                <div class="rounded-xl border border-border bg-card p-3 text-text" data-autohide>
                    {{ session('status') }}
                </div>
            @endif

            @php
                $avgColor = function ($val) {
                    if ($val === null || $val === '') return 'text-muted';
                    $v = (float) $val;
                    if ($v >= 5.0) return 'text-green-600 dark:text-green-400';
                    if ($v >= 4.0) return 'text-emerald-600 dark:text-emerald-400';
                    if ($v >= 3.5) return 'text-yellow-600 dark:text-yellow-400';
                    return 'text-red-600 dark:text-red-400';
                };
            @endphp

            {{-- Filter + Summary --}}
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 items-stretch">
                {{-- Filter --}}
                <div class="lg:col-span-8">
                    <div class="bg-card border border-border rounded-2xl shadow-sm p-4 h-full">
                        <form method="GET" action="{{ route('lernender.noten.index') }}" class="h-full flex flex-col gap-3">
                            <input type="hidden" name="semester_id" value="{{ $selectedSemesterId }}">

                            <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                                <div class="md:col-span-8 min-w-0">
                                    <label class="text-sm font-medium text-muted">Kategorie</label>
                                    <select name="kategorie_id"
                                            class="mt-1 w-full min-w-0 rounded-xl border border-border bg-input text-text
                                                   focus:ring-2 focus:ring-ring focus:border-ring">
                                        <option value="">Alle</option>
                                        @foreach($kategorien as $k)
                                            <option value="{{ $k->kategorie_id }}" @selected(request('kategorie_id') == $k->kategorie_id)>
                                                {{ $k->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="md:col-span-4 flex gap-2 justify-end flex-nowrap">
                                    <button class="px-4 py-2 h-10 rounded-xl bg-accent text-white hover:opacity-90 whitespace-nowrap">
                                        Anwenden
                                    </button>

                                    <a href="{{ route('lernender.noten.index', ['semester_id' => $selectedSemesterId]) }}"
                                       class="px-4 py-2 h-10 rounded-xl bg-card text-text border border-border hover:bg-card/60 text-center whitespace-nowrap">
                                        Reset
                                    </a>
                                </div>
                            </div>

                            @if(($missingWeights ?? 0) > 0)
                                <div class="rounded-xl border border-border bg-card p-3 text-sm text-muted">
                                    Hinweis: {{ $missingWeights }} Note(n) ohne Gewichtung werden logisch mit 100% behandelt.
                                </div>
                            @endif

                            <div class="flex-1"></div>
                        </form>
                    </div>
                </div>

                {{-- Summary --}}
                <div class="lg:col-span-4">
                    <div class="relative overflow-hidden bg-card border border-border rounded-2xl shadow-sm p-4 h-full grid grid-cols-2 gap-2 items-center text-center np-card-lift">
                        <div class="absolute -right-4 -top-4 w-20 h-20 rounded-full bg-accent/5 pointer-events-none"></div>
                        <div class="relative flex flex-col items-center justify-center">
                            <div class="text-[11px] uppercase tracking-widest text-muted font-medium">Ø Semester</div>
                            <div class="text-4xl font-extrabold tracking-tight tabular-nums mt-1 {{ $avgColor($avgWeighted) }}">
                                {{ $avgWeighted ?? '–' }}
                            </div>
                            <div class="text-[11px] text-muted mt-0.5">
                                {{ $count }} {{ $count === 1 ? 'Note' : 'Noten' }}
                            </div>
                        </div>
                        <div class="relative flex flex-col items-center justify-center border-l border-border">
                            <div class="text-[11px] uppercase tracking-widest text-muted font-medium">Ø gesamt</div>
                            <div class="text-4xl font-extrabold tracking-tight tabular-nums mt-1 {{ $avgColor($globalAvgWeighted ?? null) }}">
                                {{ $globalAvgWeighted ?? '–' }}
                            </div>
                            <div class="text-[11px] text-muted mt-0.5">
                                {{ $globalCount ?? 0 }} {{ ($globalCount ?? 0) === 1 ? 'Note' : 'Noten' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Kategorie-Übersicht (Stats getrennt pro Kategorie für gewähltes Semester) --}}
            @if($kategorieStats->isNotEmpty())
                <div class="bg-card border border-border rounded-2xl shadow-sm overflow-hidden">
                    <div class="px-5 py-3 border-b border-border flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-text">Auswertung nach Kategorie</h3>
                        <span class="text-[11px] text-muted">{{ $selectedSemLabel }}</span>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-4 divide-x divide-y sm:divide-y-0 divide-border">
                        @foreach($kategorieStats as $ks)
                            @php
                                $ksAvg = $ks->avg_weighted !== null ? (float) $ks->avg_weighted : null;
                                $ksQuote = $ks->total > 0 ? round($ks->passed / $ks->total * 100) : null;
                            @endphp
                            <div class="p-4">
                                <div class="text-[11px] uppercase tracking-widest text-muted font-medium">{{ $ks->kategorie_name }}</div>
                                <div class="mt-1 text-2xl font-extrabold tabular-nums tracking-tight {{ $avgColor($ksAvg) }}">
                                    {{ $ksAvg !== null ? number_format($ksAvg, 2) : '–' }}
                                </div>
                                <div class="mt-0.5 text-[11px] text-muted">
                                    {{ $ks->total }} {{ $ks->total === 1 ? 'Note' : 'Noten' }}
                                    @if($ksQuote !== null)
                                        · {{ $ksQuote }}% best.
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Tabelle/Groups --}}
            @include('lernender.noten.partials.notes-table', [
                'fachGroups' => $fachGroups,
                'modulGroups' => $modulGroups,
                'weightedAvg' => $weightedAvg,
                'canManage' => $canManage,
                'editUrl' => $editUrl,
                'destroyUrl' => $destroyUrl,
            ])

            @if($notes->isEmpty())
                <div class="bg-card border border-border rounded-2xl shadow-sm px-5 py-12 text-center">
                    <svg class="mx-auto w-12 h-12 text-muted/30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    <p class="mt-3 text-sm text-muted">Noch keine Noten für dieses Semester.</p>
                    <a href="{{ route('lernender.noten.create') }}"
                       class="mt-4 inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-accent text-white text-sm font-semibold hover:opacity-90">
                        <span class="text-lg leading-none">+</span>
                        Erste Note erfassen
                    </a>
                </div>
            @endif

            {{-- Semester-Übersicht --}}
            @if($semesterStats->isNotEmpty())
                <div class="bg-card border border-border rounded-2xl shadow-sm overflow-hidden">
                    <details class="np-details-semester">
                        <summary class="cursor-pointer select-none list-none px-5 py-4 flex items-center justify-between hover:bg-bg/60">
                            <span class="font-semibold text-text text-sm">Alle Semester im Überblick</span>
                            <svg class="np-chevron-semester w-4 h-4 text-muted transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </summary>
                        <div class="border-t border-border overflow-x-auto">
                            <table class="min-w-full text-sm text-text">
                                <thead class="bg-bg text-muted">
                                    <tr>
                                        <th class="text-left px-4 py-2.5">Semester</th>
                                        <th class="text-center px-4 py-2.5">Noten</th>
                                        <th class="text-center px-4 py-2.5">Ø gewichtet</th>
                                        <th class="text-center px-4 py-2.5 whitespace-nowrap">Bestanden</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    @foreach($semesterStats as $ss)
                                        @php
                                            $ssAvg = $ss->avg_weighted !== null ? (float) $ss->avg_weighted : null;
                                            $ssColor = $ssAvg === null ? 'text-muted'
                                                : ($ssAvg >= 4.0 ? 'text-green-600 dark:text-green-400'
                                                : ($ssAvg >= 3.5 ? 'text-yellow-600 dark:text-yellow-400'
                                                : 'text-red-600 dark:text-red-400'));
                                            $isSelected = (int)$ss->semester_id === (int)$selectedSemesterId;
                                        @endphp
                                        <tr class="hover:bg-bg {{ $isSelected ? 'bg-accent/5' : '' }}">
                                            <td class="px-4 py-2.5">
                                                <a href="{{ route('lernender.noten.index', ['semester_id' => $ss->semester_id]) }}"
                                                   class="hover:text-accent {{ $isSelected ? 'font-semibold text-accent' : 'text-text' }}">
                                                    {{ $ss->sem_label }}
                                                    @if($isSelected)
                                                        <span class="ml-1 text-xs">(aktuell)</span>
                                                    @endif
                                                </a>
                                            </td>
                                            <td class="px-4 py-2.5 text-center text-muted">{{ $ss->total }}</td>
                                            <td class="px-4 py-2.5 text-center font-semibold {{ $ssColor }}">
                                                {{ $ssAvg !== null ? number_format($ssAvg, 2) : '–' }}
                                            </td>
                                            <td class="px-4 py-2.5 text-center text-muted">
                                                {{ $ss->passed }} / {{ $ss->total }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                </div>
            @endif

        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('details.np-details-semester').forEach((d) => {
                const chevron = d.querySelector('.np-chevron-semester');
                if (!chevron) return;
                const sync = () => d.open
                    ? chevron.classList.add('rotate-180')
                    : chevron.classList.remove('rotate-180');
                sync();
                d.addEventListener('toggle', sync);
            });
        });
    </script>
</x-app-layout>