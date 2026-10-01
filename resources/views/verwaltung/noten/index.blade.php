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
        <div class="mx-auto np-seite px-8 flex flex-col gap-4">

            @php
                $a = $stand->auswertung;
                $semNr = $semesterId ?: $stand->semesterId;
                $detailUrl = route($bereich.'.learners.show', $lernender->lernender_id);
                $semNote = $semNr ? $a->semester($semNr)['note'] : null;
            @endphp
            <div class="grid grid-cols-4 gap-4">
                <x-kachel :label="__('Gesamtschnitt')" :note="$a->gesamtNote" wert="–" :href="$detailUrl" />
                <x-kachel :label="$semNr ? $a->konfiguration->semesterName($semNr, $a->lernenderId) : __('Semester')" :note="$semNote" wert="–" />
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
                <select id="lernenden_wechseln" class="np-feld np-feld-klein w-auto max-w-64" x-data x-on:change="if ($el.value) window.location.href = $el.value">
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
                <x-leer class="np-karte" symbol="clipboard-document-check" :titel="$gefiltert ? __('Keine Noten im Filter') : __('Noch keine Noten')">
                    @if($gefiltert)
                        <a href="{{ $zuruecksetzen }}" class="np-knopf np-knopf-sekundaer">{{ __('Filter zurücksetzen') }}</a>
                    @endif
                </x-leer>
            @else
                @php
                    // Nach Speichern, Kommentar oder «gesehen» kommt die Seite mit derselben Note zurück; ein Link mit ?_open= ebenso
                    $start = (int) (session('opened_note') ?: request()->integer('_open'));
                    $ids = $notes->pluck('note_id')->map(fn ($id) => (int) $id)->all();
                    $start = in_array($start, $ids, true) ? $start : $ids[0];
                    $infos = $notes->mapWithKeys(fn ($n) => [$n->note_id => [
                        'gesehen' => $gesehen = $n->gesehen->first(),
                        'neu' => \App\Support\Ungelesen::istNeu($n, $gesehen?->gesehen_am, (int) auth()->id()),
                        'thema' => $n->fach?->name
                            ?? ($n->modulBelegung?->modul ? $n->modulBelegung->modul->modul_nummer.' – '.$n->modulBelegung->modul->titel : '–'),
                    ]]);
                @endphp
                {{-- Liste links, gewählte Note rechts wie in Mail (HIG «Split views»); Ungelesenes mit blauem Punkt --}}
                <div class="grid grid-cols-[minmax(22rem,30rem)_minmax(0,1fr)] items-start gap-4"
                     x-data="npAuswahlliste({ reihenfolge: @js($ids), start: {{ $start }}, praefix: 'note' })">

                    <div class="flex flex-col gap-3">
                        <div class="np-karte p-1.5">
                            <div role="listbox" x-ref="liste" aria-label="{{ __('Noten') }}" class="flex flex-col gap-0.5"
                                 @keydown.arrow-down.prevent="bewegen(1)" @keydown.arrow-up.prevent="bewegen(-1)"
                                 @keydown.home.prevent="bewegen(-Infinity)" @keydown.end.prevent="bewegen(Infinity)">
                                @foreach($notes as $n)
                                    @php
                                        $id = (int) $n->note_id;
                                        ['neu' => $istNeu, 'thema' => $thema] = $infos[$id];
                                        $letzterKommentar = $n->kommentare->last();
                                        $vorschau = $n->titel ?: ($letzterKommentar ? $letzterKommentar->autor?->vorname.': '.$letzterKommentar->kommentar_text : null);
                                    @endphp
                                    <div role="option" id="note-{{ $id }}" data-auswahl="{{ $id }}" data-note-id="{{ $id }}"
                                         aria-selected="{{ $id === $start ? 'true' : 'false' }}" tabindex="{{ $id === $start ? 0 : -1 }}"
                                         :aria-selected="gewaehlt === {{ $id }} ? 'true' : 'false'" :tabindex="gewaehlt === {{ $id }} ? 0 : -1"
                                         @click="waehlen({{ $id }})" class="np-listenzeile">
                                        @if($istNeu)
                                            <span class="absolute left-2 top-4 size-2 rounded-full bg-accent" role="img" aria-label="{{ __('Neu') }}" title="{{ __('Neu') }}"></span>
                                        @endif
                                        <div class="flex items-start gap-3">
                                            <div class="min-w-0 flex-1">
                                                <div @class(['truncate text-sm text-text', 'font-semibold' => $istNeu, 'font-medium' => ! $istNeu])>{{ $thema }}</div>
                                                <div class="mt-0.5 flex min-w-0 items-center gap-1.5 text-xs text-muted">
                                                    <time datetime="{{ $n->pruefungsdatum?->toDateString() }}" class="shrink-0 tabular-nums">{{ $n->pruefungsdatum?->format('d.m.Y') }}</time>
                                                    <span aria-hidden="true">·</span>
                                                    <span class="truncate">{{ $n->kategorie?->name ?? '–' }}</span>
                                                    <span aria-hidden="true">·</span>
                                                    <span class="shrink-0 tabular-nums">{{ \App\Support\Zahl::prozent($n->gewichtung_prozent ?? 100) }}</span>
                                                </div>
                                                @if($vorschau)
                                                    <p class="mt-0.5 truncate text-xs text-muted">{{ Str::limit($vorschau, 120) }}</p>
                                                @endif
                                            </div>
                                            <div class="flex shrink-0 flex-col items-end gap-1">
                                                <x-note :wert="$n->note_wert" :stufe="$n->note_stufe" variante="badge" />
                                                @if($n->kommentare->isNotEmpty())
                                                    <span class="inline-flex items-center gap-1 text-xs tabular-nums text-muted">
                                                        <x-symbol name="chat-bubble-oval-left" class="size-3.5" />{{ $n->kommentare->count() }}<span class="sr-only"> {{ $n->kommentare->count() === 1 ? __('Kommentar') : __('Kommentare') }}</span>
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        @if($notes->hasPages())
                            <div class="px-1">{{ $notes->links() }}</div>
                        @endif
                    </div>

                    {{-- Gewählte Note in natürlicher Höhe (höchstens so hoch wie das Fenster), bleibt beim Scrollen stehen;
                         @container steuert die Inspektor-Spalte (@4xl) nach der Breite der Karte --}}
                    <div x-ref="detail" class="np-karte @container sticky top-[calc(var(--np-symbolleiste-hoehe)+1rem)] max-h-[calc(100dvh-var(--np-symbolleiste-hoehe)-2rem)] max-w-6xl self-start overflow-y-auto">
                        @foreach($notes as $n)
                            @php
                                $id = (int) $n->note_id;
                                ['gesehen' => $gesehen, 'neu' => $istNeu, 'thema' => $thema] = $infos[$id];
                            @endphp
                            <article x-show="gewaehlt === {{ $id }}" @if($id !== $start) x-cloak @endif aria-labelledby="note-titel-{{ $id }}"
                                     class="grid @4xl:grid-cols-[minmax(0,1fr)_19rem]">
                                <div class="min-w-0">
                                    <header class="flex items-start gap-6 border-b border-border px-6 py-5">
                                        <div class="min-w-0 flex-1">
                                            <h2 id="note-titel-{{ $id }}" class="text-lg font-semibold text-text">{{ $thema }}</h2>
                                            <p class="mt-0.5 text-sm text-muted">
                                                {{ $n->kategorie?->name ?? '–' }}
                                                @if($n->semester_id)<span aria-hidden="true">·</span> <x-semester :id="$n->semester_id" :lernender="$lernender" />@endif
                                                @if($n->pruefungsdatum)<span aria-hidden="true">·</span> <time datetime="{{ $n->pruefungsdatum->toDateString() }}">{{ \App\Support\Format::date($n->pruefungsdatum) }}</time>@endif
                                            </p>
                                            @if($n->titel)
                                                <p class="mt-3 max-w-3xl break-words text-base text-text">{{ $n->titel }}</p>
                                            @endif
                                        </div>
                                        <div class="flex shrink-0 flex-col items-end">
                                            <x-note :wert="$n->note_wert" :stufe="$n->note_stufe" variante="hero" class="text-3xl leading-none" />
                                            <span class="mt-1.5 text-xs tabular-nums text-muted">{{ __('Gewicht :prozent', ['prozent' => \App\Support\Zahl::prozent($n->gewichtung_prozent ?? 100)]) }}</span>
                                        </div>
                                    </header>

                                    <section aria-labelledby="kommentare-{{ $id }}" class="flex flex-col gap-4 px-6 py-5">
                                        <h3 id="kommentare-{{ $id }}" class="text-sm font-semibold text-text">{{ __('Kommentare') }}</h3>
                                        @if($n->kommentare->isNotEmpty())
                                            <ol class="flex max-w-3xl flex-col gap-4">
                                                @foreach($n->kommentare as $k)
                                                    <li class="flex items-start gap-3">
                                                        <span class="np-monogramm size-8 shrink-0 text-2xs" aria-hidden="true">{{ mb_strtoupper(mb_substr($k->autor?->vorname ?? '', 0, 1).mb_substr($k->autor?->nachname ?? '', 0, 1)) ?: '?' }}</span>
                                                        <div class="min-w-0 flex-1">
                                                            <div class="flex items-baseline gap-2">
                                                                <span class="truncate text-sm font-medium text-text">{{ $k->autor?->vorname }} {{ $k->autor?->nachname }}</span>
                                                                <time datetime="{{ $k->erstellt_am->toIso8601String() }}" class="shrink-0 text-xs tabular-nums text-muted">{{ __(':datum Uhr', ['datum' => $k->erstellt_am->format('d.m.Y H:i')]) }}</time>
                                                                @if((int) $k->autor_benutzer_id === $viewerId || $bereich === 'admin')
                                                                    <form method="POST" action="{{ route('comments.destroy', $k->kommentar_id) }}" class="ml-auto"
                                                                          x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true"
                                                                          data-bestaetigen="{{ __('Kommentar löschen?') }}" data-bestaetigen-knopf="{{ __('Löschen') }}">
                                                                        @csrf
                                                                        @method('DELETE')
                                                                        <button :disabled="loading" aria-label="{{ __('Kommentar löschen') }}" title="{{ __('Löschen') }}"
                                                                                class="np-knopf np-knopf-symbol np-knopf-symbol-gefahr np-knopf-klein"><x-symbol name="trash" /></button>
                                                                    </form>
                                                                @endif
                                                            </div>
                                                            <p class="mt-0.5 whitespace-pre-line break-words text-sm text-text">{{ $k->kommentar_text }}</p>
                                                        </div>
                                                    </li>
                                                @endforeach
                                            </ol>
                                        @else
                                            <p class="text-sm text-muted">{{ __('Noch keine Kommentare.') }}</p>
                                        @endif

                                        <form method="POST" action="{{ route('comments.store', $n->note_id) }}" class="flex max-w-3xl items-end gap-2"
                                              x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                                            @csrf
                                            <label for="kommentar-{{ $id }}" class="sr-only">{{ __('Kommentar schreiben') }}</label>
                                            <textarea id="kommentar-{{ $id }}" name="kommentar_text" rows="2" maxlength="2000" required
                                                      placeholder="{{ __('Kommentar schreiben…') }}"
                                                      x-on:keydown.ctrl.enter="$el.form.requestSubmit()" x-on:keydown.meta.enter="$el.form.requestSubmit()"
                                                      class="np-feld min-w-0 flex-1 resize-y"></textarea>
                                            <button type="submit" :disabled="loading" class="np-knopf np-knopf-sekundaer">{{ __('Senden') }}</button>
                                        </form>
                                    </section>
                                </div>

                                {{-- Inspektor: Angaben und Aktionen --}}
                                <aside aria-label="{{ __('Angaben') }}" class="flex flex-col gap-5 border-t border-border px-5 py-5 @4xl:border-l @4xl:border-t-0">
                                    <div class="flex flex-col gap-2">
                                        @if($istNeu)
                                            <form method="POST" action="{{ route("{$bereich}.learners.grades.seen", [$lernender->lernender_id, $n->note_id]) }}"
                                                  x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                                                @csrf
                                                <button type="submit" :disabled="loading" class="np-knopf np-knopf-sekundaer w-full">{{ __('Als gesehen markieren') }}</button>
                                            </form>
                                        @endif
                                        @if($darfKorrigieren)
                                            <a href="{{ route("{$bereich}.learners.grades.edit", [$lernender->lernender_id, $n->note_id]) }}"
                                               class="np-knopf np-knopf-sekundaer w-full">{{ __('Korrigieren') }}</a>
                                        @endif
                                        @if($darfLoeschen)
                                            <form method="POST" action="{{ route("{$bereich}.learners.grades.destroy", [$lernender->lernender_id, $n->note_id]) }}"
                                                  x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true"
                                                  data-bestaetigen="{{ __('Note löschen?') }}" data-bestaetigen-knopf="{{ __('Löschen') }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" :disabled="loading" class="np-knopf np-knopf-gefahr w-full">{{ __('Note löschen') }}</button>
                                            </form>
                                        @endif
                                    </div>

                                    <dl class="grid grid-cols-[max-content_minmax(0,1fr)] gap-x-4 gap-y-1.5 text-sm">
                                        <dt class="text-muted">{{ __('Erfasst von') }}</dt>
                                        <dd class="text-text">{{ $n->erfasstVonBenutzer?->vorname }} {{ $n->erfasstVonBenutzer?->nachname }}</dd>
                                        @if($n->erstellt_am)
                                            <dt class="text-muted">{{ __('Erfasst am') }}</dt>
                                            <dd class="tabular-nums text-text">{{ __(':datum Uhr', ['datum' => $n->erstellt_am->format('d.m.Y H:i')]) }}</dd>
                                        @endif
                                        @if($gesehen && ! $istNeu)
                                            <dt class="text-muted">{{ __('Gesehen') }}</dt>
                                            <dd class="tabular-nums text-text">{{ __(':datum Uhr', ['datum' => $gesehen->gesehen_am->format('d.m.Y H:i')]) }}</dd>
                                        @endif
                                    </dl>
                                    <x-note-geaendert :note="$n" :lernender-benutzer-id="$lernender->benutzer_id" class="-mt-3" />
                                </aside>
                            </article>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
