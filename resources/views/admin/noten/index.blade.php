<x-app-layout>
    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-text">
                Noten:
                <span class="text-muted">{{ $selectedLernender->nachname ?? '' }} {{ $selectedLernender->vorname ?? '' }}</span>
            </h2>
            <div class="flex gap-2">
                <a href="{{ route('admin.lernende.noten.create', ['lernender_id' => $selectedLernenderId]) }}"
                   class="px-4 py-2 h-10 rounded-xl bg-accent text-white hover:opacity-90 whitespace-nowrap text-sm">
                    + Note erfassen
                </a>
                <a href="{{ route('admin.lernende.show', $selectedLernenderId) }}"
                   class="px-4 py-2 h-10 rounded-xl bg-card text-text border border-border hover:bg-bg whitespace-nowrap text-sm">
                    Zum Profil
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

            {{-- Lernenden-Switcher --}}
            <div class="bg-card border border-border rounded-2xl shadow-sm p-4">
                <label class="text-sm font-medium text-muted">Lernenden wechseln</label>
                <select class="mt-1 w-full sm:w-80 rounded-xl border border-border bg-input text-text focus:ring-2 focus:ring-ring focus:border-ring"
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
            <div class="bg-card border border-border rounded-2xl shadow-sm p-4">
                <form method="GET"
                      action="{{ route('admin.lernende.noten.index', ['lernender_id' => $selectedLernenderId]) }}"
                      class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">

                    <div>
                        <label class="text-sm font-medium text-muted">Kategorie</label>
                        <select name="kategorie_id"
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
                        <button class="px-4 py-2 h-10 rounded-xl bg-accent text-white hover:opacity-90">
                            Filtern
                        </button>
                        <a href="{{ route('admin.lernende.noten.index', ['lernender_id' => $selectedLernenderId]) }}"
                           class="px-4 py-2 h-10 rounded-xl bg-card text-text border border-border hover:bg-bg">
                            Reset
                        </a>
                    </div>
                </form>
            </div>

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
                        $noteColor = $noteWert >= 4.0
                            ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300'
                            : ($noteWert >= 3.5
                                ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300'
                                : 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300');
                    @endphp

                    <details class="np-details bg-card border border-border rounded-2xl shadow-sm overflow-hidden">

                        <summary class="cursor-pointer select-none px-4 py-3 flex items-start justify-between gap-3 list-none hover:bg-bg">
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
                            </div>

                            {{-- Admin-Aktionen --}}
                            <div class="border-t border-border px-5 py-3 flex items-center justify-end gap-3">
                                <a href="{{ route('admin.lernende.noten.edit', [$selectedLernenderId, $n->note_id]) }}"
                                   class="px-3 py-1.5 rounded-xl text-xs text-accent border border-accent/20 hover:bg-accent/10">
                                    Bearbeiten
                                </a>
                                <form method="POST"
                                      action="{{ route('admin.lernende.noten.destroy', [$selectedLernenderId, $n->note_id]) }}"
                                      onsubmit="return confirm('Note wirklich löschen?')">
                                    @csrf @method('DELETE')
                                    <button type="submit"
                                            class="px-3 py-1.5 rounded-xl text-xs text-red-500 border border-red-200 hover:bg-red-50 dark:border-red-800 dark:hover:bg-red-900/20">
                                        Löschen
                                    </button>
                                </form>
                            </div>

                            {{-- Kommentar-Thread --}}
                            <div class="border-t border-border px-5 py-4 space-y-3">
                                <div class="text-xs font-semibold uppercase tracking-wider text-muted">Kommentare</div>

                                @forelse($n->kommentare as $k)
                                    <div class="bg-bg rounded-xl p-3 space-y-0.5">
                                        <div class="text-xs text-muted">
                                            <span class="font-medium text-text">{{ $k->autor?->vorname }} {{ $k->autor?->nachname }}</span>
                                            &middot;
                                            {{ $k->erstellt_am->format('d.m.Y H:i') }} Uhr
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
                                                class="px-4 py-2 rounded-xl bg-accent text-white text-sm hover:opacity-90 whitespace-nowrap">
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
        });
    </script>

</x-app-layout>
