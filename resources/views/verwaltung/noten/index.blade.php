<x-app-layout>
    <x-slot name="title">Noten {{ $lernender->benutzer->vorname }} {{ $lernender->benutzer->nachname }}</x-slot>
    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-4 flex-wrap">
            <h2 class="font-semibold text-xl text-text">
                Noten:
                <span class="text-muted">{{ $lernender->benutzer->nachname }} {{ $lernender->benutzer->vorname }}</span>
            </h2>
            <div class="flex items-center gap-2 flex-wrap">
                @if($neuCount > 0)
                    <form method="POST" action="{{ route("{$bereich}.lernende.noten.alle_gesehen", $lernender->lernender_id) }}"
                          x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                        @csrf
                        @foreach(['kategorie_id', 'semester_id'] as $f)
                            @if(request()->filled($f))
                                <input type="hidden" name="{{ $f }}" value="{{ request()->integer($f) }}">
                            @endif
                        @endforeach
                        <button type="submit" :disabled="loading"
                                class="inline-flex items-center gap-2 px-4 h-10 rounded-xl bg-green-600 hover:bg-green-700 text-white text-sm font-medium whitespace-nowrap disabled:opacity-60">
                            Alle {{ $neuCount }} als gesehen markieren
                        </button>
                    </form>
                @endif
                @can('noteAnlegen', $lernender)
                    <a href="{{ route("{$bereich}.lernende.noten.create", $lernender->lernender_id) }}"
                       class="inline-flex items-center px-4 h-10 rounded-xl bg-accent text-white np-btn-primary whitespace-nowrap text-sm">+ Note erfassen</a>
                @endcan
                <a href="{{ route("{$bereich}.lernende.noten.drucken", $lernender->lernender_id) }}" target="_blank"
                   class="inline-flex items-center px-4 h-10 rounded-xl glass-btn text-text whitespace-nowrap text-sm">Drucken</a>
                <a href="{{ route("{$bereich}.lernende.noten.export", ['lernender_id' => $lernender->lernender_id, ...request()->only(['semester_id', 'kategorie_id'])]) }}"
                   class="inline-flex items-center px-4 h-10 rounded-xl glass-btn text-text whitespace-nowrap text-sm">CSV</a>
                <a href="{{ route("{$bereich}.lernende.show", $lernender->lernender_id) }}"
                   class="inline-flex items-center px-4 h-10 rounded-xl glass-btn text-text whitespace-nowrap text-sm">Profil</a>
            </div>
        </div>
    </x-slot>

    @php
        $darfKorrigieren = auth()->user()->can('noteKorrigieren', $lernender);
        $darfLoeschen = auth()->user()->can('noteLoeschen', $lernender);
        $viewerId = (int) auth()->user()->benutzer_id;
        $notenfarbe = fn (?float $v) => $v === null ? 'text-muted'
            : ($v >= 5.0 ? 'text-green-700 dark:text-green-400'
            : ($v >= 4.0 ? 'text-emerald-700 dark:text-emerald-400'
            : ($v >= 3.5 ? 'text-yellow-700 dark:text-yellow-400'
            : 'text-red-600 dark:text-red-400')));
        $label = 'text-xs uppercase tracking-widest text-muted font-medium';
        $feld = 'mt-1 w-full rounded-xl border border-border bg-input text-text focus:ring-2 focus:ring-ring focus:border-ring';
        $gefiltert = request()->filled('kategorie_id') || request()->filled('semester_id');
        $avg = $statsRow?->avg_weighted !== null ? (float) $statsRow->avg_weighted : null;
    @endphp

    <div class="py-6">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if($currentSemAvg !== null && $currentSemAvg < 4.0)
                <div class="glass rounded-2xl p-4 border border-red-500/30 text-sm text-red-600 dark:text-red-400 font-medium">
                    Aktueller Semester-Ø {{ number_format($currentSemAvg, 2) }} – unter 4.0
                </div>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="glass rounded-2xl p-4">
                    <div class="{{ $label }} mb-2">Ausbildung</div>
                    <div class="text-sm text-text font-medium">{{ $lernender->lehrberuf?->name ?? '–' }}</div>
                    <div class="mt-1 text-xs text-muted">
                        {{ $lernender->lehrbeginn?->format('d.m.Y') ?? '–' }} – {{ $lernender->lehrende?->format('d.m.Y') ?? 'offen' }}
                    </div>
                </div>
                <div class="glass rounded-2xl p-4 flex flex-wrap gap-6 text-sm">
                    <div>
                        <div class="{{ $label }}">Noten</div>
                        <div class="font-bold text-lg text-text tabular-nums">{{ (int) ($statsRow->total ?? 0) }}</div>
                    </div>
                    <div>
                        <div class="{{ $label }}">Ø gewichtet</div>
                        <div class="font-bold text-lg tabular-nums {{ $notenfarbe($avg) }}">{{ $avg !== null ? number_format($avg, 2) : '–' }}</div>
                    </div>
                    <div>
                        <div class="{{ $label }}">Bestanden</div>
                        <div class="font-bold text-lg text-text tabular-nums">{{ (int) ($statsRow->passed ?? 0) }} / {{ (int) ($statsRow->total ?? 0) }}</div>
                    </div>
                </div>
            </div>

            <x-noten-verlauf :points="$notenVerlauf" title="Notenverlauf" subtitle="letzte {{ $notenVerlauf->count() }} Noten" />

            {{-- Lernenden wechseln + Filter --}}
            <div class="glass rounded-2xl p-4 grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
                <div>
                    <label for="lernenden_wechseln" class="{{ $label }}">Lernender</label>
                    <select id="lernenden_wechseln" class="{{ $feld }}" onchange="if (this.value) window.location.href = this.value">
                        @foreach($switcher as $l)
                            <option value="{{ route("{$bereich}.lernende.noten.index", $l->lernender_id) }}" @selected($l->lernender_id === $lernender->lernender_id)>
                                {{ $l->benutzer->nachname }} {{ $l->benutzer->vorname }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <form method="GET" action="{{ route("{$bereich}.lernende.noten.index", $lernender->lernender_id) }}" class="contents">
                    <div>
                        <label for="kategorie_id" class="{{ $label }}">Kategorie</label>
                        <select id="kategorie_id" name="kategorie_id" onchange="this.form.submit()" class="{{ $feld }}">
                            <option value="">Alle</option>
                            @foreach($kategorien as $k)
                                <option value="{{ $k->kategorie_id }}" @selected(request('kategorie_id') == $k->kategorie_id)>{{ $k->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="semester_id" class="{{ $label }}">Semester</label>
                        <select id="semester_id" name="semester_id" onchange="this.form.submit()" class="{{ $feld }}">
                            <option value="">Alle</option>
                            @foreach($semester as $s)
                                <option value="{{ $s->semester_id }}" @selected(request('semester_id') == $s->semester_id)>{{ $s->bezeichnung }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <button class="px-4 h-10 rounded-xl bg-accent text-white np-btn-primary text-sm">Filtern</button>
                        @if($gefiltert)
                            <a href="{{ route("{$bereich}.lernende.noten.index", $lernender->lernender_id) }}"
                               class="inline-flex items-center px-4 h-10 rounded-xl glass-btn text-text text-sm">Zurücksetzen</a>
                        @endif
                    </div>
                </form>
            </div>

            <x-fach-modul-stats :stats="$fachStats" />

            <div class="space-y-2">
                @forelse($notes as $n)
                    @php
                        $gesehen = $n->gesehen->first();
                        $istNeu = ! $gesehen
                            || $n->erstellt_am > $gesehen->gesehen_am
                            || $n->kommentare->contains(fn ($k) => $k->erstellt_am > $gesehen->gesehen_am);
                        $letzterKommentar = $n->kommentare->last();
                        $thema = $n->fach?->name
                            ?? ($n->modulBelegung?->modul ? $n->modulBelegung->modul->modul_nummer.' – '.$n->modulBelegung->modul->titel : '–');
                        $wert = (float) $n->note_wert;
                    @endphp

                    <details class="np-details glass rounded-2xl overflow-hidden {{ $istNeu ? 'ring-1 ring-accent/40' : '' }}" data-note-id="{{ $n->note_id }}">
                        <summary class="cursor-pointer select-none px-4 py-3 flex items-start justify-between gap-3 list-none hover:bg-accent/5 transition-colors duration-100">
                            <div class="flex items-start gap-3 min-w-0">
                                <span class="np-chevron text-muted transition-transform duration-200 shrink-0 mt-0.5">
                                    <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L10.94 10 7.23 6.29a.75.75 0 1 1 1.06-1.06l4.24 4.24c.3.3.3.77 0 1.06l-4.24 4.24a.75.75 0 0 1-1.06.02z" clip-rule="evenodd"/>
                                    </svg>
                                </span>
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-medium text-text">{{ $n->pruefungsdatum?->format('d.m.Y') }}</span>
                                        <span class="text-xs text-muted">{{ $n->kategorie?->name ?? '–' }}</span>
                                        @if($n->kommentare->isNotEmpty())
                                            <span class="inline-flex px-1.5 py-0.5 rounded-full text-[11px] bg-bg border border-border text-muted">{{ $n->kommentare->count() }} {{ $n->kommentare->count() === 1 ? 'Kommentar' : 'Kommentare' }}</span>
                                        @endif
                                        @if($istNeu)
                                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-accent text-white">Neu</span>
                                        @endif
                                    </div>
                                    <div class="text-sm text-muted mt-0.5 truncate">{{ $thema }}</div>
                                    @if($n->titel)
                                        <div class="text-xs text-muted truncate">{{ $n->titel }}</div>
                                    @endif
                                    <x-note-geaendert :note="$n" :lernender-benutzer-id="$lernender->benutzer_id" />
                                    @if($letzterKommentar)
                                        <div class="mt-1.5 text-xs text-muted italic truncate max-w-sm">
                                            <span class="font-medium not-italic">{{ $letzterKommentar->autor?->vorname }}</span>:
                                            {{ Str::limit($letzterKommentar->kommentar_text, 80) }}
                                        </div>
                                    @endif
                                </div>
                            </div>
                            <div class="shrink-0 flex flex-col items-end gap-0.5">
                                <span class="text-2xl font-bold tabular-nums leading-none {{ $notenfarbe($wert) }}">{{ number_format($wert, 2) }}</span>
                                <span class="text-xs text-muted tabular-nums">{{ $n->gewichtung_prozent ?? 100 }}%</span>
                            </div>
                        </summary>

                        <div class="border-t border-border">
                            <dl class="px-5 py-4 grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-2 text-sm">
                                <div><dt class="text-xs text-muted">Semester</dt><dd class="text-text">{{ $n->semester?->bezeichnung ?? '–' }}</dd></div>
                                <div><dt class="text-xs text-muted">Fach / Modul</dt><dd class="text-text">{{ $thema }}</dd></div>
                                @if($n->gruppe)
                                    <div><dt class="text-xs text-muted">Gruppe</dt><dd class="text-text">{{ $n->gruppe->bezeichnung }}</dd></div>
                                @endif
                                <div><dt class="text-xs text-muted">Erfasst von</dt><dd class="text-text">{{ $n->erfasstVonBenutzer?->vorname }} {{ $n->erfasstVonBenutzer?->nachname }}</dd></div>
                            </dl>

                            <div class="px-5 pb-4 flex flex-wrap items-center justify-between gap-3">
                                @if($istNeu)
                                    <form method="POST" action="{{ route("{$bereich}.lernende.noten.gesehen", [$lernender->lernender_id, $n->note_id]) }}"
                                          x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                                        @csrf
                                        <button type="submit" :disabled="loading"
                                                class="inline-flex items-center gap-2 px-4 h-10 rounded-xl bg-green-600 hover:bg-green-700 text-white text-sm font-medium disabled:opacity-60">Als gesehen markieren</button>
                                    </form>
                                @else
                                    <span class="text-xs text-muted">Gesehen am {{ $gesehen->gesehen_am->format('d.m.Y H:i') }} Uhr</span>
                                @endif

                                <div class="flex items-center gap-2">
                                    @if($darfKorrigieren)
                                        <a href="{{ route("{$bereich}.lernende.noten.edit", [$lernender->lernender_id, $n->note_id]) }}"
                                           class="inline-flex items-center px-3 min-h-[36px] rounded-xl text-xs text-accent border border-accent/20 hover:bg-accent/10">Korrigieren</a>
                                    @endif
                                    @if($darfLoeschen)
                                        <form method="POST" action="{{ route("{$bereich}.lernende.noten.destroy", [$lernender->lernender_id, $n->note_id]) }}"
                                              x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true"
                                              onsubmit="return confirm('Note löschen?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" :disabled="loading"
                                                    class="inline-flex items-center px-3 min-h-[36px] rounded-xl text-xs text-red-600 dark:text-red-400 border border-red-500/30 hover:bg-red-500/10 disabled:opacity-60">Löschen</button>
                                        </form>
                                    @endif
                                </div>
                            </div>

                            <div class="border-t border-border px-5 py-4 space-y-3">
                                <div class="{{ $label }}">Kommentare</div>
                                @forelse($n->kommentare as $k)
                                    <div class="bg-bg rounded-xl p-3 space-y-0.5">
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="text-xs text-muted">
                                                <span class="font-medium text-text">{{ $k->autor?->vorname }} {{ $k->autor?->nachname }}</span>
                                                &middot; {{ $k->erstellt_am->format('d.m.Y H:i') }} Uhr
                                            </div>
                                            @if((int) $k->autor_benutzer_id === $viewerId || $bereich === 'admin')
                                                <form method="POST" action="{{ route('noten.kommentare.destroy', $k->kommentar_id) }}"
                                                      x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true"
                                                      onsubmit="return confirm('Kommentar löschen?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button :disabled="loading" class="inline-flex items-center px-2 min-h-[32px] rounded-lg text-xs text-red-600 dark:text-red-400 hover:bg-red-500/10 disabled:opacity-60">Löschen</button>
                                                </form>
                                            @endif
                                        </div>
                                        <div class="text-sm text-text whitespace-pre-line">{{ $k->kommentar_text }}</div>
                                    </div>
                                @empty
                                    <div class="text-sm text-muted">Noch keine Kommentare.</div>
                                @endforelse
                            </div>

                            <form method="POST" action="{{ route('noten.kommentare.store', $n->note_id) }}"
                                  class="border-t border-border px-5 py-4 flex gap-2 items-end"
                                  x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                                @csrf
                                <textarea name="kommentar_text" rows="2" maxlength="2000" required
                                          placeholder="Kommentar schreiben…" aria-label="Kommentar schreiben"
                                          onkeydown="if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') this.form.requestSubmit()"
                                          class="flex-1 rounded-xl border border-border bg-input text-text placeholder-muted text-sm px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring resize-y"></textarea>
                                <button type="submit" :disabled="loading"
                                        class="px-4 h-10 rounded-xl bg-accent text-white text-sm np-btn-primary whitespace-nowrap disabled:opacity-60">Senden</button>
                            </form>
                        </div>
                    </details>
                @empty
                    <div class="glass rounded-2xl px-5 py-12 text-center">
                        <h3 class="font-semibold text-text">{{ $gefiltert ? 'Keine Noten im Filter' : 'Noch keine Noten' }}</h3>
                        @if($gefiltert)
                            <a href="{{ route("{$bereich}.lernende.noten.index", $lernender->lernender_id) }}"
                               class="mt-4 inline-flex items-center px-4 h-10 rounded-xl glass-btn text-text text-sm">Filter zurücksetzen</a>
                        @endif
                    </div>
                @endforelse
            </div>

            @if($notes->hasPages())
                <div class="glass rounded-2xl p-3">{{ $notes->links() }}</div>
            @endif
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('details.np-details').forEach((d) => {
                const chevron = d.querySelector('.np-chevron');
                if (!chevron) return;
                const sync = () => chevron.classList.toggle('rotate-90', d.open);
                sync();
                d.addEventListener('toggle', sync);
            });

            const offen = {{ session('opened_note') ? (int) session('opened_note') : 'null' }};
            const el = offen && document.querySelector(`details.np-details[data-note-id="${offen}"]`);
            if (el) {
                el.open = true;
                setTimeout(() => el.scrollIntoView({ behavior: 'smooth', block: 'start' }), 50);
            }
        });
    </script>
</x-app-layout>
