<x-app-layout>
    <x-slot name="title">{{ __('Noten :name', ['name' => $lernender->benutzer->vorname.' '.$lernender->benutzer->nachname]) }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :titel="__('Noten')" :untertitel="$lernender->benutzer->vorname.' '.$lernender->benutzer->nachname">
            <x-slot:aktionen>
                @if($neuCount > 0)
                    <form method="POST" action="{{ route("{$bereich}.learners.grades.seen_all", $lernender->lernender_id) }}"
                          x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                        @csrf
                        @foreach(['kategorie_id', 'semester_id'] as $f)
                            @if(request()->filled($f))
                                <input type="hidden" name="{{ $f }}" value="{{ request()->integer($f) }}">
                            @endif
                        @endforeach
                        <button type="submit" :disabled="loading"
                                class="inline-flex h-9 items-center gap-2 rounded-lg glass-btn px-3.5 text-sm font-medium text-accent disabled:opacity-60">
                            {{ __('Alle :anzahl als gesehen markieren', ['anzahl' => $neuCount]) }}
                        </button>
                    </form>
                @endif
                @can('noteAnlegen', $lernender)
                    <a href="{{ route("{$bereich}.learners.grades.create", $lernender->lernender_id) }}"
                       class="inline-flex h-9 items-center gap-2 rounded-lg bg-accent px-3.5 text-sm font-medium text-accent-contrast np-btn-primary">{{ __('+ Note erfassen') }}</a>
                @endcan
                <a href="{{ route("{$bereich}.learners.grades.print", $lernender->lernender_id) }}" target="_blank"
                   class="inline-flex h-9 items-center gap-2 rounded-lg glass-btn px-3.5 text-sm font-medium text-text">{{ __('Drucken') }}</a>
                <a href="{{ route("{$bereich}.learners.grades.export", ['lernender_id' => $lernender->lernender_id, ...request()->only(['semester_id', 'kategorie_id'])]) }}"
                   class="inline-flex h-9 items-center gap-2 rounded-lg glass-btn px-3.5 text-sm font-medium text-text">CSV</a>
                <a href="{{ route("{$bereich}.learners.show", $lernender->lernender_id) }}"
                   class="inline-flex h-9 items-center gap-2 rounded-lg glass-btn px-3.5 text-sm font-medium text-text">{{ __('Profil') }}</a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    @php
        $darfKorrigieren = auth()->user()->can('noteKorrigieren', $lernender);
        $darfLoeschen = auth()->user()->can('noteLoeschen', $lernender);
        $viewerId = (int) auth()->user()->benutzer_id;
        $label = 'text-sm font-medium text-text';
        $feld = 'mt-1 w-full rounded-xl border border-border bg-input text-text focus:ring-2 focus:ring-ring focus:border-ring';
        $gefiltert = request()->filled('kategorie_id') || request()->filled('semester_id');
    @endphp

    <div class="py-6">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-4">

            @php
                $a = $stand->auswertung;
                $semNr = $semesterId ?: $stand->semesterId;
                $detailUrl = route($bereich.'.learners.show', $lernender->lernender_id);
                $semNote = $semNr ? $a->semester($semNr)['note'] : null;
            @endphp
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <x-kachel :label="__('Gesamtschnitt')" :note="$a->gesamtNote" :href="$detailUrl" />
                <x-kachel :label="__('Semester :name', ['name' => $a->konfiguration->semesterName($semNr, $a->lernenderId)])" :note="$semNote" />
                <x-kachel :label="__('Prüfungen')" :wert="$notes->total()" :sub="$gefiltert ? __('im Filter') : null" />
                <x-kachel :label="__('Neu')" :wert="$neuCount" :ton="$neuCount ? 'accent' : 'neutral'" />
            </div>

            @if($stand->gruende)
                <div class="rounded-xl border border-border bg-card px-5 py-3 flex flex-wrap items-center gap-2">
                    <x-status :status="$stand->status" />
                    @foreach($stand->gruende as $g)
                        <span class="px-2 py-0.5 rounded-md text-xs {{ $stand->status === 'rot' ? 'bg-note-ungenuegend/10 text-note-ungenuegend' : 'bg-note-knapp/14 text-note-knapp' }}">{{ $g }}</span>
                    @endforeach
                </div>
            @endif

            {{-- Lernenden wechseln + Filter --}}
            <div class="rounded-xl border border-border bg-card p-4 grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">
                <div>
                    <label for="lernenden_wechseln" class="{{ $label }}">{{ __('Lernender') }}</label>
                    <select id="lernenden_wechseln" class="{{ $feld }}" onchange="if (this.value) window.location.href = this.value">
                        @foreach($switcher as $l)
                            <option value="{{ route("{$bereich}.learners.grades.index", $l->lernender_id) }}" @selected($l->lernender_id === $lernender->lernender_id)>
                                {{ $l->benutzer->nachname }} {{ $l->benutzer->vorname }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <form method="GET" action="{{ route("{$bereich}.learners.grades.index", $lernender->lernender_id) }}" class="contents">
                    <div>
                        <label for="kategorie_id" class="{{ $label }}">{{ __('Kategorie') }}</label>
                        <select id="kategorie_id" name="kategorie_id" onchange="this.form.submit()" class="{{ $feld }}">
                            <option value="">{{ __('Alle') }}</option>
                            @foreach($kategorien as $k)
                                <option value="{{ $k->kategorie_id }}" @selected(request('kategorie_id') == $k->kategorie_id)>{{ $k->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="semester_id" class="{{ $label }}">{{ __('Semester') }}</label>
                        <select id="semester_id" name="semester_id" onchange="this.form.submit()" class="{{ $feld }}">
                            <option value="">{{ __('Alle') }}</option>
                            @foreach($semester as $s)
                                <option value="{{ $s->semester_id }}" @selected(request('semester_id') == $s->semester_id)>{{ \App\Services\Auswertung\Konfiguration::ausDb()->semesterName((int) $s->semester_id, (int) $lernender->lernender_id) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <noscript>
                        <button type="submit" class="inline-flex items-center px-4 h-10 rounded-xl bg-accent text-accent-contrast text-sm np-btn-primary">{{ __('Filtern') }}</button>
                    </noscript>
                    @if($gefiltert)
                        <div class="flex">
                            <a href="{{ route("{$bereich}.learners.grades.index", $lernender->lernender_id) }}"
                               class="inline-flex items-center px-4 h-10 rounded-xl glass-btn text-text text-sm">{{ __('Zurücksetzen') }}</a>
                        </div>
                    @endif
                </form>
            </div>


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

                    <details class="np-details rounded-xl border border-border bg-card overflow-hidden {{ $istNeu ? 'ring-1 ring-accent/40' : '' }}" data-note-id="{{ $n->note_id }}">
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
                                            <span class="inline-flex px-1.5 py-0.5 rounded-full text-[11px] bg-bg border border-border text-muted">{{ $n->kommentare->count() }} {{ $n->kommentare->count() === 1 ? __('Kommentar') : __('Kommentare') }}</span>
                                        @endif
                                        @if($istNeu)
                                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-accent text-accent-contrast">{{ __('Neu') }}</span>
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
                                <span class="text-2xl font-bold tabular-nums leading-none {{ \App\Support\NotenSkala::text($wert) }}">{{ \App\Support\NotenSkala::format($wert) }}</span>
                                <span class="text-xs text-muted tabular-nums">{{ \App\Support\Zahl::prozent($n->gewichtung_prozent ?? 100) }}</span>
                            </div>
                        </summary>

                        <div class="border-t border-border">
                            <dl class="px-5 py-4 grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-2 text-sm">
                                <div><dt class="text-xs text-muted">{{ __('Semester') }}</dt><dd class="text-text">@if($n->semester_id)<x-semester :id="$n->semester_id" :lernender="$lernender" />@else–@endif</dd></div>
                                <div><dt class="text-xs text-muted">{{ __('Fach / Modul') }}</dt><dd class="text-text">{{ $thema }}</dd></div>
                                <div><dt class="text-xs text-muted">{{ __('Erfasst von') }}</dt><dd class="text-text">{{ $n->erfasstVonBenutzer?->vorname }} {{ $n->erfasstVonBenutzer?->nachname }}</dd></div>
                            </dl>

                            <div class="px-5 pb-4 flex flex-wrap items-center justify-between gap-3">
                                @if($istNeu)
                                    <form method="POST" action="{{ route("{$bereich}.learners.grades.seen", [$lernender->lernender_id, $n->note_id]) }}"
                                          x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                                        @csrf
                                        <button type="submit" :disabled="loading"
                                                class="inline-flex items-center gap-2 px-4 h-10 rounded-xl glass-btn text-accent text-sm font-medium disabled:opacity-60">{{ __('Als gesehen markieren') }}</button>
                                    </form>
                                @else
                                    <span class="text-xs text-muted">{{ __('Gesehen am :datum Uhr', ['datum' => $gesehen->gesehen_am->format('d.m.Y H:i')]) }}</span>
                                @endif

                                <div class="flex items-center gap-2">
                                    @if($darfKorrigieren)
                                        <a href="{{ route("{$bereich}.learners.grades.edit", [$lernender->lernender_id, $n->note_id]) }}"
                                           class="inline-flex items-center px-3 min-h-[36px] rounded-xl text-xs text-accent border border-accent/20 hover:bg-accent/10">{{ __('Korrigieren') }}</a>
                                    @endif
                                    @if($darfLoeschen)
                                        <form method="POST" action="{{ route("{$bereich}.learners.grades.destroy", [$lernender->lernender_id, $n->note_id]) }}"
                                              x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true"
                                              onsubmit="return confirm('{{ __('Note löschen?') }}');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" :disabled="loading"
                                                    class="inline-flex items-center px-3 min-h-[36px] rounded-xl text-xs text-note-ungenuegend hover:bg-note-ungenuegend/10 disabled:opacity-60">{{ __('Löschen') }}</button>
                                        </form>
                                    @endif
                                </div>
                            </div>

                            <div class="border-t border-border px-5 py-4 space-y-3">
                                <div class="{{ $label }}">{{ __('Kommentare') }}</div>
                                @forelse($n->kommentare as $k)
                                    <div class="bg-bg rounded-xl p-3 space-y-0.5">
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="text-xs text-muted">
                                                <span class="font-medium text-text">{{ $k->autor?->vorname }} {{ $k->autor?->nachname }}</span>
                                                &middot; {{ __(':datum Uhr', ['datum' => $k->erstellt_am->format('d.m.Y H:i')]) }}
                                            </div>
                                            @if((int) $k->autor_benutzer_id === $viewerId || $bereich === 'admin')
                                                <form method="POST" action="{{ route('comments.destroy', $k->kommentar_id) }}"
                                                      x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true"
                                                      onsubmit="return confirm('{{ __('Kommentar löschen?') }}');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button :disabled="loading" class="inline-flex items-center px-2 min-h-[32px] rounded-lg text-xs text-note-ungenuegend hover:bg-note-ungenuegend/10 disabled:opacity-60">{{ __('Löschen') }}</button>
                                                </form>
                                            @endif
                                        </div>
                                        <div class="text-sm text-text whitespace-pre-line">{{ $k->kommentar_text }}</div>
                                    </div>
                                @empty
                                    <div class="text-sm text-muted">{{ __('Noch keine Kommentare.') }}</div>
                                @endforelse
                            </div>

                            <form method="POST" action="{{ route('comments.store', $n->note_id) }}"
                                  class="border-t border-border px-5 py-4 flex gap-2 items-end"
                                  x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                                @csrf
                                <textarea name="kommentar_text" rows="2" maxlength="2000" required
                                          placeholder="{{ __('Kommentar schreiben…') }}" aria-label="{{ __('Kommentar schreiben') }}"
                                          onkeydown="if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') this.form.requestSubmit()"
                                          class="flex-1 rounded-xl border border-border bg-input text-text placeholder-muted text-sm px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring resize-y"></textarea>
                                <button type="submit" :disabled="loading"
                                        class="px-4 h-10 rounded-xl bg-accent text-accent-contrast text-sm np-btn-primary whitespace-nowrap disabled:opacity-60">{{ __('Senden') }}</button>
                            </form>
                        </div>
                    </details>
                @empty
                    <div class="rounded-xl border border-border bg-card px-5 py-12 text-center">
                        <h3 class="font-semibold text-text">{{ $gefiltert ? __('Keine Noten im Filter') : __('Noch keine Noten') }}</h3>
                        @if($gefiltert)
                            <a href="{{ route("{$bereich}.learners.grades.index", $lernender->lernender_id) }}"
                               class="mt-4 inline-flex items-center px-4 h-10 rounded-xl glass-btn text-text text-sm">{{ __('Filter zurücksetzen') }}</a>
                        @endif
                    </div>
                @endforelse
            </div>

            @if($notes->hasPages())
                <div class="rounded-xl border border-border bg-card p-3">{{ $notes->links() }}</div>
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
