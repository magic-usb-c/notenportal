<x-app-layout>
    <x-slot name="title">{{ __('Noten') }}</x-slot>
    @php
        $a = $auswertung;
        $semLabel = $selectedSemesterId ? $a->konfiguration->semesterName((int) $selectedSemesterId, $a->lernenderId) : __('Semester');
        $ausgewaehltesSemester = $semester->firstWhere('semester_id', (int) $selectedSemesterId);
        $semesterRest = null;
        if ($ausgewaehltesSemester) {
            $heute = \Illuminate\Support\Carbon::today()->toDateString();
            if ((string) $ausgewaehltesSemester->start_datum <= $heute && (string) $ausgewaehltesSemester->end_datum >= $heute) {
                $semesterRest = \App\Support\Format::restdauer(\Illuminate\Support\Carbon::parse($ausgewaehltesSemester->end_datum));
            }
        }
        $mit = fn (array $extra) => array_merge(request()->except(['page', '_open']), $extra);
        $semSchnitt = $selectedSemesterId ? $a->semester((int) $selectedSemesterId)['note'] : null;
        $ich = (int) auth()->user()->benutzer_id;
        $offeneNote = (int) (session('opened_note') ?: request()->input('_open', 0));

        // Statuszeile: Semester und Gesamt immer (leer = «–»), Kategorien nur mit Note im Semester
        $status = [[__('Semester'), $semSchnitt], [__('Gesamt'), $a->gesamtNote]];
        foreach ($a->kategorien as $kid => $kat) {
            $kNote = $selectedSemesterId ? $a->semester((int) $selectedSemesterId, $kid)['note'] : null;
            if ($kNote !== null) {
                $status[] = [$a->konfiguration->kategorieName($kid), $kNote];
            }
        }
        $pfeil = 'np-knopf np-knopf-symbol';
    @endphp

    <x-slot name="header">
        <x-seitenkopf titel="{{ __('Noten') }}">
            <div class="flex flex-col items-center gap-0.5">
                <div class="inline-flex h-9 items-center gap-0.5 rounded-full bg-fill p-0.5" role="group" aria-label="{{ __('Semester') }}">
                    <a @class([$pfeil, 'pointer-events-none opacity-30' => ! $prevSemesterId])
                       href="{{ $prevSemesterId ? route('learner.grades.index', $mit(['semester_id' => $prevSemesterId])) : '#' }}" aria-label="{{ __('Vorheriges Semester') }}"><x-symbol name="chevron-left" strich="2" /></a>
                    <x-semester :id="$selectedSemesterId ?: null" :lernender="$a->lernenderId" class="whitespace-nowrap px-2 text-sm font-medium tabular-nums text-text" />
                    <a @class([$pfeil, 'pointer-events-none opacity-30' => ! $nextSemesterId])
                       href="{{ $nextSemesterId ? route('learner.grades.index', $mit(['semester_id' => $nextSemesterId])) : '#' }}" aria-label="{{ __('Nächstes Semester') }}"><x-symbol name="chevron-right" strich="2" /></a>
                </div>
                @if($semesterRest)
                    <span class="text-2xs text-muted">{{ $semesterRest }}</span>
                @endif
            </div>
            <x-slot:aktionen>
                <a href="{{ route('learner.grades.index') }}?rechner=1" x-data @click.prevent="$dispatch('open-drawer', 'rechner')"
                   class="np-knopf np-knopf-sekundaer">{{ __('Notenrechner') }}</a>
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button type="button" aria-label="{{ __('Weitere Aktionen') }}" class="np-knopf np-knopf-sekundaer np-knopf-rund">
                            <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M3 10a1.5 1.5 0 113 0 1.5 1.5 0 01-3 0zM8.5 10a1.5 1.5 0 113 0 1.5 1.5 0 01-3 0zM15.5 8.5a1.5 1.5 0 100 3 1.5 1.5 0 000-3z"/></svg>
                        </button>
                    </x-slot>
                    <x-slot name="content">
                        <a href="{{ route('learner.grades.import.index') }}" class="np-menue-eintrag">{{ __('Importieren…') }}</a>
                        <a href="{{ route('learner.grades.print') }}" target="_blank" rel="noopener noreferrer" class="np-menue-eintrag">{{ __('Drucken') }}</a>
                        <a href="{{ route('learner.grades.export') }}" class="np-menue-eintrag">{{ __('CSV exportieren') }}</a>
                    </x-slot>
                </x-dropdown>
                <a href="{{ route('learner.grades.create') }}" x-data @click.prevent="$dispatch('np-note', { url: $el.href, titel: @js(__('Neue Note')) })"
                   class="np-knopf np-knopf-primaer">
                    <x-symbol name="plus" strich="2" />{{ __('Note') }}
                </a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6" x-data="{ ansicht: @js(request('ansicht') === 'alle' ? 'alle' : 'semester') }">
        <div class="mx-auto flex np-seite flex-col gap-6 px-8">

            {{-- Statuszeile --}}
            <dl class="flex flex-wrap items-baseline gap-x-3 gap-y-2">
                @foreach($status as [$label, $wert])
                    @unless($loop->first)<span class="text-muted" aria-hidden="true">·</span>@endunless
                    <div class="flex items-baseline gap-2">
                        <dt class="text-xs text-muted">{{ $label }}</dt>
                        <dd><x-note :wert="$wert" :stellen="1" class="text-2xl" /></dd>
                    </div>
                @endforeach
            </dl>

            {{-- Werkzeugzeile: Ansicht links, Kategorien rechts --}}
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="np-segment" role="radiogroup" x-radiogroup aria-label="{{ __('Ansicht') }}">
                    <button type="button" role="radio" :aria-checked="ansicht === 'semester'" @click="ansicht = 'semester'">{{ __('Semester') }}</button>
                    <button type="button" role="radio" :aria-checked="ansicht === 'alle'" @click="ansicht = 'alle'">{{ __('Zeugnisübersicht') }}</button>
                </div>
                <nav class="np-segment max-w-full overflow-x-auto" x-show="ansicht === 'semester'" aria-label="{{ __('Kategorie') }}">
                    <a href="{{ route('learner.grades.index', $mit(['kategorie_id' => null])) }}" @if(! $kategorieId) aria-current="page" @endif>{{ __('Alle') }}</a>
                    @foreach($kategorien as $k)
                        <a href="{{ route('learner.grades.index', $mit(['kategorie_id' => $k->kategorie_id])) }}" @if($kategorieId === $k->kategorie_id) aria-current="page" @endif>{{ $k->name }}</a>
                    @endforeach
                </nav>
            </div>

            {{-- Zeugnisübersicht --}}
            <x-karte titel="{{ __('Zeugnisnoten über die Lehrzeit') }}" :polster="false" x-show="ansicht === 'alle'" x-cloak>
                @if($heatmap['gruppen'])
                    <div class="px-5 pb-2 pt-1"><x-noten-legende art="zellen" /></div>
                @endif
                <x-heatmap :daten="$heatmap" />
            </x-karte>

            {{-- Semester: Tabelle je Kategorie. Ab 92rem Inhaltsbreite zwei Spalten: die Kategorien bleiben in ihrer
                 Reihenfolge und werden dort geteilt, wo beide Spalten etwa gleich hoch werden. Eine Spalte allein bleibt
                 halb breit, damit Name und Note nah beieinander stehen und ein Filter die Breite nicht ändert. --}}
            @php
                $gruppenListe = collect($gruppen)->values();
                $hoehe = fn ($g) => 2 + count($g->elemente);
                $summe = $gruppenListe->sum($hoehe);
                $teilung = $gruppenListe->count();
                $bester = PHP_INT_MAX;
                $lauf = 0;
                foreach ($gruppenListe as $i => $g) {
                    $lauf += $hoehe($g);
                    if ($i + 1 < $gruppenListe->count() && max($lauf, $summe - $lauf) < $bester) {
                        $bester = max($lauf, $summe - $lauf);
                        $teilung = $i + 1;
                    }
                }
                $spalten = [$gruppenListe->take($teilung), $gruppenListe->slice($teilung)];
            @endphp
            <div class="@container" x-show="ansicht === 'semester'">
                @if($gruppenListe->isNotEmpty())
                <div class="grid grid-cols-1 items-start gap-6 @min-[92rem]:grid-cols-2">
                @foreach($spalten as $spalte)
                <div class="flex min-w-0 flex-col gap-6">
                @foreach($spalte as $g)
                    <section class="flex flex-col gap-2" aria-labelledby="kategorie-{{ $g->id }}">
                        <div class="flex h-7 items-center justify-between gap-3 px-1">
                            <h2 id="kategorie-{{ $g->id }}" class="text-sm font-semibold text-text">{{ $g->name }}</h2>
                            <div class="flex items-center gap-3 text-sm">
                                @if($g->promotion)
                                    <x-status :status="$g->promotion['erfuellt'] ? 'gruen' : 'rot'" :text="$g->promotion['erfuellt'] ? __('Promotion ok') : __('Promotion gefährdet')" />
                                @endif
                                <span class="flex items-baseline gap-1.5"><span class="text-xs text-muted">{{ __('Schnitt') }}</span><x-note :wert="$g->semester" :stellen="1" /></span>
                            </div>
                        </div>

                        {{-- Gliederung wie eine macOS-Outline-Ansicht: feste Spalten in allen Kategorien, Pfeil vor dem Namen,
                             die Prüfungen darunter im selben Raster (Gewicht unter «Prüfungen», Note unter «Schnitt») --}}
                        <div class="np-karte overflow-hidden">
                            <table class="w-full table-fixed text-sm tabular-nums">
                                <colgroup>
                                    <col>
                                    <col class="w-56">
                                    <col class="w-24">
                                    <col class="w-28">
                                </colgroup>
                                <thead>
                                    <tr class="border-b border-border">
                                        <th scope="col" class="h-8 pl-10 pr-3 text-left text-xs font-medium text-muted">{{ __('Fach / Modul') }}</th>
                                        <th scope="col" class="h-8 px-3 text-left text-xs font-medium text-muted">{{ __('Prüfungen') }}</th>
                                        <th scope="col" class="h-8 px-3 text-right text-xs font-medium text-muted">{{ __('Schnitt') }}</th>
                                        <th scope="col" class="h-8 px-4 text-right text-xs font-medium text-muted">{{ __('Zeugnis') }}</th>
                                    </tr>
                                </thead>
                                @foreach($g->elemente as $el)
                                    @php
                                        $e = $el->element;
                                        $beleg = $e?->typ === \App\Services\Auswertung\Element::MODUL ? ($belegungen[$e->modulId] ?? null) : null;
                                        $offenGewicht = $e?->offenGewicht();
                                        $fortschritt = $e && $e->zielGewicht ? (int) min(100, round($e->gewichtSumme / $e->zielGewicht * 100)) : null;
                                        $zeileId = 'noten-'.$g->id.'-'.$loop->index;
                                        $anzahl = $el->noten->count();
                                    @endphp
                                    <tbody x-data="{ offen: @js($offeneNote > 0 && $el->noten->contains('note_id', $offeneNote)) }" class="border-b border-border last:border-0">
                                        <tr class="h-11 cursor-pointer transition-colors duration-100 hover:bg-surface-2/60" @click="offen = ! offen">
                                            <th scope="row" class="px-3 text-left font-normal">
                                                <button type="button" @click.stop="offen = ! offen" :aria-expanded="offen" aria-controls="{{ $zeileId }}"
                                                        class="flex w-full min-w-0 items-center gap-2 rounded-md text-left focus-visible:outline-2 focus-visible:outline-ring">
                                                    <x-symbol name="chevron-right" strich="2" class="size-3.5 shrink-0 text-muted transition-transform duration-200" ::class="offen && 'rotate-90'" />
                                                    <span class="ml-1.5 truncate font-medium text-text">{{ $el->label }}</span>
                                                    @if($beleg && $beleg['versuche'] > 1)
                                                        <span class="shrink-0 text-xs text-muted">{{ __(':n. Versuch', ['n' => $beleg['versuche']]) }}</span>
                                                    @endif
                                                </button>
                                            </th>
                                            <td class="px-3 text-muted">
                                                <span class="flex items-center gap-3" @if($fortschritt !== null) title="{{ $fortschritt >= 100 ? __('abgeschlossen') : __(':prozent offen', ['prozent' => \App\Support\Zahl::prozent($offenGewicht)]) }}" @endif>
                                                    <span class="sr-only">{{ $anzahl === 1 ? __('1 Prüfung') : __(':anzahl Prüfungen', ['anzahl' => $anzahl]) }}</span>
                                                    <span class="w-5 text-right" aria-hidden="true">{{ $anzahl }}</span>
                                                    @if($fortschritt !== null)
                                                        <span class="h-1 w-16 shrink-0 overflow-hidden rounded-full bg-fill" aria-hidden="true">
                                                            <span class="block h-full bg-chart-6" style="width: {{ $fortschritt }}%"></span>
                                                        </span>
                                                        <span class="truncate text-xs">{{ $fortschritt >= 100 ? __('abgeschlossen') : __(':prozent offen', ['prozent' => \App\Support\Zahl::prozent($offenGewicht)]) }}</span>
                                                    @endif
                                                </span>
                                            </td>
                                            <td class="px-3 text-right text-muted" title="{{ __('Schnitt vor Rundung') }}">{{ \App\Support\NotenSkala::format($e?->schnitt, 2) }}</td>
                                            <td class="px-4 text-right"><x-note :wert="$e?->note" variante="badge" /></td>
                                        </tr>
                                        <tr id="{{ $zeileId }}" x-show="offen" x-cloak>
                                            <td colspan="4" class="border-t border-border p-0">
                                                @php
                                                    $ms = $e ? ($modulstatus[$e->schluessel] ?? null) : null;
                                                    $mbk = $beleg['mbk'] ?? null;
                                                @endphp
                                                @if($mbk || ($ms && ($ms['dauer_seit_beginn'] || $ms['naechster_termin'] || $ms['bewerteter_anteil_prozent'] !== null)))
                                                    <div class="flex items-center gap-x-5 border-b border-border/70 bg-fill-2 py-2 pl-10 pr-4 text-xs text-muted">
                                                        @if($ms && $ms['dauer_seit_beginn'])
                                                            <span class="shrink-0">{{ $ms['dauer_seit_beginn'] }}</span>
                                                        @endif
                                                        @if($ms && $ms['naechster_termin'])
                                                            <span class="inline-flex min-w-0 items-center gap-1">
                                                                <span class="truncate text-text">{{ $ms['naechster_termin']['titel'] }}</span>
                                                                <span class="shrink-0">· {{ $ms['naechster_termin']['restdauer'] }}</span>
                                                            </span>
                                                        @endif
                                                        @if($ms && $ms['bewerteter_anteil_prozent'] !== null)
                                                            <span class="inline-flex shrink-0 items-center gap-1.5">
                                                                {{ __('Bewertet') }}
                                                                <span class="h-1 w-14 overflow-hidden rounded-full bg-fill" aria-hidden="true">
                                                                    <span class="block h-full bg-chart-6" style="width: {{ $ms['bewerteter_anteil_prozent'] }}%"></span>
                                                                </span>
                                                                {{ \App\Support\Zahl::prozent($ms['bewerteter_anteil_prozent']) }}
                                                            </span>
                                                        @endif
                                                        @if($mbk)
                                                            <a href="{{ $mbk }}" target="_blank" rel="noopener noreferrer"
                                                               class="ml-auto shrink-0 text-accent-text underline-offset-2 hover:underline">
                                                                {{ __('Modulbeschreibung') }}<span class="sr-only"> ({{ __('neues Fenster') }})</span>
                                                            </a>
                                                        @endif
                                                    </div>
                                                @endif
                                                <div class="divide-y divide-border">
                                                    @foreach($el->noten as $n)
                                                        @include('lernender.noten.partials.note', ['n' => $n, 'ich' => $ich])
                                                    @endforeach
                                                    @if($beleg)
                                                        <div class="flex justify-end px-4 py-2">
                                                            <form method="POST" action="{{ route($beleg['offen'] ? 'learner.grades.module.repeat' : 'learner.grades.module.resume', $e->modulId) }}"
                                                                  @if($beleg['offen']) data-bestaetigen="{{ __('Modul wiederholen?') }}" data-bestaetigen-text="{{ __('Ab der nächsten Note zählt nur der neue Versuch.') }}" data-bestaetigen-knopf="{{ __('Wiederholen') }}" data-bestaetigen-art="normal" @endif
                                                                  x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                                                                @csrf
                                                                <button :disabled="loading" class="np-knopf np-knopf-sekundaer np-knopf-klein">
                                                                    {{ $beleg['offen'] ? __('Modul wiederholen') : __('Wiederholung zurücknehmen') }}
                                                                </button>
                                                            </form>
                                                        </div>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                @endforeach
                            </table>
                        </div>
                    </section>
                @endforeach
                </div>
                @endforeach
                </div>
                @else
                    <p class="np-karte flex items-center gap-3 px-5 py-4 text-sm text-muted">
                        {{ __('Keine Noten in :semester', ['semester' => $semLabel]) }}
                        <a href="{{ route('learner.grades.create') }}" x-data @click.prevent="$dispatch('np-note', { url: $el.href, titel: @js(__('Neue Note')) })"
                           class="text-accent-text underline-offset-2 hover:underline">{{ __('Note erfassen') }}</a>
                    </p>
                @endif
            </div>
        </div>
    </div>

    {{-- Erfassen/Bearbeiten im Drawer; create/edit bleiben als Seiten für Direktlinks und ohne JS --}}
    <x-noten-drawer :fehler="$drawerFehler" />

    {{-- Notenrechner: bis zu 10 hypothetische Noten gleichzeitig, Auswirkung alt → neu; speichert nichts --}}
    <x-drawer name="rechner" :offen="request()->has('rechner')" titel="{{ __('Notenrechner') }}" breite="lg">
        <div x-data="npNotenrechnerDrawer(@js([
                'semesterListe' => $semesterListe,
                'berechnenUrl' => route('learner.grades.calculator.simulate'),
                'grenzen' => \App\Support\NotenSkala::grenzen(),
                'heute' => now()->toDateString(),
            ]))" class="flex flex-col gap-5">
            <p class="text-sm text-muted">{{ __('Nur eine Simulation – es wird nichts gespeichert.') }}</p>

            <div class="flex flex-col gap-3">
                <template x-for="z in zeilen" :key="z.nr">
                    <div class="flex flex-col gap-2 rounded-xl bg-fill-2 p-3">
                        <div class="flex items-center gap-2">
                            <select x-model="z.bezug" class="np-feld min-w-0 flex-1" aria-label="{{ __('Fach / Modul') }}">
                                <option value="">{{ __('Bitte wählen') }}</option>
                                @foreach($bezugOptionen as $gruppe => $optionen)
                                    <optgroup label="{{ $gruppe }}">
                                        @foreach($optionen as $o)
                                            <option value="{{ $o['wert'] }}">{{ $o['label'] }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                            <button type="button" @click="entferne(z.nr)" class="np-knopf np-knopf-symbol np-knopf-symbol-gefahr shrink-0" aria-label="{{ __('Note entfernen') }}"><x-symbol name="x-mark" strich="2" /></button>
                        </div>
                        <input type="date" x-model="z.datum" class="np-feld" aria-label="{{ __('Prüfungsdatum') }}">
                        <p class="text-xs" :class="semesterVon(z.datum) ? 'text-muted' : 'text-note-knapp'"
                           x-text="semesterVon(z.datum) ? semesterVon(z.datum).name : (z.datum ? @js(__('Kein Semester für dieses Datum')) : '')"></p>
                        <div class="flex items-end gap-2">
                            <label class="flex w-24 shrink-0 flex-col gap-1">
                                <span class="text-xs text-muted">{{ __('Gewichtung') }}</span>
                                <span class="relative">
                                    <input type="number" min="0" max="100" step="1" x-model="z.gewicht"
                                           class="np-feld pl-2 pr-6 text-right tabular-nums"
                                           aria-label="{{ __('Gewichtung in Prozent') }}">
                                    <span class="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 text-xs text-muted" aria-hidden="true">%</span>
                                </span>
                            </label>
                            <label class="flex flex-1 flex-col gap-1">
                                <span class="text-xs text-muted">{{ __('Note') }}</span>
                                <input type="number" min="1" max="6" step="0.05" x-model="z.wert" placeholder="4.5"
                                       class="np-feld border-2 text-center font-semibold tabular-nums"
                                       :class="klasse(z.wert)" aria-label="{{ __('Note') }}">
                            </label>
                        </div>
                    </div>
                </template>
            </div>

            <button type="button" @click="neueZeile()" x-show="zeilen.length < 10"
                    class="np-knopf np-knopf-sekundaer self-start">
                <x-symbol name="plus" strich="2" />{{ __('Note hinzufügen') }}
            </button>

            <p x-show="fehler" x-cloak class="text-sm text-note-ungenuegend" x-text="fehler"></p>

            <section x-show="vergleich.length" x-cloak class="np-karte overflow-hidden transition-opacity" :class="laedt ? 'opacity-70' : ''">
                <div class="border-b border-border/70 px-4 py-2.5">
                    <h3 class="text-sm font-semibold text-text">{{ __('Auswirkung') }}</h3>
                </div>
                <div class="divide-y divide-border/70">
                    <template x-for="v in vergleich" :key="v.text">
                        <div class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm">
                            <span class="truncate text-muted" x-text="v.label"></span>
                            <span class="flex shrink-0 items-center gap-2 tabular-nums">
                                <span class="text-muted" x-text="fmt(v.vorher)"></span>
                                <span class="text-muted" aria-hidden="true">→</span>
                                <span class="min-w-10 text-right font-semibold" :class="klasse(v.nachher)" x-text="fmt(v.nachher)"></span>
                                <span class="w-12 text-right text-xs"
                                      :class="delta(v.vorher, v.nachher) > 0 ? 'text-note-gut' : (delta(v.vorher, v.nachher) < 0 ? 'text-note-ungenuegend' : 'text-muted')"
                                      x-text="delta(v.vorher, v.nachher) === null || delta(v.vorher, v.nachher) === 0 ? '' : (delta(v.vorher, v.nachher) > 0 ? '+' : '') + fmt(delta(v.vorher, v.nachher), 2)"></span>
                            </span>
                        </div>
                    </template>
                </div>
            </section>

            <section x-show="promotion.length" x-cloak class="flex flex-col gap-2">
                <h3 class="text-sm font-semibold text-text">{{ __('Promotion') }}</h3>
                <template x-for="p in promotion" :key="p.kategorie + p.semester">
                    <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl bg-fill-2 px-3 py-2.5">
                        <div class="text-sm">
                            <span class="font-medium text-text" x-text="p.kategorie"></span>
                            <span class="text-muted" x-text="p.semester"></span>
                        </div>
                        <span class="inline-flex items-center gap-1.5 text-xs font-medium text-text">
                            <span class="size-2 rounded-full" :class="p.nachher.erfuellt ? 'bg-note-gut' : 'bg-note-ungenuegend'" aria-hidden="true"></span>
                            <span x-text="p.nachher.erfuellt ? @js(__('erfüllt')) : @js(__('gefährdet'))"></span>
                        </span>
                    </div>
                </template>
            </section>
        </div>
    </x-drawer>

    <script>
        function npTitelEdit(initial, url) {
            return {
                titel: initial,
                titelDraft: initial ?? '',
                editingTitel: false,
                savingTitel: false,
                titelError: false,
                startTitelEdit() {
                    this.titelDraft = this.titel ?? '';
                    this.titelError = false;
                    this.editingTitel = true;
                },
                async saveTitel() {
                    this.savingTitel = true;
                    this.titelError = false;
                    try {
                        const res = await fetch(url, {
                            method: 'PATCH',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                                'Content-Type': 'application/json',
                                Accept: 'application/json',
                            },
                            body: JSON.stringify({ titel: this.titelDraft }),
                        });
                        if (res.ok) {
                            this.titel = (await res.json()).titel;
                            this.editingTitel = false;
                        } else {
                            this.titelError = true;
                        }
                    } catch (e) {
                        this.titelError = true;
                    } finally {
                        this.savingTitel = false;
                    }
                },
            };
        }

        document.addEventListener('DOMContentLoaded', () => {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
            const offen = {{ $offeneNote ?: 'null' }};

            document.querySelectorAll('details.np-note-detail').forEach((d) => {
                d.addEventListener('toggle', () => {
                    if (d.open) {
                        fetch(`/grades/${d.dataset.noteId}/seen`, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' } }).catch(() => {});
                    }
                });
            });

            // Deep-Link (?_open=): die Zeile ist serverseitig aufgeklappt, hier nur die Prüfung öffnen
            const ziel = offen && document.querySelector(`details.np-note-detail[data-note-id="${offen}"]`);
            if (ziel) {
                ziel.open = true;
                setTimeout(() => ziel.scrollIntoView({ behavior: 'smooth', block: 'center' }), 60);
            }
        });
    </script>
</x-app-layout>
