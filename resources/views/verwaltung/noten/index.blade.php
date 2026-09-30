<x-app-layout>
    <x-slot name="title">{{ __('Noten :name', ['name' => $lernender->benutzer->vorname.' '.$lernender->benutzer->nachname]) }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :zurueck="route($bereich.'.learners.show', $lernender->lernender_id)" :titel="__('Noten')" :untertitel="$lernender->benutzer->vorname.' '.$lernender->benutzer->nachname">
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
                        <button type="submit" :disabled="loading" class="np-knopf np-knopf-sekundaer">
                            {{ __('Alle :anzahl als gesehen markieren', ['anzahl' => $neuCount]) }}
                        </button>
                    </form>
                @endif
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button type="button" aria-label="{{ __('Weitere Aktionen') }}" title="{{ __('Weitere Aktionen') }}" class="np-knopf np-knopf-sekundaer np-knopf-rund">
                            <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M4 10a1.5 1.5 0 113 0 1.5 1.5 0 01-3 0zm5 0a1.5 1.5 0 113 0 1.5 1.5 0 01-3 0zm5 0a1.5 1.5 0 113 0 1.5 1.5 0 01-3 0z"/></svg>
                        </button>
                    </x-slot>
                    <x-slot name="content">
                        <x-dropdown-link href="{{ route($bereich.'.learners.grades.print', $lernender->lernender_id) }}" target="_blank">{{ __('Drucken') }}</x-dropdown-link>
                        <x-dropdown-link href="{{ route($bereich.'.learners.grades.export', ['lernender_id' => $lernender->lernender_id, ...request()->only(['semester_id', 'kategorie_id'])]) }}">CSV</x-dropdown-link>
                    </x-slot>
                </x-dropdown>
                @can('noteAnlegen', $lernender)
                    <a href="{{ route("{$bereich}.learners.grades.create", $lernender->lernender_id) }}"
                       class="np-knopf np-knopf-primaer"><x-symbol name="plus" strich="2" />{{ __('Note erfassen') }}</a>
                @endcan
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    @php
        $darfKorrigieren = auth()->user()->can('noteKorrigieren', $lernender);
        $darfLoeschen = auth()->user()->can('noteLoeschen', $lernender);
        $viewerId = (int) auth()->user()->benutzer_id;
        $gefiltert = request()->filled('kategorie_id') || request()->filled('semester_id');
        $zuruecksetzen = route("{$bereich}.learners.grades.index", $lernender->lernender_id);
    @endphp

    <div class="py-6">
        <div class="mx-auto np-seite px-4 sm:px-6 lg:px-8 flex flex-col gap-4">

            @php
                $a = $stand->auswertung;
                $semNr = $semesterId ?: $stand->semesterId;
                $detailUrl = route($bereich.'.learners.show', $lernender->lernender_id);
                $semNote = $semNr ? $a->semester($semNr)['note'] : null;
            @endphp
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <x-kachel :label="__('Gesamtschnitt')" :note="$a->gesamtNote" :href="$detailUrl" />
                <x-kachel :label="$semNr ? $a->konfiguration->semesterName($semNr, $a->lernenderId) : __('Semester')" :note="$semNote" />
                <x-kachel :label="__('Prüfungen')" :wert="$notes->total()" :sub="$gefiltert ? __('im Filter') : null" />
                <x-kachel :label="__('Neu')" :wert="$neuCount" :ton="$neuCount ? 'accent' : 'neutral'" />
            </div>

            @if($stand->gruende)
                <div class="flex flex-wrap items-center gap-2">
                    <x-status :status="$stand->status" />
                    @foreach($stand->gruende as $g)
                        <span class="np-marke {{ $stand->status === 'rot' ? 'bg-note-ungenuegend/10 text-note-ungenuegend' : 'bg-note-knapp/14 text-note-knapp' }}">{{ $g }}</span>
                    @endforeach
                </div>
            @endif

            <x-filterleiste :action="$zuruecksetzen" :zaehler="$notes->total()" zaehler-label="{{ __('Prüfungen') }}"
                            :zurueck="$zuruecksetzen" :aktive-filter="(int) request()->filled('kategorie_id') + (int) request()->filled('semester_id')">
                <label for="lernenden_wechseln" class="sr-only">{{ __('Lernender') }}</label>
                <select id="lernenden_wechseln" class="np-feld np-feld-klein w-auto max-w-64" onchange="if (this.value) window.location.href = this.value">
                    @foreach($switcher as $l)
                        <option value="{{ route("{$bereich}.learners.grades.index", $l->lernender_id) }}" @selected($l->lernender_id === $lernender->lernender_id)>
                            {{ $l->benutzer->nachname }} {{ $l->benutzer->vorname }}
                        </option>
                    @endforeach
                </select>
                <label for="kategorie_id" class="sr-only">{{ __('Kategorie') }}</label>
                <select id="kategorie_id" name="kategorie_id" x-on:change="$el.form.requestSubmit()" class="np-feld np-feld-klein w-auto max-w-64">
                    <option value="">{{ __('Kategorie: alle') }}</option>
                    @foreach($kategorien as $k)
                        <option value="{{ $k->kategorie_id }}" @selected(request('kategorie_id') == $k->kategorie_id)>{{ $k->name }}</option>
                    @endforeach
                </select>
                <label for="semester_id" class="sr-only">{{ __('Semester') }}</label>
                <select id="semester_id" name="semester_id" x-on:change="$el.form.requestSubmit()" class="np-feld np-feld-klein w-auto max-w-64">
                    <option value="">{{ __('Semester: alle') }}</option>
                    @foreach($semester as $s)
                        <option value="{{ $s->semester_id }}" @selected(request('semester_id') == $s->semester_id)>{{ \App\Services\Auswertung\Konfiguration::ausDb()->semesterName((int) $s->semester_id, (int) $lernender->lernender_id) }}</option>
                    @endforeach
                </select>
            </x-filterleiste>

            @if($notes->isEmpty())
                <div class="np-karte flex flex-col items-center gap-3 px-5 py-12 text-center">
                    <h2 class="text-sm font-semibold text-text">{{ $gefiltert ? __('Keine Noten im Filter') : __('Noch keine Noten') }}</h2>
                    @if($gefiltert)
                        <a href="{{ $zuruecksetzen }}" class="np-knopf np-knopf-sekundaer">{{ __('Filter zurücksetzen') }}</a>
                    @endif
                </div>
            @else
                {{-- Eine gruppierte Liste statt Einzelkarten (HIG «Lists and tables»); Ungelesenes wie in Mail mit blauem Punkt. --}}
                <div class="np-karte divide-y divide-border overflow-hidden">
                    @foreach($notes as $n)
                        @php
                            $gesehen = $n->gesehen->first();
                            $istNeu = ! $gesehen
                                || $n->erstellt_am > $gesehen->gesehen_am
                                || $n->kommentare->contains(fn ($k) => $k->erstellt_am > $gesehen->gesehen_am);
                            $letzterKommentar = $n->kommentare->last();
                            $thema = $n->fach?->name
                                ?? ($n->modulBelegung?->modul ? $n->modulBelegung->modul->modul_nummer.' – '.$n->modulBelegung->modul->titel : '–');
                        @endphp
                        <details class="np-details group" data-note-id="{{ $n->note_id }}">
                            <summary class="flex cursor-pointer select-none list-none items-start gap-3 py-2.5 pl-3 pr-4 transition-colors duration-100 hover:bg-surface-2/60">
                                <span class="flex h-6 w-3 shrink-0 items-center justify-center" @if($istNeu) role="img" aria-label="{{ __('Neu') }}" title="{{ __('Neu') }}" @endif>
                                    @if($istNeu)<span class="size-2 rounded-full bg-accent"></span>@endif
                                </span>
                                <span class="np-chevron flex h-6 shrink-0 items-center text-muted" aria-hidden="true"><x-symbol name="chevron-right" strich="2" class="size-3.5" /></span>
                                <div class="grid min-w-0 flex-1 grid-cols-[6.5rem_minmax(0,1fr)] items-baseline gap-x-4">
                                    <span class="text-sm leading-6 tabular-nums text-muted">{{ $n->pruefungsdatum?->format('d.m.Y') }}</span>
                                    <span class="flex min-w-0 items-baseline gap-2">
                                        <span class="truncate text-sm font-medium leading-6 text-text">{{ $thema }}</span>
                                        <span class="shrink-0 text-xs text-muted">{{ $n->kategorie?->name ?? '–' }}</span>
                                    </span>
                                    <span></span>
                                    <div class="min-w-0">
                                        @if($n->titel)
                                            <div class="truncate text-xs text-muted">{{ $n->titel }}</div>
                                        @endif
                                        <x-note-geaendert :note="$n" :lernender-benutzer-id="$lernender->benutzer_id" />
                                        @if($letzterKommentar)
                                            <div class="max-w-xl truncate text-xs text-muted">
                                                <span class="font-medium">{{ $letzterKommentar->autor?->vorname }}</span>: {{ Str::limit($letzterKommentar->kommentar_text, 90) }}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                                <div class="flex h-6 shrink-0 items-center gap-4">
                                    @if($n->kommentare->isNotEmpty())
                                        <span class="inline-flex items-center gap-1 text-xs tabular-nums text-muted" title="{{ $n->kommentare->count() === 1 ? __('Kommentar') : __('Kommentare') }}">
                                            <svg class="size-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 3c-4.31 0-8 3.033-8 7 0 2.024.978 3.825 2.499 5.085a3.478 3.478 0 01-.522 1.756.75.75 0 00.584 1.143 5.976 5.976 0 003.936-1.108c.487.082.99.124 1.503.124 4.31 0 8-3.033 8-7s-3.69-7-8-7z" clip-rule="evenodd"/></svg>
                                            {{ $n->kommentare->count() }}<span class="sr-only"> {{ $n->kommentare->count() === 1 ? __('Kommentar') : __('Kommentare') }}</span>
                                        </span>
                                    @endif
                                    <span class="w-12 text-right text-xs tabular-nums text-muted">{{ \App\Support\Zahl::prozent($n->gewichtung_prozent ?? 100) }}</span>
                                    <x-note :wert="$n->note_wert" :stufe="$n->note_stufe" variante="badge" />
                                </div>
                            </summary>

                            <div class="border-t border-border bg-fill-2">
                                <div class="flex flex-wrap items-start justify-between gap-x-8 gap-y-3 px-5 py-4 pl-14">
                                    <dl class="grid grid-cols-2 gap-x-8 gap-y-2 text-sm sm:grid-cols-3">
                                        <div><dt class="text-xs text-muted">{{ __('Semester') }}</dt><dd class="text-text">@if($n->semester_id)<x-semester :id="$n->semester_id" :lernender="$lernender" />@else–@endif</dd></div>
                                        <div><dt class="text-xs text-muted">{{ __('Fach / Modul') }}</dt><dd class="text-text">{{ $thema }}</dd></div>
                                        <div><dt class="text-xs text-muted">{{ __('Erfasst von') }}</dt><dd class="text-text">{{ $n->erfasstVonBenutzer?->vorname }} {{ $n->erfasstVonBenutzer?->nachname }}</dd></div>
                                    </dl>
                                    <div class="flex flex-wrap items-center gap-2">
                                        @if($istNeu)
                                            <form method="POST" action="{{ route("{$bereich}.learners.grades.seen", [$lernender->lernender_id, $n->note_id]) }}"
                                                  x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                                                @csrf
                                                <button type="submit" :disabled="loading" class="np-knopf np-knopf-sekundaer np-knopf-klein">{{ __('Als gesehen markieren') }}</button>
                                            </form>
                                        @else
                                            <span class="text-xs text-muted">{{ __('Gesehen am :datum Uhr', ['datum' => $gesehen->gesehen_am->format('d.m.Y H:i')]) }}</span>
                                        @endif
                                        @if($darfKorrigieren)
                                            <a href="{{ route("{$bereich}.learners.grades.edit", [$lernender->lernender_id, $n->note_id]) }}"
                                               class="np-knopf np-knopf-sekundaer np-knopf-klein">{{ __('Korrigieren') }}</a>
                                        @endif
                                        @if($darfLoeschen)
                                            <form method="POST" action="{{ route("{$bereich}.learners.grades.destroy", [$lernender->lernender_id, $n->note_id]) }}"
                                                  x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true"
                                                  data-bestaetigen="{{ __('Note löschen?') }}" data-bestaetigen-knopf="{{ __('Löschen') }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" :disabled="loading" aria-label="{{ __('Note löschen') }}" title="{{ __('Löschen') }}"
                                                        class="np-knopf np-knopf-symbol np-knopf-symbol-gefahr np-knopf-klein"><x-symbol name="trash" /></button>
                                            </form>
                                        @endif
                                    </div>
                                </div>

                                <div class="flex flex-col gap-2 px-5 pb-4 pl-14">
                                    <div class="text-xs font-medium text-muted">{{ __('Kommentare') }}</div>
                                    @forelse($n->kommentare as $k)
                                        <div class="max-w-3xl rounded-xl bg-card px-3.5 py-2.5 shadow-e1">
                                            <div class="flex items-start justify-between gap-2">
                                                <div class="text-xs text-muted">
                                                    <span class="font-medium text-text">{{ $k->autor?->vorname }} {{ $k->autor?->nachname }}</span>
                                                    &middot; {{ __(':datum Uhr', ['datum' => $k->erstellt_am->format('d.m.Y H:i')]) }}
                                                </div>
                                                @if((int) $k->autor_benutzer_id === $viewerId || $bereich === 'admin')
                                                    <form method="POST" action="{{ route('comments.destroy', $k->kommentar_id) }}"
                                                          x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true"
                                                          data-bestaetigen="{{ __('Kommentar löschen?') }}" data-bestaetigen-knopf="{{ __('Löschen') }}">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button :disabled="loading" aria-label="{{ __('Kommentar löschen') }}" title="{{ __('Löschen') }}"
                                                                class="np-knopf np-knopf-symbol np-knopf-symbol-gefahr np-knopf-klein"><x-symbol name="trash" /></button>
                                                    </form>
                                                @endif
                                            </div>
                                            <div class="whitespace-pre-line text-sm text-text">{{ $k->kommentar_text }}</div>
                                        </div>
                                    @empty
                                        <div class="text-sm text-muted">{{ __('Noch keine Kommentare.') }}</div>
                                    @endforelse
                                </div>

                                <form method="POST" action="{{ route('comments.store', $n->note_id) }}"
                                      class="flex max-w-3xl items-end gap-2 px-5 pb-4 pl-14"
                                      x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                                    @csrf
                                    <textarea name="kommentar_text" rows="2" maxlength="2000" required
                                              placeholder="{{ __('Kommentar schreiben…') }}" aria-label="{{ __('Kommentar schreiben') }}"
                                              onkeydown="if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') this.form.requestSubmit()"
                                              class="np-feld min-w-0 flex-1 resize-y"></textarea>
                                    <button type="submit" :disabled="loading" class="np-knopf np-knopf-sekundaer">{{ __('Senden') }}</button>
                                </form>
                            </div>
                        </details>
                    @endforeach
                </div>
            @endif

            @if($notes->hasPages())
                <div class="px-1">{{ $notes->links() }}</div>
            @endif
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const offen = {{ session('opened_note') ? (int) session('opened_note') : 'null' }};
            const el = offen && document.querySelector(`details.np-details[data-note-id="${offen}"]`);
            if (el) {
                el.open = true;
                setTimeout(() => el.scrollIntoView({ behavior: 'smooth', block: 'start' }), 50);
            }
        });
    </script>
</x-app-layout>
