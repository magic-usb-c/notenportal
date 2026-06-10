{{-- resources/views/lernender/noten/index.blade.php --}}
<x-app-layout>
    <x-slot name="title">Meine Noten</x-slot>
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
    <x-slot name="header">
        {{-- 1 Zeile / 3 Bereiche --}}
        <div class="w-full flex flex-wrap items-center justify-between gap-4">
            <div class="flex-none flex items-center gap-5">
                <h2 class="font-semibold text-xl text-text whitespace-nowrap">Meine Noten</h2>
                <div class="hidden md:flex items-center gap-3 pl-4 border-l border-border">
                    <div>
                        <div class="text-[10px] uppercase tracking-widest text-muted">Ø Sem</div>
                        <div class="text-lg font-bold tabular-nums {{ $avgColor($avgWeighted) }}">{{ $avgWeighted ?? '–' }}</div>
                    </div>
                    <div>
                        <div class="text-[10px] uppercase tracking-widest text-muted">Ø Gesamt</div>
                        <div class="text-lg font-bold tabular-nums {{ $avgColor($globalAvgWeighted ?? null) }}">{{ $globalAvgWeighted ?? '–' }}</div>
                    </div>
                    <div>
                        <div class="text-[10px] uppercase tracking-widest text-muted">Noten</div>
                        <div class="text-lg font-bold text-text tabular-nums">{{ $count }}</div>
                    </div>
                </div>
            </div>

            <div class="flex-1 flex items-center justify-center gap-2 min-w-0">
                <a
                    @class([
                        'inline-flex items-center justify-center shrink-0 w-12 h-10 rounded-xl border border-border',
                        'text-text hover:bg-card/60',
                        !$prevSemesterId ? 'pointer-events-none opacity-40' : ''
                    ])
                    href="{{ $prevSemesterId ? route('lernender.noten.index', $queryWith(['semester_id' => $prevSemesterId])) : '#' }}"
                    title="Vorheriges Semester (Pfeiltaste links)"
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
                    title="Nächstes Semester (Pfeiltaste rechts)"
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
                   class="inline-flex items-center gap-2 px-4 py-2 h-10 rounded-xl bg-accent text-white hover:opacity-90 whitespace-nowrap"
                   title="Neue Note erfassen (Shortcut: N)">
                    <span class="text-lg leading-none">+</span>
                    Neue Note
                    <kbd class="hidden lg:inline-flex items-center justify-center text-[10px] font-mono bg-white/20 px-1.5 py-0.5 rounded ml-1">N</kbd>
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

            {{-- Filter (volle Breite, kompakt) --}}
            <div class="bg-card border border-border rounded-2xl shadow-sm p-4">
                <form method="GET" action="{{ route('lernender.noten.index') }}"
                      class="flex flex-wrap items-end gap-3">
                    <input type="hidden" name="semester_id" value="{{ $selectedSemesterId }}">

                    <div class="flex-1 min-w-[180px]">
                        <label class="text-xs uppercase tracking-wide text-muted">Kategorie</label>
                        <select name="kategorie_id"
                                onchange="this.form.submit()"
                                class="mt-1 w-full rounded-xl border border-border bg-input text-text
                                       focus:ring-2 focus:ring-ring focus:border-ring">
                            <option value="">Alle</option>
                            @foreach($kategorien as $k)
                                <option value="{{ $k->kategorie_id }}" @selected(request('kategorie_id') == $k->kategorie_id)>
                                    {{ $k->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <button class="px-4 py-2 h-10 rounded-xl bg-accent text-white hover:opacity-90 whitespace-nowrap">
                        Anwenden
                    </button>
                    <a href="{{ route('lernender.noten.index', ['semester_id' => $selectedSemesterId]) }}"
                       class="px-4 py-2 h-10 rounded-xl bg-card text-text border border-border hover:bg-card/60 whitespace-nowrap">
                        Reset
                    </a>

                    @if(($missingWeights ?? 0) > 0)
                        <div class="w-full text-xs text-muted">
                            Hinweis: {{ $missingWeights }} Note(n) ohne Gewichtung werden mit 100% gerechnet.
                        </div>
                    @endif
                </form>
            </div>

            {{-- Warning-Banner: Ø unter 4.0 (Trigger aus PDF) --}}
            @if($avgWeighted !== null && (float)$avgWeighted < 4.0)
                <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800/50 rounded-2xl p-4 flex items-start gap-3">
                    <svg class="w-5 h-5 text-red-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4a2 2 0 0 0-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/>
                    </svg>
                    <div>
                        <div class="font-medium text-red-800 dark:text-red-300 text-sm">Achtung: Durchschnitt unter 4.0</div>
                        <div class="text-xs text-red-600 dark:text-red-400 mt-0.5">
                            Dein aktueller Semesterdurchschnitt beträgt {{ $avgWeighted }}. Sprich mit deinem Berufsbildner.
                        </div>
                    </div>
                </div>
            @endif

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
            @if($notes->isNotEmpty())
                <div class="flex justify-end gap-2 text-xs -mb-2">
                    <button type="button"
                            onclick="document.querySelectorAll('details.np-details').forEach(d => d.open = true)"
                            class="px-3 py-1.5 rounded-lg border border-border text-muted hover:text-text hover:bg-card">
                        Alle aufklappen
                    </button>
                    <button type="button"
                            onclick="document.querySelectorAll('details.np-details').forEach(d => d.open = false)"
                            class="px-3 py-1.5 rounded-lg border border-border text-muted hover:text-text hover:bg-card">
                        Alle zuklappen
                    </button>
                </div>
            @endif
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
                        <div class="border-t border-border divide-y divide-border">
                            @foreach($semesterStats as $ss)
                                @php
                                    $ssAvg = $ss->avg_weighted !== null ? (float) $ss->avg_weighted : null;
                                    $ssColor = $ssAvg === null ? 'text-muted'
                                        : ($ssAvg >= 5.0 ? 'text-green-600 dark:text-green-400'
                                        : ($ssAvg >= 4.0 ? 'text-emerald-600 dark:text-emerald-400'
                                        : ($ssAvg >= 3.5 ? 'text-yellow-600 dark:text-yellow-400'
                                        : 'text-red-600 dark:text-red-400')));
                                    $ssBarBg = $ssAvg === null ? 'bg-muted/30'
                                        : ($ssAvg >= 5.0 ? 'bg-green-500'
                                        : ($ssAvg >= 4.0 ? 'bg-emerald-500'
                                        : ($ssAvg >= 3.5 ? 'bg-yellow-500'
                                        : 'bg-red-500')));
                                    $ssBarWidth = $ssAvg !== null ? min(100, round(($ssAvg / 6) * 100)) : 0;
                                    $isSelected = (int)$ss->semester_id === (int)$selectedSemesterId;
                                @endphp
                                <a href="{{ route('lernender.noten.index', ['semester_id' => $ss->semester_id]) }}"
                                   class="flex items-center gap-3 px-5 py-3 hover:bg-accent/5 transition-colors duration-100 {{ $isSelected ? 'bg-accent/5' : '' }}">
                                    <span class="text-xs w-28 shrink-0 {{ $isSelected ? 'font-semibold text-accent' : 'text-text' }}">
                                        {{ $ss->sem_label }}
                                        @if($isSelected)<span class="text-[10px] text-muted">(aktuell)</span>@endif
                                    </span>
                                    <div class="flex-1 bg-bg rounded-full h-2 overflow-hidden border border-border">
                                        <div class="h-full rounded-full {{ $ssBarBg }} transition-all duration-300" style="width: {{ $ssBarWidth }}%"></div>
                                    </div>
                                    <span class="text-sm font-semibold tabular-nums {{ $ssColor }} w-12 text-right">
                                        {{ $ssAvg !== null ? number_format($ssAvg, 2) : '–' }}
                                    </span>
                                    <span class="text-[11px] text-muted tabular-nums w-20 text-right shrink-0">
                                        {{ $ss->passed }}/{{ $ss->total }} best.
                                    </span>
                                </a>
                            @endforeach
                        </div>
                    </details>
                </div>
            @endif

        </div>
    </div>

    <script>
        // Keyboard-Shortcuts: "N" = neue Note, ←/→ = Semester wechseln
        const prevSemUrl = {!! $prevSemesterId ? json_encode(route('lernender.noten.index', $queryWith(['semester_id' => $prevSemesterId]))) : 'null' !!};
        const nextSemUrl = {!! $nextSemesterId ? json_encode(route('lernender.noten.index', $queryWith(['semester_id' => $nextSemesterId]))) : 'null' !!};

        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey || e.metaKey || e.altKey) return;
            const tag = (document.activeElement?.tagName ?? '').toUpperCase();
            if (['INPUT', 'TEXTAREA', 'SELECT'].includes(tag)) return;
            if (document.activeElement?.isContentEditable) return;

            if (e.key === 'n' || e.key === 'N') {
                window.location.href = '{{ route("lernender.noten.create") }}';
            } else if (e.key === 'ArrowLeft' && prevSemUrl) {
                window.location.href = prevSemUrl;
            } else if (e.key === 'ArrowRight' && nextSemUrl) {
                window.location.href = nextSemUrl;
            }
        });

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