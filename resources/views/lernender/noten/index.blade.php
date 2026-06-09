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
                    <div class="bg-card border border-border rounded-2xl shadow-sm p-4 h-full">
                        <div class="grid grid-cols-3 gap-3 h-full">
                            <div class="rounded-2xl border border-border bg-bg p-3">
                                <div class="text-[11px] text-muted">Noten</div>
                                <div class="mt-1 text-2xl font-semibold text-text">{{ $count }}</div>
                            </div>

                            <div class="rounded-2xl border border-border bg-bg p-3">
                                <div class="text-[11px] text-muted">Ø ungewichtet</div>
                                <div class="mt-1 text-2xl font-semibold text-text">{{ $avgUnweighted ?? '-' }}</div>
                            </div>

                            <div class="rounded-2xl border border-border bg-bg p-3">
                                <div class="text-[11px] text-muted">Ø gewichtet</div>
                                <div class="mt-1 text-2xl font-semibold text-text">{{ $avgWeighted ?? '-' }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

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
                <div class="bg-card border border-border rounded-2xl shadow-sm px-5 py-10 text-center text-muted text-sm">
                    Noch keine Noten für dieses Semester.
                    <a href="{{ route('lernender.noten.create') }}" class="text-accent hover:underline ml-1">Erste Note erfassen</a>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>