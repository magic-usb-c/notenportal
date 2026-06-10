<x-app-layout>
    <x-slot name="title">Noten</x-slot>
    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-4 flex-wrap">
            <h2 class="font-semibold text-xl text-text">
                Noten:
                <span class="text-muted">{{ $selectedLernender->nachname ?? '' }} {{ $selectedLernender->vorname ?? '' }}</span>
            </h2>
            <div class="flex items-center gap-2">
                @php
                    $bbBenutzerId = auth()->user()->benutzer_id;
                    $hasNeuInView = $notes->getCollection()->contains(function ($n) use ($bbBenutzerId) {
                        $gr = $n->gesehen->first();
                        return !$gr
                            || $n->erstellt_am > $gr->gesehen_am
                            || $n->kommentare->filter(fn($k) => $k->erstellt_am > $gr->gesehen_am)->isNotEmpty();
                    });
                @endphp
                @if($hasNeuInView)
                    <form method="POST"
                          action="{{ route('berufsbildner.noten.alle_gesehen', ['lernender_id' => $selectedLernenderId]) }}">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center gap-2 px-4 py-2 h-10 rounded-xl bg-green-600 hover:bg-green-700 text-white text-sm font-medium whitespace-nowrap">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                            </svg>
                            Alle gesehen
                        </button>
                    </form>
                @endif
                <a href="{{ route('berufsbildner.lernende.noten.drucken', ['lernender_id' => $selectedLernenderId]) }}"
                   target="_blank"
                   class="inline-flex items-center gap-1.5 px-4 py-2 h-10 rounded-xl bg-card text-text border border-border hover:bg-bg whitespace-nowrap text-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    Drucken
                </a>
                <a href="{{ route('berufsbildner.lernende.noten.export', ['lernender_id' => $selectedLernenderId]) }}"
                   class="inline-flex items-center gap-1.5 px-4 py-2 h-10 rounded-xl bg-card text-text border border-border hover:bg-bg whitespace-nowrap text-sm"
                   title="Noten als CSV exportieren">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    CSV
                </a>
                <a href="{{ route('berufsbildner.lernende.show', ['lernender_id' => $selectedLernenderId]) }}"
                   class="px-4 py-2 h-10 rounded-xl bg-card text-text border border-border hover:bg-bg whitespace-nowrap"
                   title="Profil ansehen">
                    Profil
                </a>
                <a href="{{ route('berufsbildner.lernende.index') }}"
                   class="px-4 py-2 h-10 rounded-xl bg-card text-text border border-border hover:bg-bg whitespace-nowrap">
                    Lernenden wechseln
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if(session('status'))
                <div class="bg-green-100 dark:bg-green-900/30 border border-green-300 dark:border-green-700 text-green-800 dark:text-green-200 rounded-xl px-4 py-3 text-sm" data-autohide>
                    {{ session('status') }}
                </div>
            @endif

            {{-- Warning: aktueller Semester-Ø unter 4.0 --}}
            @if($currentSemAvg !== null && $currentSemAvg < 4.0)
                <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800/50 rounded-2xl p-4 flex items-start gap-3">
                    <svg class="w-5 h-5 text-red-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4a2 2 0 0 0-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/>
                    </svg>
                    <div>
                        <div class="font-medium text-red-800 dark:text-red-300 text-sm">Aktueller Semester-Ø unter 4.0</div>
                        <div class="text-xs text-red-600 dark:text-red-400 mt-0.5">
                            {{ $selectedLernender->vorname }} {{ $selectedLernender->nachname }} hat im aktuellen Semester einen Durchschnitt von {{ number_format($currentSemAvg, 2) }}.
                        </div>
                    </div>
                </div>
            @endif

            {{-- Lernenden-Zusammenfassung --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                {{-- Profil --}}
                @if($lernenderProfil)
                    <div class="bg-card border border-border rounded-2xl shadow-sm p-4">
                        <div class="text-xs font-semibold uppercase tracking-wider text-muted mb-2">Ausbildung</div>
                        <div class="text-sm text-text font-medium">{{ $lernenderProfil->lehrberuf_name ?? '–' }}</div>
                        <div class="mt-1 text-xs text-muted">
                            Lehrbeginn:
                            {{ $lernenderProfil->lehrbeginn ? \Carbon\Carbon::parse($lernenderProfil->lehrbeginn)->format('d.m.Y') : '–' }}
                            @if($lernenderProfil->lehrende)
                                · Ende: {{ \Carbon\Carbon::parse($lernenderProfil->lehrende)->format('d.m.Y') }}
                            @endif
                        </div>
                    </div>
                @endif

                {{-- Semester-Schnitte --}}
                @if($semStats->isNotEmpty())
                    <div class="bg-card border border-border rounded-2xl shadow-sm p-4">
                        <div class="text-xs font-semibold uppercase tracking-wider text-muted mb-2">Ø pro Semester</div>
                        <div class="flex flex-wrap gap-2">
                            @foreach($semStats as $ss)
                                @php
                                    $a = $ss->avg !== null ? (float)$ss->avg : null;
                                    $c = $a === null
                                        ? 'bg-bg text-muted'
                                        : ($a >= 5.0 ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300 np-glow-green'
                                        : ($a >= 4.0 ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300 np-glow-emerald'
                                        : ($a >= 3.5 ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300 np-glow-yellow'
                                        : 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300 np-glow-red')));
                                @endphp
                                <div class="text-center">
                                    <div class="text-[11px] text-muted whitespace-nowrap">{{ $ss->sem_label }}</div>
                                    <span class="inline-flex items-center justify-center px-2.5 py-1 rounded-lg text-xs font-bold {{ $c }}">
                                        {{ $a !== null ? number_format($a, 2) : '–' }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- Notenverlauf (Liniendiagramm der letzten 20 Noten) --}}
            <x-noten-verlauf :points="$notenVerlauf"
                             title="Notenverlauf"
                             subtitle="letzte {{ $notenVerlauf->count() }} Noten" />

            {{-- Lernenden-Switcher --}}
            <div class="bg-card border border-border rounded-2xl shadow-sm p-4">
                <label class="text-sm font-medium text-muted">Lernenden wechseln</label>
                <select class="mt-1 w-full sm:w-80 rounded-xl border border-border bg-input text-text focus:ring-2 focus:ring-ring focus:border-ring"
                        onchange="if(this.value) window.location.href=this.value">
                    @foreach($lernende as $l)
                        <option
                            value="{{ route('berufsbildner.lernende.noten.index', ['lernender_id' => $l->lernender_id]) }}"
                            @selected((int)$l->lernender_id === (int)$selectedLernenderId)
                        >
                            {{ $l->nachname }} {{ $l->vorname }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Filter --}}
            <div class="bg-card border border-border rounded-2xl shadow-sm p-4">
                <form method="GET"
                      action="{{ route('berufsbildner.lernende.noten.index', ['lernender_id' => $selectedLernenderId]) }}"
                      class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">

                    <div>
                        <label class="text-sm font-medium text-muted">Kategorie</label>
                        <select name="kategorie_id"
                                onchange="this.form.submit()"
                                class="mt-1 w-full rounded-xl border border-border bg-input text-text focus:ring-2 focus:ring-ring focus:border-ring">
                            <option value="">Alle</option>
                            @foreach($kategorien as $k)
                                <option value="{{ $k->kategorie_id }}" @selected(request('kategorie_id') == $k->kategorie_id)>
                                    {{ $k->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="text-sm font-medium text-muted">Semester</label>
                        <select name="semester_id"
                                onchange="this.form.submit()"
                                class="mt-1 w-full rounded-xl border border-border bg-input text-text focus:ring-2 focus:ring-ring focus:border-ring">
                            <option value="">Alle</option>
                            @foreach($semester as $s)
                                <option value="{{ $s->semester_id }}" @selected(request('semester_id') == $s->semester_id)>
                                    {{ $s->bezeichnung }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex gap-2">
                        <button class="px-4 py-2 h-10 rounded-xl bg-accent text-white np-btn-primary">
                            Filtern
                        </button>
                        <a href="{{ route('berufsbildner.lernende.noten.index', ['lernender_id' => $selectedLernenderId]) }}"
                           class="px-4 py-2 h-10 rounded-xl bg-card text-text border border-border hover:bg-bg">
                            Reset
                        </a>
                    </div>
                </form>
            </div>

            {{-- Ø pro Fach / Modul (berücksichtigt aktive Filter) --}}
            <x-fach-modul-stats :stats="$fachStats" />

            {{-- Noten-Accordion --}}
            @if($notes->isNotEmpty())
                <div class="flex justify-end gap-2 text-xs">
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
            <div class="space-y-2">
                @forelse($notes as $n)
                    @php
                        // Gesehen-Eintrag dieses BBs für diese Note (max. 1, da eager-load gefiltert)
                        $gesehenRecord = $n->gesehen->first();

                        // "Neu" wenn: keine Markierung, oder Note neuer als letzte Markierung,
                        // oder ein Kommentar neuer als letzte Markierung
                        $isNeu = !$gesehenRecord
                            || $n->erstellt_am > $gesehenRecord->gesehen_am
                            || $n->kommentare->filter(fn($k) => $k->erstellt_am > $gesehenRecord->gesehen_am)->isNotEmpty();

                        $newestKommentar = $n->kommentare->last();

                        // Fach- oder Modulbezeichnung
                        if ($n->fach) {
                            $thema = $n->fach->name;
                        } elseif ($n->modulBelegung?->modul) {
                            $m = $n->modulBelegung->modul;
                            $thema = $m->modul_nummer . ' – ' . $m->titel;
                        } else {
                            $thema = '–';
                        }

                        // Note farblich kodieren (Schweizer Schulnoten)
                        $noteWert = (float) $n->note_wert;
                        $noteColor = $noteWert >= 5.0
                            ? 'text-green-600 dark:text-green-400'
                            : ($noteWert >= 4.0
                                ? 'text-emerald-600 dark:text-emerald-400'
                                : ($noteWert >= 3.5
                                    ? 'text-yellow-600 dark:text-yellow-400'
                                    : 'text-red-600 dark:text-red-400'));
                    @endphp

                    <details class="np-details bg-card border border-border rounded-2xl shadow-sm overflow-hidden
                                    {{ $isNeu ? 'ring-1 ring-accent/40' : '' }}"
                             data-note-id="{{ $n->note_id }}">

                        <summary class="cursor-pointer select-none px-4 py-3 flex items-start justify-between gap-3 list-none hover:bg-accent/5 transition-colors duration-100">
                            {{-- Linke Seite: Chevron + Infos --}}
                            <div class="flex items-start gap-3 min-w-0">
                                <span class="np-chevron text-muted transition-transform duration-200 shrink-0 mt-0.5">
                                    <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L10.94 10 7.23 6.29a.75.75 0 1 1 1.06-1.06l4.24 4.24c.3.3.3.77 0 1.06l-4.24 4.24a.75.75 0 0 1-1.06.02z" clip-rule="evenodd"/>
                                    </svg>
                                </span>

                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-medium text-text">
                                            {{ optional($n->pruefungsdatum)->format('d.m.Y') }}
                                        </span>
                                        <span class="text-xs text-muted">{{ $n->kategorie?->name ?? '–' }}</span>
                                        @if($isNeu)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-accent text-white">
                                                Neu
                                            </span>
                                        @endif
                                    </div>
                                    <div class="text-sm text-muted mt-0.5 truncate">{{ $thema }}</div>
                                    @if($n->titel)
                                        <div class="text-xs text-muted truncate">{{ $n->titel }}</div>
                                    @endif

                                    {{-- Neuester Kommentar als Vorschau --}}
                                    @if($newestKommentar)
                                        <div class="mt-1.5 text-xs text-muted italic truncate max-w-sm">
                                            <span class="font-medium not-italic">{{ $newestKommentar->autor?->vorname }}</span>:
                                            {{ Str::limit($newestKommentar->kommentar_text, 80) }}
                                        </div>
                                    @endif
                                </div>
                            </div>

                            {{-- Rechte Seite: Note --}}
                            <div class="shrink-0 flex flex-col items-end gap-0.5">
                                <span class="text-2xl font-bold tabular-nums leading-none {{ $noteColor }}">
                                    {{ number_format($noteWert, 1) }}
                                </span>
                                @if($n->gewichtung_prozent !== null)
                                    <span class="text-xs text-muted tabular-nums">{{ $n->gewichtung_prozent }}%</span>
                                @endif
                            </div>
                        </summary>

                        {{-- Ausgeklappter Bereich --}}
                        <div class="border-t border-border">

                            {{-- Details-Grid --}}
                            <div class="px-5 py-4 grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-2 text-sm">
                                <div>
                                    <div class="text-xs text-muted">Datum</div>
                                    <div class="text-text">{{ optional($n->pruefungsdatum)->format('d.m.Y') }}</div>
                                </div>
                                <div>
                                    <div class="text-xs text-muted">Kategorie</div>
                                    <div class="text-text">{{ $n->kategorie?->name ?? '–' }}</div>
                                </div>
                                <div>
                                    <div class="text-xs text-muted">Semester</div>
                                    <div class="text-text">{{ $n->semester?->bezeichnung ?? '–' }}</div>
                                </div>
                                <div>
                                    <div class="text-xs text-muted">Fach / Modul</div>
                                    <div class="text-text">{{ $thema }}</div>
                                </div>
                                @if($n->titel)
                                    <div>
                                        <div class="text-xs text-muted">Titel</div>
                                        <div class="text-text">{{ $n->titel }}</div>
                                    </div>
                                @endif
                                <div>
                                    <div class="text-xs text-muted">Gewichtung</div>
                                    <div class="text-text">{{ $n->gewichtung_prozent !== null ? $n->gewichtung_prozent . ' %' : '–' }}</div>
                                </div>
                                @if($n->gruppe)
                                    <div>
                                        <div class="text-xs text-muted">Gruppe</div>
                                        <div class="text-text">{{ $n->gruppe->bezeichnung }}</div>
                                    </div>
                                @endif
                            </div>

                            {{-- Als gesehen markieren --}}
                            <div class="px-5 pb-4">
                                @if($isNeu)
                                    <form method="POST"
                                          action="{{ route('berufsbildner.noten.gesehen', ['lernender_id' => $selectedLernenderId, 'note_id' => $n->note_id]) }}"
                                          class="inline">
                                        @csrf
                                        <button type="submit"
                                                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-green-600 hover:bg-green-700 text-white text-sm font-medium">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                            </svg>
                                            Als gesehen markieren
                                        </button>
                                    </form>
                                @else
                                    <div class="inline-flex items-center gap-1.5 text-xs text-muted">
                                        <svg class="w-4 h-4 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                        </svg>
                                        Gesehen am {{ $gesehenRecord->gesehen_am->format('d.m.Y H:i') }} Uhr
                                    </div>
                                @endif
                            </div>

                            {{-- Kommentar-Thread --}}
                            <div class="border-t border-border px-5 py-4 space-y-3">
                                <div class="text-xs font-semibold uppercase tracking-wider text-muted">Kommentare</div>

                                @forelse($n->kommentare as $k)
                                    @php
                                        $canDeleteKommentar = (int)$k->autor_benutzer_id === (int)auth()->user()->benutzer_id
                                            || auth()->user()->hasRole('Admin');
                                    @endphp
                                    <div class="bg-bg rounded-xl p-3 space-y-0.5">
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="text-xs text-muted">
                                                <span class="font-medium text-text">{{ $k->autor?->vorname }} {{ $k->autor?->nachname }}</span>
                                                &middot;
                                                {{ $k->erstellt_am->format('d.m.Y H:i') }} Uhr
                                            </div>
                                            @if($canDeleteKommentar)
                                                <form method="POST"
                                                      action="{{ route('noten.kommentare.destroy', $k->kommentar_id) }}"
                                                      class="shrink-0"
                                                      onsubmit="return confirm('Kommentar wirklich löschen?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="text-xs text-red-400 hover:text-red-600">Löschen</button>
                                                </form>
                                            @endif
                                        </div>
                                        <div class="text-sm text-text whitespace-pre-line">{{ $k->kommentar_text }}</div>
                                    </div>
                                @empty
                                    <div class="text-sm text-muted">Noch keine Kommentare.</div>
                                @endforelse
                            </div>

                            {{-- Neuer Kommentar --}}
                            <div class="border-t border-border px-5 py-4">
                                <form method="POST"
                                      action="{{ route('noten.kommentare.store', $n->note_id) }}">
                                    @csrf
                                    <div class="flex gap-2">
                                        <input type="text"
                                               name="kommentar_text"
                                               placeholder="Kommentar schreiben…"
                                               class="flex-1 rounded-xl border border-border bg-input text-text placeholder-muted text-sm px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring"
                                               maxlength="2000"
                                               required>
                                        <button type="submit"
                                                class="px-4 py-2 rounded-xl bg-accent text-white text-sm np-btn-primary whitespace-nowrap">
                                            Senden
                                        </button>
                                    </div>
                                </form>
                            </div>

                        </div>
                    </details>
                @empty
                    <div class="bg-card border border-border rounded-2xl shadow-sm px-5 py-8 text-center text-muted text-sm">
                        Keine Noten gefunden.
                    </div>
                @endforelse
            </div>

            {{-- Pagination --}}
            @if($notes->hasPages())
                <div class="bg-card border border-border rounded-2xl shadow-sm p-3">
                    {{ $notes->links() }}
                </div>
            @endif

        </div>
    </div>

    <style>
        summary::-webkit-details-marker { display: none; }
        summary { list-style: none; }
    </style>

    <script>
        const openedNoteId = {{ session('opened_note') ? (int)session('opened_note') : 'null' }};

        const openNoteAccordion = (noteId) => {
            const noteEl = document.querySelector(`details.np-details[data-note-id="${noteId}"]`);
            if (!noteEl) return;
            if (!noteEl.open) noteEl.open = true;
            setTimeout(() => noteEl.scrollIntoView({ behavior: 'smooth', block: 'start' }), 50);
        };

        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('details.np-details').forEach((d) => {
                const chevron = d.querySelector('.np-chevron');
                if (!chevron) return;
                const sync = () => d.open
                    ? chevron.classList.add('rotate-90')
                    : chevron.classList.remove('rotate-90');
                sync();
                d.addEventListener('toggle', sync);
            });

            if (openedNoteId) openNoteAccordion(openedNoteId);
        });
    </script>

</x-app-layout>
