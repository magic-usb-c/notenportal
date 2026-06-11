<x-app-layout>
    <x-slot name="title">Noten</x-slot>
    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-text">
                Noten:
                <span class="text-muted">{{ $selectedLernender->nachname ?? '' }} {{ $selectedLernender->vorname ?? '' }}</span>
            </h2>
            <div class="flex gap-2 flex-wrap">
                <a href="{{ route('admin.lernende.noten.create', ['lernender_id' => $selectedLernenderId]) }}"
                   class="px-4 py-2 h-10 rounded-xl bg-accent text-white np-btn-primary whitespace-nowrap text-sm">
                    + Note erfassen
                </a>
                <a href="{{ route('admin.lernende.noten.drucken', ['lernender_id' => $selectedLernenderId]) }}"
                   target="_blank"
                   class="inline-flex items-center gap-1.5 px-4 py-2 h-10 rounded-xl glass-btn text-text whitespace-nowrap text-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    Drucken
                </a>
                <a href="{{ route('admin.lernende.noten.export', array_merge(['lernender_id' => $selectedLernenderId], request()->only(['semester_id', 'kategorie_id']))) }}"
                   class="inline-flex items-center gap-1.5 px-4 py-2 h-10 rounded-xl glass-btn text-text whitespace-nowrap text-sm">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    CSV
                </a>
                <a href="{{ route('admin.lernende.show', $selectedLernenderId) }}"
                   class="px-4 py-2 h-10 rounded-xl glass-btn text-text whitespace-nowrap text-sm">
                    Zum Profil
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">


            {{-- Notenverlauf (Liniendiagramm der letzten 20 Noten) --}}
            <x-noten-verlauf :points="$notenVerlauf"
                             title="Notenverlauf"
                             subtitle="letzte {{ $notenVerlauf->count() }} Noten" />

            {{-- Lernenden-Switcher --}}
            <div class="glass rounded-2xl p-4">
                <label for="lernenden_wechseln" class="text-sm font-medium text-muted">Lernenden wechseln</label>
                <select id="lernenden_wechseln" class="mt-1 w-full sm:w-80 rounded-xl border border-border bg-input text-text focus:ring-2 focus:ring-ring focus:border-ring"
                        onchange="if(this.value) window.location.href=this.value">
                    @foreach($lernende as $l)
                        <option
                            value="{{ route('admin.lernende.noten.index', ['lernender_id' => $l->lernender_id]) }}"
                            @selected((int)$l->lernender_id === (int)$selectedLernenderId)
                        >
                            {{ $l->nachname }} {{ $l->vorname }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Filter --}}
            <div class="glass rounded-2xl p-4">
                <form method="GET"
                      action="{{ route('admin.lernende.noten.index', ['lernender_id' => $selectedLernenderId]) }}"
                      class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">

                    <div>
                        <label for="kategorie_id" class="text-sm font-medium text-muted">Kategorie</label>
                        <select name="kategorie_id" id="kategorie_id" onchange="this.form.submit()"
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
                        <label for="semester_id" class="text-sm font-medium text-muted">Semester</label>
                        <select name="semester_id" id="semester_id" onchange="this.form.submit()"
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
                        <a href="{{ route('admin.lernende.noten.index', ['lernender_id' => $selectedLernenderId]) }}"
                           class="px-4 py-2 h-10 rounded-xl glass-btn text-text">
                            Reset
                        </a>
                    </div>
                </form>
            </div>

            {{-- Statistik-Leiste --}}
            @if($statsRow && $statsRow->total > 0)
                @php
                    $avg = $statsRow->avg_weighted !== null ? (float)$statsRow->avg_weighted : null;
                    $passRate = $statsRow->total > 0 ? round($statsRow->passed / $statsRow->total * 100) : null;
                    $avgColor = $avg === null ? 'text-muted'
                        : ($avg >= 5.0 ? 'text-green-700 dark:text-green-400'
                        : ($avg >= 4.0 ? 'text-emerald-700 dark:text-emerald-400'
                        : ($avg >= 3.5 ? 'text-yellow-700 dark:text-yellow-400'
                        : 'text-red-600 dark:text-red-400')));
                @endphp
                <div class="glass rounded-2xl px-5 py-3 flex flex-wrap gap-6 text-sm">
                    <div class="text-center">
                        <div class="text-xs text-muted">Noten</div>
                        <div class="font-bold text-text text-lg">{{ $statsRow->total }}</div>
                    </div>
                    <div class="text-center">
                        <div class="text-xs text-muted">Ø gewichtet</div>
                        <div class="font-bold text-lg {{ $avgColor }}">{{ $avg !== null ? number_format($avg, 2) : '–' }}</div>
                    </div>
                    <div class="text-center">
                        <div class="text-xs text-muted">Bestanden</div>
                        <div class="font-bold text-lg text-text">{{ $statsRow->passed }} / {{ $statsRow->total }}</div>
                    </div>
                    @if($passRate !== null)
                        <div class="text-center">
                            <div class="text-xs text-muted">Bestehensquote</div>
                            <div class="font-bold text-lg {{ $passRate >= 75 ? 'text-green-700 dark:text-green-400' : ($passRate >= 50 ? 'text-yellow-700 dark:text-yellow-400' : 'text-red-600 dark:text-red-400') }}">{{ $passRate }} %</div>
                        </div>
                    @endif
                </div>
            @endif

            {{-- Ø pro Fach / Modul (berücksichtigt aktive Filter) --}}
            <x-fach-modul-stats :stats="$fachStats" />

            {{-- Noten-Accordion --}}
            <div class="space-y-2">
                @forelse($notes as $n)
                    @php
                        $newestKommentar = $n->kommentare->last();

                        if ($n->fach) {
                            $thema = $n->fach->name;
                        } elseif ($n->modulBelegung?->modul) {
                            $m = $n->modulBelegung->modul;
                            $thema = $m->modul_nummer . ' – ' . $m->titel;
                        } else {
                            $thema = '–';
                        }

                        $noteWert = (float) $n->note_wert;
                        $noteColor = $noteWert >= 5.0
                            ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300'
                            : ($noteWert >= 4.0
                                ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300'
                                : ($noteWert >= 3.5
                                    ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300'
                                    : 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300'));
                    @endphp

                    <details class="np-details glass rounded-2xl overflow-hidden"
                             data-note-id="{{ $n->note_id }}">

                        <summary class="cursor-pointer select-none px-4 py-3 flex items-start justify-between gap-3 list-none hover:bg-accent/5 transition-colors duration-100">
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
                                        @if($n->kommentare->isNotEmpty())
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs bg-bg border border-border text-muted">
                                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h6m-9 8l4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/>
                                                </svg>
                                                {{ $n->kommentare->count() }}
                                            </span>
                                        @endif
                                    </div>
                                    <div class="text-sm text-muted mt-0.5 truncate">{{ $thema }}</div>
                                    @if($n->titel)
                                        <div class="text-xs text-muted truncate">{{ $n->titel }}</div>
                                    @endif
                                    @if($newestKommentar)
                                        <div class="mt-1.5 text-xs text-muted italic truncate max-w-sm">
                                            <span class="font-medium not-italic">{{ $newestKommentar->autor?->vorname }}</span>:
                                            {{ Str::limit($newestKommentar->kommentar_text, 80) }}
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="shrink-0 flex flex-col items-end gap-1">
                                <span class="inline-flex items-center justify-center min-w-[3rem] px-3 py-1 rounded-xl font-bold text-sm {{ $noteColor }}">
                                    {{ number_format($noteWert, 1) }}
                                </span>
                                @if($n->gewichtung_prozent !== null)
                                    <span class="text-xs text-muted">{{ $n->gewichtung_prozent }}%</span>
                                @endif
                            </div>
                        </summary>

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
                                @if($n->erfasstVonBenutzer)
                                    <div>
                                        <div class="text-xs text-muted">Erfasst von</div>
                                        <div class="text-text">{{ $n->erfasstVonBenutzer->vorname }} {{ $n->erfasstVonBenutzer->nachname }}</div>
                                    </div>
                                @endif
                                @if($n->aktualisiertVonBenutzer && $n->aktualisiert_von_benutzer_id !== $n->erfasst_von_benutzer_id)
                                    <div>
                                        <div class="text-xs text-muted">Zuletzt geändert von</div>
                                        <div class="text-text">{{ $n->aktualisiertVonBenutzer->vorname }} {{ $n->aktualisiertVonBenutzer->nachname }}</div>
                                    </div>
                                @endif
                            </div>

                            {{-- Admin-Aktionen --}}
                            <div class="border-t border-border px-5 py-3 flex items-center justify-end gap-3">
                                <a href="{{ route('admin.lernende.noten.edit', [$selectedLernenderId, $n->note_id]) }}"
                                   class="px-3 py-1.5 rounded-xl text-xs text-accent border border-accent/20 hover:bg-accent/10">
                                    Bearbeiten
                                </a>
                                <form method="POST"
                                      action="{{ route('admin.lernende.noten.destroy', [$selectedLernenderId, $n->note_id]) }}"
                                      x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true"
                                      onsubmit="return confirm('Note wirklich löschen?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" :disabled="loading"
                                            class="px-3 py-1.5 rounded-xl text-xs text-red-600 dark:text-red-400 border border-red-200 hover:bg-red-50 dark:border-red-800 dark:hover:bg-red-900/20 disabled:opacity-60 disabled:cursor-not-allowed">
                                        Löschen
                                    </button>
                                </form>
                            </div>

                            {{-- Kommentar-Thread --}}
                            <div class="border-t border-border px-5 py-4 space-y-3">
                                <div class="text-xs font-semibold uppercase tracking-wider text-muted">Kommentare</div>

                                @forelse($n->kommentare as $k)
                                    <div class="bg-bg rounded-xl p-3 space-y-0.5">
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="text-xs text-muted">
                                                <span class="font-medium text-text">{{ $k->autor?->vorname }} {{ $k->autor?->nachname }}</span>
                                                &middot;
                                                {{ $k->erstellt_am->format('d.m.Y H:i') }} Uhr
                                            </div>
                                            <form method="POST"
                                                  action="{{ route('noten.kommentare.destroy', $k->kommentar_id) }}"
                                                  class="shrink-0"
                                                  x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true"
                                                  onsubmit="return confirm('Kommentar wirklich löschen?');">
                                                @csrf
                                                @method('DELETE')
                                                <button :disabled="loading" class="text-xs text-red-400 hover:text-red-600 disabled:opacity-60 disabled:cursor-not-allowed">Löschen</button>
                                            </form>
                                        </div>
                                        <div class="text-sm text-text whitespace-pre-line">{{ $k->kommentar_text }}</div>
                                    </div>
                                @empty
                                    <div class="text-sm text-muted">Noch keine Kommentare.</div>
                                @endforelse
                            </div>

                            {{-- Kommentar verfassen (Admin) --}}
                            <div class="border-t border-border px-5 py-4">
                                <form method="POST"
                                      action="{{ route('noten.kommentare.store', $n->note_id) }}"
                                      x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                                    @csrf
                                    <div class="flex gap-2">
                                        <input type="text"
                                               name="kommentar_text"
                                               placeholder="Kommentar schreiben…" aria-label="Kommentar schreiben"
                                               class="flex-1 rounded-xl border border-border bg-input text-text placeholder-muted text-sm px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring"
                                               maxlength="2000"
                                               required>
                                        <button type="submit" :disabled="loading"
                                                class="px-4 py-2 rounded-xl bg-accent text-white text-sm np-btn-primary whitespace-nowrap disabled:opacity-60 disabled:cursor-not-allowed">
                                            Senden
                                        </button>
                                    </div>
                                </form>
                            </div>

                        </div>
                    </details>
                @empty
                    <div class="glass rounded-2xl px-5 py-12 text-center">
                        <svg class="mx-auto w-20 h-20 text-muted/20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <h3 class="mt-4 font-semibold text-text">Hier ist noch nichts zu sehen</h3>
                        <p class="mt-1 text-sm text-muted">
                            @if(request()->filled('kategorie_id') || request()->filled('semester_id'))
                                Mit den aktuellen Filtern wurden keine Noten gefunden.
                            @else
                                Für diesen Lernenden sind noch keine Noten erfasst.
                            @endif
                        </p>
                        <div class="mt-4 flex items-center justify-center gap-2">
                            @if(request()->filled('kategorie_id') || request()->filled('semester_id'))
                                <a href="{{ route('admin.lernende.noten.index', ['lernender_id' => $selectedLernenderId]) }}"
                                   class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-border text-sm text-text hover:bg-bg">
                                    Filter zurücksetzen
                                </a>
                            @endif
                            <a href="{{ route('admin.lernende.noten.create', ['lernender_id' => $selectedLernenderId]) }}"
                               class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-accent text-white text-sm font-semibold np-btn-primary">
                                <span class="text-lg leading-none">+</span>
                                Note erfassen
                            </a>
                        </div>
                    </div>
                @endforelse
            </div>

            {{-- Pagination --}}
            @if($notes->hasPages())
                <div class="glass rounded-2xl p-3">
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

            if (openedNoteId) {
                const target = document.querySelector(`details.np-details[data-note-id="${openedNoteId}"]`);
                if (target) {
                    if (!target.open) target.open = true;
                    setTimeout(() => target.scrollIntoView({ behavior: 'smooth', block: 'start' }), 50);
                }
            }
        });
    </script>

</x-app-layout>
