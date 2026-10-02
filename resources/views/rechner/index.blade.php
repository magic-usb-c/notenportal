<x-app-layout>
    <x-slot name="title">{{ __('Rechner') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :zurueck="$zurueck" titel="{{ __('Rechner') }}" :untertitel="$lernender ? $lernender->benutzer->vorname.' '.$lernender->benutzer->nachname : null">
        </x-seitenkopf>
    </x-slot>

    @php
        $feld = 'np-feld';
        $label = 'text-sm font-medium text-text';
        $ebenen = ['gesamt' => __('Gesamt'), 'kategorie' => __('Kategorie'), 'semester' => __('Semester'), 'fach' => __('Fach'), 'modul' => __('Modul')];
        // Ein Satz pro Ebene, was sie beantwortet (übersichtlicher: David/PO-Rückmeldung #10).
        // Lernende lesen «du», Berufsbildner und Admins den Vornamen der Person, deren Noten sie rechnen.
        $person = $lernender?->benutzer->vorname;
        // Verwaltung: Bereich (admin|trainer) aus dem Routennamen, für die Cockpit-Navigation
        $cockpitBereich = \Illuminate\Support\Str::before((string) \Illuminate\Support\Facades\Route::currentRouteName(), '.');
        // Satzbausteine für das grosse, einsätzige Ergebnis (:platzhalter werden im JS ersetzt, formatiere() in rechner.js).
        $texte = [
            'ebene' => [
                'gesamt' => __('im Gesamtschnitt'),
                'kategorie' => __('in der Kategorie :name'),
                'semester' => __('im :name'),
                'fach' => __('im Fach :name'),
                'modul' => __('im Modul :name'),
            ],
            'ohneEinfluss' => __('Die offenen Prüfungen wirken sich :bezug nicht aus; der Wert bleibt bei :resultat.'),
            'keineNoten' => __('Noch keine Noten :bezug.'),
        ] + ($person === null ? [
            'benoetigtEine' => __('Du brauchst mindestens :note in der nächsten Prüfung, um :bezug auf :ziel zu kommen.'),
            'benoetigtMehrere' => __('Du brauchst mindestens :note in jeder der :n offenen Prüfungen, um :bezug auf :ziel zu kommen.'),
            'erreicht' => __('Dein Ziel ist bereits erreicht: Selbst mit der tiefsten Note (1.0) in den restlichen Prüfungen bleibst du :bezug bei mindestens :minimum, dein Ziel war :ziel.'),
            'unerreichbar' => __('Ziel nicht erreichbar: Selbst mit der Bestnote (6.0) in allen offenen Prüfungen kommst du :bezug höchstens auf :maximum, dein Ziel war :ziel.'),
            'zielErreicht' => __('Ziel erreicht: Du stehst :bezug bei :resultat.'),
            'zielOffen' => __('Ziel noch nicht erreicht: Du stehst :bezug bei :resultat, es fehlen :differenz.'),
        ] : [
            'benoetigtEine' => __(':person braucht mindestens :note in der nächsten Prüfung, um :bezug auf :ziel zu kommen.', ['person' => $person]),
            'benoetigtMehrere' => __(':person braucht mindestens :note in jeder der :n offenen Prüfungen, um :bezug auf :ziel zu kommen.', ['person' => $person]),
            'erreicht' => __('Das Ziel ist bereits erreicht: Selbst mit der tiefsten Note (1.0) in den restlichen Prüfungen bleibt :person :bezug bei mindestens :minimum, das Ziel war :ziel.', ['person' => $person]),
            'unerreichbar' => __('Ziel nicht erreichbar: Selbst mit der Bestnote (6.0) in allen offenen Prüfungen kommt :person :bezug höchstens auf :maximum, das Ziel war :ziel.', ['person' => $person]),
            'zielErreicht' => __('Ziel erreicht: :person steht :bezug bei :resultat.', ['person' => $person]),
            'zielOffen' => __('Ziel noch nicht erreicht: :person steht :bezug bei :resultat, es fehlen :differenz.', ['person' => $person]),
        ]);
    @endphp

    {{-- Links das Ziel und die offenen Prüfungen, rechts das Ergebnis: Heldenzahl, Kurve, Auswirkung, Promotion --}}
    <div class="py-6"
         x-data="npRechner(@js(['daten' => $daten, 'berechnenUrl' => $berechnenUrl, 'zielUrl' => $zielUrl, 'start' => $start, 'texte' => $texte]))">
        <div class="np-seite mx-auto flex flex-col gap-5 px-8">
            @if($lernender)
                @include('verwaltung.lernende._tabs', ['lernender' => $lernender, 'bereich' => $cockpitBereich, 'aktiv' => 'calculator', 'klasse' => '-mb-1'])
            @endif

            {{-- Gespeicherte Ziele --}}
            <div class="flex flex-wrap items-center gap-2" x-show="ziele.length" x-cloak>
                <span class="mr-1 text-xs text-muted">{{ __('Ziele') }}</span>
                <template x-for="z in ziele" :key="z.id">
                    <div class="inline-flex h-8 items-center rounded-full text-sm transition-colors duration-100"
                         :class="zielText === z.ziel ? 'bg-accent/12 text-accent-text' : 'bg-fill text-text hover:bg-fill-2'">
                        <button type="button" class="inline-flex h-full items-center gap-1.5 rounded-full pl-3 pr-2" @click="setzeZiel(z.ziel, z.zielwert)"
                                :aria-pressed="zielText === z.ziel">
                            <span x-text="z.label"></span>
                            <span class="font-semibold tabular-nums" x-text="'≥ ' + fmt(z.zielwert)"></span>
                        </button>
                        @if($zielUrl)
                            <form method="POST" :action="@js(route('learner.goals.destroy', 0)).replace(/0$/, z.id)" class="pr-1" x-data="{ loading: false }" @submit="loading = true">
                                @csrf
                                @method('DELETE')
                                <button :disabled="loading" class="inline-flex size-6 items-center justify-center rounded-full text-muted hover:bg-text/10 hover:text-text disabled:opacity-50"
                                        :aria-label="@js(__('Ziel entfernen')) + ': ' + z.label" title="{{ __('Ziel entfernen') }}"><x-symbol name="x-mark" strich="2" class="size-3.5" /></button>
                            </form>
                        @endif
                    </div>
                </template>
            </div>

            <div class="grid grid-cols-12 items-start gap-5">

                {{-- Eingaben --}}
                <div class="col-span-6 flex flex-col gap-5">
                    <section class="np-karte flex flex-col gap-5 p-5" aria-label="{{ __('Ziel') }}">
                        <div role="radiogroup" x-radiogroup aria-label="{{ __('Ebene') }}" class="np-segment flex w-full">
                            @foreach($ebenen as $wert => $name)
                                <button type="button" role="radio" class="flex-1" :aria-checked="ebene === '{{ $wert }}'" @click="waehleEbene('{{ $wert }}')">{{ $name }}</button>
                            @endforeach
                        </div>

                        <div class="grid grid-cols-2 gap-4" x-show="ebene !== 'gesamt'" x-cloak>
                            <div :class="['kategorie', 'fach'].includes(ebene) ? '' : 'col-span-2'">
                                <label for="ziel-id" class="{{ $label }}" x-text="{ kategorie: @js(__('Kategorie')), semester: @js(__('Semester')), fach: @js(__('Fach')), modul: @js(__('Modul')) }[ebene]"></label>
                                <select id="ziel-id" x-model="zielId" class="mt-1.5 {{ $feld }}">
                                    <template x-if="ebene === 'kategorie'"><template x-for="k in katalog.kategorien" :key="k.id"><option :value="String(k.id)" x-text="k.name" :selected="String(k.id) === zielId"></option></template></template>
                                    <template x-if="ebene === 'semester'"><template x-for="s in katalog.semester" :key="s.id"><option :value="String(s.id)" x-text="s.name" :selected="String(s.id) === zielId"></option></template></template>
                                    <template x-if="ebene === 'fach'"><template x-for="f in katalog.faecher" :key="f.id"><option :value="String(f.id)" x-text="f.name" :selected="String(f.id) === zielId"></option></template></template>
                                    <template x-if="ebene === 'modul'"><template x-for="m in katalog.module" :key="m.id"><option :value="String(m.id)" x-text="m.name" :selected="String(m.id) === zielId"></option></template></template>
                                </select>
                            </div>
                            <div x-show="['kategorie', 'fach'].includes(ebene)">
                                <label for="ziel-zeitraum" class="{{ $label }}">{{ __('Zeitraum') }}</label>
                                <select id="ziel-zeitraum" x-model="zeitraum" class="mt-1.5 {{ $feld }}">
                                    <option value="">{{ __('Ganze Lehrzeit') }}</option>
                                    <template x-for="s in katalog.semester" :key="s.id"><option :value="String(s.id)" x-text="s.name" :selected="String(s.id) === zeitraum"></option></template>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label for="zielwert" class="{{ $label }}">{{ __('Mindestens') }}</label>
                            <div class="mt-1.5 flex items-center gap-3">
                                <div class="flex items-center gap-1">
                                    <button type="button" class="np-knopf np-knopf-sekundaer np-knopf-symbol" aria-label="{{ __('Zielwert senken') }}"
                                            @click="zielwert = String(Math.max(1, Math.round((parseFloat(zielwert) - 0.1) * 10) / 10))"><x-symbol name="minus" strich="2" /></button>
                                    <input id="zielwert" type="number" min="1" max="6" step="0.05" x-model="zielwert"
                                           class="np-feld h-10 w-20 px-2 text-center text-xl font-semibold tabular-nums"
                                           :class="klasse(zielwert)">
                                    <button type="button" class="np-knopf np-knopf-sekundaer np-knopf-symbol" aria-label="{{ __('Zielwert erhöhen') }}"
                                            @click="zielwert = String(Math.min(6, Math.round((parseFloat(zielwert) + 0.1) * 10) / 10))"><x-symbol name="plus" strich="2" /></button>
                                </div>
                                <div class="np-segment np-segment-klein" role="group" aria-label="{{ __('Häufige Ziele') }}">
                                    @foreach(['4.0', '4.5', '5.0', '5.5'] as $v)
                                        <button type="button" class="tabular-nums" @click="zielwert = '{{ $v }}'" :aria-pressed="parseFloat(zielwert) === {{ $v }}">{{ $v }}</button>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <div class="flex min-h-9 items-center justify-between gap-3 border-t border-border pt-4">
                            <div class="flex items-baseline gap-2 text-sm">
                                <span class="text-muted">{{ __('Aktuell') }}</span>
                                <span class="font-semibold tabular-nums" :class="klasse(ergebnis?.loesung.aktuell)" x-text="fmt(ergebnis?.loesung.aktuell)"></span>
                            </div>
                            @if($zielUrl)
                                <form method="POST" action="{{ $zielUrl }}" x-show="speicherbar" x-cloak x-data="{ loading: false }" @submit="loading = true">
                                    @csrf
                                    <input type="hidden" name="ziel" :value="zielText">
                                    <input type="hidden" name="zielwert" :value="zielwert">
                                    <button class="np-knopf np-knopf-sekundaer"
                                            x-text="gespeichertesZiel ? (parseFloat(gespeichertesZiel.zielwert) === parseFloat(zielwert) ? @js(__('Ziel gespeichert')) : @js(__('Ziel aktualisieren'))) : @js(__('Als Ziel speichern'))"
                                            :disabled="loading || (gespeichertesZiel && parseFloat(gespeichertesZiel.zielwert) === parseFloat(zielwert))"></button>
                                </form>
                            @endif
                        </div>
                    </section>

                    <section class="np-karte" aria-labelledby="offene-titel">
                        <div class="flex h-14 items-center justify-between gap-3 px-5">
                            <h2 id="offene-titel" class="flex items-baseline gap-2 text-sm font-semibold text-text">{{ __('Offene Prüfungen') }} <span class="font-normal tabular-nums text-muted" x-text="offene"></span></h2>
                            <div class="flex gap-1.5">
                                <button type="button" x-show="katalog.faecher.length" @click="neueZeile('fach')" class="np-knopf np-knopf-sekundaer np-knopf-klein"><x-symbol name="plus" strich="2" class="size-3.5" />{{ __('Fach') }}</button>
                                <button type="button" x-show="katalog.module.length" @click="neueZeile('modul')" class="np-knopf np-knopf-sekundaer np-knopf-klein"><x-symbol name="plus" strich="2" class="size-3.5" />{{ __('Modul') }}</button>
                            </div>
                        </div>

                        {{-- Eine Zeile je Prüfung, Spalten wie eine Tabelle: Fach/Modul · Semester/Herkunft · Gewicht · Note --}}
                        <div x-show="zeilen.length" class="px-2 pb-2">
                            <div class="grid h-8 grid-cols-[minmax(0,1fr)_11rem_5rem_4.5rem_2rem] items-center gap-2 border-b border-border px-3 text-xs font-medium text-muted" aria-hidden="true">
                                <span>{{ __('Fach / Modul') }}</span>
                                <span>{{ __('Semester') }}</span>
                                <span class="text-right">{{ __('Gewicht') }}</span>
                                <span class="text-center">{{ __('Note') }}</span>
                                <span></span>
                            </div>
                            <template x-for="z in zeilen" :key="z.nr">
                                <div class="grid grid-cols-[minmax(0,1fr)_11rem_5rem_4.5rem_2rem] items-center gap-2 rounded-lg px-3 py-1.5 even:bg-text/3">
                                    <select x-model="z.id" class="np-feld np-feld-klein min-w-0" :aria-label="z.typ === 'fach' ? @js(__('Fach')) : @js(__('Modul'))">
                                        <option value="">–</option>
                                        <template x-for="o in (z.typ === 'fach' ? katalog.faecher.filter(f => f.erfassbar || String(f.id) === z.id) : katalog.module)" :key="o.id">
                                            <option :value="String(o.id)" x-text="o.name" :selected="String(o.id) === z.id"></option>
                                        </template>
                                    </select>
                                    <div class="min-w-0">
                                        <select x-show="z.typ === 'fach'" x-model="z.semester" class="np-feld np-feld-klein" aria-label="{{ __('Semester') }}">
                                            <template x-for="s in katalog.semester" :key="s.id"><option :value="String(s.id)" x-text="s.name" :selected="String(s.id) === z.semester"></option></template>
                                        </select>
                                        <div class="flex min-w-0 items-center gap-1.5 text-xs text-muted" x-show="z.typ !== 'fach'">
                                            <span x-show="z.quelle === 'rest'" class="np-marke shrink-0">{{ __('Rest') }}</span>
                                            <span x-show="z.quelle === 'geplant'" class="np-marke shrink-0">{{ __('geplant') }}</span>
                                            <span class="truncate" x-text="z.titel ?? ''" :title="z.titel ?? ''"></span>
                                        </div>
                                    </div>
                                    <span class="relative">
                                        <input type="number" min="0" max="100" step="1" x-model="z.gewicht" class="np-feld np-feld-klein pl-2 pr-6 text-right tabular-nums" aria-label="{{ __('Gewichtung in Prozent') }}">
                                        <span class="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 text-xs text-muted" aria-hidden="true">%</span>
                                    </span>
                                    <input type="number" min="1" max="6" step="0.05" x-model="z.wert" placeholder="?"
                                           class="np-feld np-feld-klein px-1 text-center font-semibold tabular-nums placeholder:font-semibold placeholder:text-muted"
                                           :class="klasse(z.wert)" aria-label="{{ __('Note (leer = gesucht)') }}">
                                    <button type="button" @click="entferne(z.nr)" class="np-knopf np-knopf-symbol np-knopf-symbol-gefahr" aria-label="{{ __('Prüfung entfernen') }}" title="{{ __('Entfernen') }}"><x-symbol name="x-mark" strich="2" class="size-4" /></button>
                                </div>
                            </template>
                        </div>

                        <p x-show="!zeilen.length" x-cloak class="px-5 pb-6 pt-2 text-sm text-muted">{{ __('Keine offenen Prüfungen') }}</p>
                    </section>
                </div>

                {{-- Ergebnis --}}
                <div class="col-span-6 flex flex-col gap-5">
                    <section class="np-karte flex min-h-56 flex-col items-center justify-center px-10 py-8 text-center transition-opacity duration-150" :class="laedt ? 'opacity-70' : ''" :aria-busy="laedt" aria-live="polite">
                        {{-- Fehler mit Weiterweg (HIG «Alerts»: Ursache und Handlung) --}}
                        <div x-show="fehler" x-cloak class="flex flex-col items-center gap-3">
                            <p class="text-sm text-note-ungenuegend" x-text="fehler"></p>
                            <button type="button" class="np-knopf np-knopf-sekundaer" @click="berechnen()">{{ __('Erneut berechnen') }}</button>
                        </div>

                        <template x-if="ergebnis && !fehler">
                            <div>
                                <div class="text-sm font-medium text-muted"
                                     x-text="{ benoetigt: @js(__('Benötigt')), erreicht: @js(__('Schon erreicht')), unerreichbar: @js(__('Nicht erreichbar')), ohne_einfluss: @js(__('Kein Einfluss')), keine_unbekannten: @js(__('Ergebnis')) }[ergebnis.loesung.status]"></div>

                                <div class="mt-1 text-display font-bold" :class="heroKlasse">
                                    <span x-show="ergebnis.loesung.status === 'benoetigt'" x-text="fmt(ergebnis.loesung.note, 2)"></span>
                                    <span x-show="ergebnis.loesung.status === 'erreicht'">✓</span>
                                    <span x-show="['unerreichbar', 'ohne_einfluss', 'keine_unbekannten'].includes(ergebnis.loesung.status) && (ergebnis.loesung.resultat ?? ergebnis.loesung.aktuell) != null" x-text="fmt(ergebnis.loesung.resultat ?? ergebnis.loesung.aktuell)"></span>
                                </div>

                                <p class="mx-auto mt-3 max-w-lg text-base text-text" x-text="heroSatz"></p>
                                @if(! $lernender)
                                    {{-- Ohne eine einzige Note gibt es nichts zu rechnen: Weiterweg statt Strich --}}
                                    <a x-show="ergebnis.loesung.resultat == null && ergebnis.loesung.aktuell == null" x-cloak
                                       href="{{ route('learner.grades.create') }}" @click.prevent="$dispatch('np-note', { url: $el.href, titel: @js(__('Neue Note')) })"
                                       class="np-knopf np-knopf-sekundaer mt-4">{{ __('Note erfassen') }}</a>
                                @endif
                            </div>
                        </template>
                    </section>

                    <x-karte :titel="__('Ergebnis je Note in den offenen Prüfungen')" x-show="kurve" x-cloak>
                        <div class="h-64" x-data="npChart('kurve')" x-effect="zeichne(kurve)">
                            <canvas x-ref="canvas" role="img" aria-label="{{ __('Ergebnis in Abhängigkeit der Note') }}"></canvas>
                        </div>
                    </x-karte>

                    <x-karte :titel="__('Auswirkung')" :polster="false" x-show="ergebnis?.vergleich?.some((v) => v.vorher != null || v.nachher != null)" x-cloak>
                        <x-slot:aktionen>
                            <span class="text-sm tabular-nums text-muted" x-show="ergebnis?.loesung.status === 'benoetigt'" x-text="@js(__('mit ')) + fmt(ergebnis?.loesung.note, 2)"></span>
                        </x-slot:aktionen>
                        <div class="px-2 pb-2">
                            <table class="np-tabelle table-fixed text-sm">
                                <colgroup>
                                    <col>
                                    <col class="w-24">
                                    <col class="w-24">
                                    <col class="w-24">
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th scope="col">{{ __('Bereich') }}</th>
                                        <th scope="col" class="text-right">{{ __('Heute') }}</th>
                                        <th scope="col" class="text-right">{{ __('Danach') }}</th>
                                        <th scope="col" class="text-right">{{ __('Differenz') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="v in ergebnis?.vergleich ?? []" :key="v.text">
                                        <tr>
                                            <td class="truncate" :class="v.ist_ziel ? 'font-semibold text-text' : 'text-text'" x-text="v.label"></td>
                                            <td class="text-right text-muted" x-text="fmt(v.vorher)"></td>
                                            <td class="text-right font-semibold" :class="klasse(v.nachher)" x-text="fmt(v.nachher)"></td>
                                            <td class="text-right tabular-nums text-muted"
                                                x-text="delta(v.vorher, v.nachher) === null || delta(v.vorher, v.nachher) === 0 ? '–' : (delta(v.vorher, v.nachher) > 0 ? '▲ ' : '▼ ') + fmt(Math.abs(delta(v.vorher, v.nachher)), 2)"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </x-karte>

                    <x-karte :titel="__('Promotion')" :polster="false" x-show="ergebnis?.promotion?.length" x-cloak>
                        <div class="px-2 pb-2">
                            <table class="np-tabelle table-fixed text-sm">
                                <colgroup>
                                    <col>
                                    <col class="w-24">
                                    <col class="w-28">
                                    <col class="w-28">
                                    <col class="w-56">
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th scope="col">{{ __('Kategorie') }}</th>
                                        <th scope="col" class="text-right">{{ __('Schnitt') }}</th>
                                        <th scope="col" class="text-right">{{ __('Ungenügend') }}</th>
                                        <th scope="col" class="text-right">{{ __('Minuspunkte') }}</th>
                                        <th scope="col" class="text-right" title="{{ __('Heute → Szenario') }}">{{ __('Promotion') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="p in ergebnis?.promotion ?? []" :key="p.kategorie + p.semester">
                                        <tr>
                                            <td class="truncate"><span class="font-medium text-text" x-text="p.kategorie"></span> <span class="text-muted" x-text="p.semester"></span></td>
                                            <td class="text-right font-semibold" :class="klasse(p.nachher.schnitt)" x-text="fmt(p.nachher.schnitt)"></td>
                                            <td class="text-right text-text" x-text="p.nachher.ungenuegend"></td>
                                            <td class="text-right text-text" x-text="fmt(p.nachher.minuspunkte, 1)"></td>
                                            <td class="text-right">
                                                <div class="inline-flex items-center justify-end gap-1.5">
                                                    {{-- Stand heute nur, wenn das Szenario ihn ändert --}}
                                                    <template x-if="p.vorher && p.vorher.erfuellt !== p.nachher.erfuellt">
                                                        <div class="inline-flex items-center gap-1.5">
                                                            <span class="np-marke bg-fill text-muted" x-text="p.vorher.erfuellt ? @js(__('erfüllt')) : @js(__('gefährdet'))"></span>
                                                            <span class="text-muted" aria-hidden="true">→</span>
                                                            <span class="sr-only">{{ __('wird zu') }}</span>
                                                        </div>
                                                    </template>
                                                    <span class="np-marke gap-1"
                                                          :class="p.nachher.erfuellt ? 'bg-note-gut/14 text-note-gut' : 'bg-note-ungenuegend/14 text-note-ungenuegend'">
                                                        <span x-show="! p.nachher.erfuellt" aria-hidden="true">▼</span>
                                                        <span x-text="p.nachher.erfuellt ? @js(__('erfüllt')) : @js(__('gefährdet'))"></span>
                                                    </span>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </x-karte>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
