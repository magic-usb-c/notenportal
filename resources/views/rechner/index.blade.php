<x-app-layout>
    <x-slot name="title">{{ __('Rechner') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf titel="{{ __('Rechner') }}" :untertitel="$lernender ? $lernender->benutzer->vorname.' '.$lernender->benutzer->nachname : null">
            <x-slot:aktionen>
                <a href="{{ $zurueck }}" class="inline-flex h-9 items-center gap-2 rounded-lg glass-btn px-3.5 text-sm font-medium text-text">{{ __('Zurück') }}</a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    @php
        $feld = 'w-full rounded-xl border border-border bg-input text-text text-sm px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring';
        $label = 'text-sm font-medium text-text';
        $ebenen = ['gesamt' => __('Gesamt'), 'kategorie' => __('Kategorie'), 'semester' => __('Semester'), 'fach' => __('Fach'), 'modul' => __('Modul')];
    @endphp

    <div class="py-6"
         x-data="npRechner(@js(['daten' => $daten, 'berechnenUrl' => $berechnenUrl, 'zielUrl' => $zielUrl, 'start' => $start]))">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col gap-5">

            {{-- Gespeicherte Ziele --}}
            <div class="flex flex-wrap items-center gap-2" x-show="ziele.length" x-cloak>
                <template x-for="z in ziele" :key="z.id">
                    <div class="inline-flex items-center rounded-full border text-sm transition-colors"
                         :class="zielText === z.ziel ? 'border-accent/50 bg-accent/10 text-accent' : 'border-border bg-card/60 text-text'">
                        <button type="button" class="pl-3 pr-2 min-h-9 inline-flex items-center gap-1.5" @click="setzeZiel(z.ziel, z.zielwert)">
                            <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10 2a8 8 0 100 16 8 8 0 000-16zm0 3a5 5 0 110 10 5 5 0 010-10zm0 3a2 2 0 100 4 2 2 0 000-4z"/></svg>
                            <span x-text="z.label"></span>
                            <span class="font-semibold tabular-nums" x-text="'≥ ' + fmt(z.zielwert)"></span>
                        </button>
                        @if($zielUrl)
                            <form method="POST" :action="@js(route('learner.goals.destroy', 0)).replace(/0$/, z.id)" class="pr-1">
                                @csrf
                                @method('DELETE')
                                <button class="w-9 h-9 inline-flex items-center justify-center rounded-full text-muted hover:text-text hover:bg-bg" aria-label="{{ __('Ziel entfernen') }}">×</button>
                            </form>
                        @endif
                    </div>
                </template>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-start">

                {{-- Eingaben --}}
                <div class="lg:col-span-5 flex flex-col gap-5">
                    <section class="rounded-xl border border-border bg-card p-5 flex flex-col gap-4">
                        <div role="tablist" aria-label="{{ __('Ebene') }}" class="grid grid-cols-5 gap-1 p-1 rounded-xl bg-bg/60 border border-border">
                            @foreach($ebenen as $wert => $name)
                                <button type="button" role="tab" :aria-selected="ebene === '{{ $wert }}'" @click="waehleEbene('{{ $wert }}')"
                                        class="min-h-9 rounded-lg text-xs sm:text-sm font-medium transition-colors"
                                        :class="ebene === '{{ $wert }}' ? 'bg-card text-accent shadow-sm' : 'text-muted hover:text-text'">{{ $name }}</button>
                            @endforeach
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" x-show="ebene !== 'gesamt'" x-cloak>
                            <div :class="['kategorie', 'fach'].includes(ebene) ? '' : 'sm:col-span-2'">
                                <label for="ziel-id" class="{{ $label }}" x-text="{ kategorie: @js(__('Kategorie')), semester: @js(__('Semester')), fach: @js(__('Fach')), modul: @js(__('Modul')) }[ebene]"></label>
                                <select id="ziel-id" x-model="zielId" class="mt-1 {{ $feld }}">
                                    <template x-if="ebene === 'kategorie'"><template x-for="k in katalog.kategorien" :key="k.id"><option :value="String(k.id)" x-text="k.name" :selected="String(k.id) === zielId"></option></template></template>
                                    <template x-if="ebene === 'semester'"><template x-for="s in katalog.semester" :key="s.id"><option :value="String(s.id)" x-text="s.name" :selected="String(s.id) === zielId"></option></template></template>
                                    <template x-if="ebene === 'fach'"><template x-for="f in katalog.faecher" :key="f.id"><option :value="String(f.id)" x-text="f.name" :selected="String(f.id) === zielId"></option></template></template>
                                    <template x-if="ebene === 'modul'"><template x-for="m in katalog.module" :key="m.id"><option :value="String(m.id)" x-text="m.name" :selected="String(m.id) === zielId"></option></template></template>
                                </select>
                            </div>
                            <div x-show="['kategorie', 'fach'].includes(ebene)">
                                <label for="ziel-zeitraum" class="{{ $label }}">{{ __('Zeitraum') }}</label>
                                <select id="ziel-zeitraum" x-model="zeitraum" class="mt-1 {{ $feld }}">
                                    <option value="">{{ __('Ganze Lehrzeit') }}</option>
                                    <template x-for="s in katalog.semester" :key="s.id"><option :value="String(s.id)" x-text="s.name" :selected="String(s.id) === zeitraum"></option></template>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label for="zielwert" class="{{ $label }}">{{ __('Mindestens') }}</label>
                            <div class="mt-1 flex items-center gap-2">
                                <button type="button" class="w-10 h-10 rounded-xl glass-btn text-text text-lg" aria-label="{{ __('Zielwert senken') }}"
                                        @click="zielwert = String(Math.max(1, Math.round((parseFloat(zielwert) - 0.1) * 10) / 10))">−</button>
                                <input id="zielwert" type="number" min="1" max="6" step="0.05" x-model="zielwert"
                                       class="w-24 h-12 text-2xl font-bold text-center tabular-nums rounded-xl border border-border bg-input focus:ring-2 focus:ring-ring focus:border-ring"
                                       :class="klasse(zielwert)">
                                <button type="button" class="w-10 h-10 rounded-xl glass-btn text-text text-lg" aria-label="{{ __('Zielwert erhöhen') }}"
                                        @click="zielwert = String(Math.min(6, Math.round((parseFloat(zielwert) + 0.1) * 10) / 10))">+</button>
                                <div class="flex flex-wrap gap-1 ml-1">
                                    @foreach(['4.0', '4.5', '5.0', '5.5'] as $v)
                                        <button type="button" @click="zielwert = '{{ $v }}'"
                                                class="min-h-9 px-2.5 rounded-lg border text-xs tabular-nums transition-colors"
                                                :class="parseFloat(zielwert) === {{ $v }} ? 'border-accent/50 bg-accent/10 text-accent' : 'border-border text-muted hover:text-text'">{{ $v }}</button>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between gap-3 pt-3 border-t border-border/70">
                            <div class="text-sm">
                                <span class="text-muted">{{ __('Aktuell') }}</span>
                                <span class="ml-1 font-bold tabular-nums" :class="klasse(ergebnis?.loesung.aktuell)" x-text="fmt(ergebnis?.loesung.aktuell)"></span>
                            </div>
                            @if($zielUrl)
                                <form method="POST" action="{{ $zielUrl }}" x-show="speicherbar" x-cloak>
                                    @csrf
                                    <input type="hidden" name="ziel" :value="zielText">
                                    <input type="hidden" name="zielwert" :value="zielwert">
                                    <button class="inline-flex items-center gap-1.5 px-3 min-h-9 rounded-xl glass-btn text-sm text-text"
                                            x-text="gespeichertesZiel ? (parseFloat(gespeichertesZiel.zielwert) === parseFloat(zielwert) ? @js(__('Ziel gespeichert')) : @js(__('Ziel aktualisieren'))) : @js(__('Als Ziel speichern'))"
                                            :disabled="gespeichertesZiel && parseFloat(gespeichertesZiel.zielwert) === parseFloat(zielwert)"></button>
                                </form>
                            @endif
                        </div>
                    </section>

                    <section class="rounded-xl border border-border bg-card overflow-hidden">
                        <div class="px-5 py-4 flex items-center justify-between gap-3 border-b border-border/70">
                            <h3 class="font-semibold text-text">{{ __('Offene Prüfungen') }} <span class="ml-1 text-sm text-muted tabular-nums" x-text="offene"></span></h3>
                            <div class="flex gap-1.5">
                                <button type="button" x-show="katalog.faecher.length" @click="neueZeile('fach')" class="px-3 min-h-9 rounded-lg glass-btn text-xs text-text">+ {{ __('Fach') }}</button>
                                <button type="button" x-show="katalog.module.length" @click="neueZeile('modul')" class="px-3 min-h-9 rounded-lg glass-btn text-xs text-text">+ {{ __('Modul') }}</button>
                            </div>
                        </div>

                        <div class="divide-y divide-border/70">
                            <template x-for="z in zeilen" :key="z.nr">
                                <div class="px-4 py-3 flex flex-col gap-2">
                                    <div class="flex items-center gap-2">
                                        <select x-model="z.id" class="flex-1 min-w-0 {{ $feld }}" :aria-label="z.typ === 'fach' ? @js(__('Fach')) : @js(__('Modul'))">
                                            <option value="">–</option>
                                            <template x-for="o in (z.typ === 'fach' ? katalog.faecher.filter(f => f.erfassbar || String(f.id) === z.id) : katalog.module)" :key="o.id">
                                                <option :value="String(o.id)" x-text="o.name" :selected="String(o.id) === z.id"></option>
                                            </template>
                                        </select>
                                        <button type="button" @click="entferne(z.nr)" class="w-9 h-9 shrink-0 inline-flex items-center justify-center rounded-lg text-muted hover:text-note-ungenuegend hover:bg-note-ungenuegend/10" aria-label="{{ __('Prüfung entfernen') }}">×</button>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <select x-show="z.typ === 'fach'" x-model="z.semester" class="flex-1 min-w-0 {{ $feld }}" aria-label="{{ __('Semester') }}">
                                            <template x-for="s in katalog.semester" :key="s.id"><option :value="String(s.id)" x-text="s.name" :selected="String(s.id) === z.semester"></option></template>
                                        </select>
                                        <div class="flex-1 min-w-0 flex items-center gap-2 text-xs text-muted" x-show="z.typ !== 'fach'">
                                            <span x-show="z.quelle === 'rest'" class="px-2 py-0.5 rounded-full bg-accent/10 text-accent font-medium">{{ __('Rest') }}</span>
                                            <span x-show="z.quelle === 'geplant'" class="px-2 py-0.5 rounded-full bg-accent/10 text-accent font-medium">{{ __('geplant') }}</span>
                                            <span class="truncate" x-text="z.titel ?? ''"></span>
                                        </div>
                                        <label class="flex items-center gap-1 text-xs text-muted shrink-0">
                                            <input type="number" min="0" max="100" step="1" x-model="z.gewicht" class="w-16 rounded-lg border border-border bg-input text-text text-sm px-2 py-1.5 text-right tabular-nums focus:ring-2 focus:ring-ring" aria-label="{{ __('Gewichtung in Prozent') }}">%
                                        </label>
                                        <input type="number" min="1" max="6" step="0.05" x-model="z.wert" placeholder="?"
                                               class="w-16 shrink-0 rounded-lg border border-border bg-input text-sm px-2 py-1.5 text-center font-semibold tabular-nums focus:ring-2 focus:ring-ring placeholder:text-accent"
                                               :class="z.wert === '' ? 'border-accent/40' : klasse(z.wert)" aria-label="{{ __('Note (leer = gesucht)') }}">
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div x-show="!zeilen.length" x-cloak class="px-5 py-8 text-center text-sm text-muted">{{ __('Keine offenen Prüfungen') }}</div>
                    </section>
                </div>

                {{-- Ergebnis --}}
                <div class="lg:col-span-7 flex flex-col gap-5">
                    <section class="rounded-xl border border-border bg-card p-6 sm:p-8 text-center transition-opacity" :class="laedt ? 'opacity-70' : ''" aria-live="polite">
                        <p x-show="fehler" x-cloak class="text-sm text-note-ungenuegend" x-text="fehler"></p>

                        <template x-if="ergebnis && !fehler">
                            <div class="relative">
                                <div class="text-[11px] uppercase tracking-widest text-muted font-medium"
                                     x-text="{ benoetigt: @js(__('Benötigt')), erreicht: @js(__('Schon erreicht')), unerreichbar: @js(__('Nicht erreichbar')), ohne_einfluss: @js(__('Kein Einfluss')), keine_unbekannten: @js(__('Ergebnis')) }[ergebnis.loesung.status]"></div>

                                <div class="mt-2 text-7xl font-extrabold tabular-nums tracking-tight" :class="heroKlasse">
                                    <span x-show="ergebnis.loesung.status === 'benoetigt'" x-text="fmt(ergebnis.loesung.note, 2)"></span>
                                    <span x-show="ergebnis.loesung.status === 'erreicht'">✓</span>
                                    <span x-show="['unerreichbar', 'ohne_einfluss', 'keine_unbekannten'].includes(ergebnis.loesung.status)" x-text="fmt(ergebnis.loesung.resultat ?? ergebnis.loesung.aktuell)"></span>
                                </div>

                                <p class="mt-2 text-sm text-muted">
                                    <span x-show="ergebnis.loesung.status === 'benoetigt'" x-text="ergebnis.loesung.unbekannte === 1 ? @js(__('in der offenen Prüfung')) : @js(__('in jeder der ')) + ergebnis.loesung.unbekannte + @js(__(' offenen Prüfungen'))"></span>
                                    <span x-show="ergebnis.loesung.status === 'erreicht'" x-text="@js(__('auch mit 1.0 bleibt es bei ')) + fmt(ergebnis.loesung.minimum)"></span>
                                    <span x-show="ergebnis.loesung.status === 'unerreichbar'">{{ __('höchstens, mit lauter 6.0') }}</span>
                                    <span x-show="ergebnis.loesung.status === 'ohne_einfluss'">{{ __('die offenen Prüfungen zählen hier nicht') }}</span>
                                    <span x-show="ergebnis.loesung.status === 'keine_unbekannten'"
                                          x-text="(ergebnis.loesung.resultat ?? 0) >= ergebnis.ziel.zielwert ? @js(__('Ziel erreicht')) : @js(__('es fehlen ')) + fmt(ergebnis.ziel.zielwert - (ergebnis.loesung.resultat ?? 0), 2)"></span>
                                </p>

                                <div class="mt-5 inline-flex flex-wrap items-center justify-center gap-2 rounded-full bg-bg/60 border border-border px-4 py-1.5 text-sm">
                                    <span class="text-muted" x-text="ergebnis.ziel.label"></span>
                                    <span class="font-semibold tabular-nums" x-text="'≥ ' + fmt(ergebnis.ziel.zielwert)"></span>
                                </div>
                            </div>
                        </template>
                    </section>

                    <section class="rounded-xl border border-border bg-card p-5" x-show="kurve" x-cloak>
                        <h3 class="text-sm font-semibold text-text mb-3">{{ __('Ergebnis je Note in den offenen Prüfungen') }}</h3>
                        <div class="h-56" x-data="npChart('kurve')" x-effect="zeichne(kurve)">
                            <canvas x-ref="canvas" role="img" aria-label="{{ __('Ergebnis in Abhängigkeit der Note') }}"></canvas>
                        </div>
                    </section>

                    <section class="rounded-xl border border-border bg-card overflow-hidden" x-show="ergebnis?.vergleich?.length" x-cloak>
                        <div class="px-5 py-3 border-b border-border/70 flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-text">{{ __('Auswirkung') }}</h3>
                            <span class="text-xs text-muted tabular-nums" x-show="ergebnis?.loesung.status === 'benoetigt'" x-text="@js(__('mit ')) + fmt(ergebnis?.loesung.note, 2)"></span>
                        </div>
                        <div class="divide-y divide-border/70">
                            <template x-for="v in ergebnis?.vergleich ?? []" :key="v.text">
                                <div class="px-5 py-2.5 flex items-center justify-between gap-3 text-sm" :class="v.ist_ziel ? 'bg-accent/5' : ''">
                                    <span class="truncate" :class="v.ist_ziel ? 'font-semibold text-text' : 'text-muted'" x-text="v.label"></span>
                                    <span class="shrink-0 flex items-center gap-2 tabular-nums">
                                        <span class="text-muted" x-text="fmt(v.vorher)"></span>
                                        <span class="text-muted" aria-hidden="true">→</span>
                                        <span class="font-bold min-w-10 text-right" :class="klasse(v.nachher)" x-text="fmt(v.nachher)"></span>
                                        <span class="w-12 text-right text-xs"
                                              :class="delta(v.vorher, v.nachher) > 0 ? 'text-note-gut' : (delta(v.vorher, v.nachher) < 0 ? 'text-note-ungenuegend' : 'text-muted')"
                                              x-text="delta(v.vorher, v.nachher) === null || delta(v.vorher, v.nachher) === 0 ? '' : (delta(v.vorher, v.nachher) > 0 ? '+' : '') + fmt(delta(v.vorher, v.nachher), 2)"></span>
                                    </span>
                                </div>
                            </template>
                        </div>
                    </section>

                    <section class="rounded-xl border border-border bg-card p-5 flex flex-col gap-3" x-show="ergebnis?.promotion?.length" x-cloak>
                        <h3 class="text-sm font-semibold text-text">{{ __('Promotion') }}</h3>
                        <template x-for="p in ergebnis?.promotion ?? []" :key="p.kategorie + p.semester">
                            <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl border px-4 py-3"
                                 :class="p.nachher.erfuellt ? 'border-note-gut/30 bg-note-gut/5' : 'border-note-ungenuegend/30 bg-note-ungenuegend/5'">
                                <div class="text-sm">
                                    <span class="font-semibold text-text" x-text="p.kategorie"></span>
                                    <span class="text-muted" x-text="p.semester"></span>
                                </div>
                                <div class="flex items-center gap-3 text-xs text-muted tabular-nums">
                                    <span>{{ __('Schnitt') }} <b class="text-text" x-text="fmt(p.nachher.schnitt)"></b></span>
                                    <span>{{ __('ungenügend') }} <b class="text-text" x-text="p.nachher.ungenuegend"></b></span>
                                    <span>{{ __('Minuspunkte') }} <b class="text-text" x-text="fmt(p.nachher.minuspunkte, 1)"></b></span>
                                    <span class="px-2 py-0.5 rounded-full font-semibold"
                                          :class="p.nachher.erfuellt ? 'bg-note-gut/14 text-note-gut' : 'bg-note-ungenuegend/14 text-note-ungenuegend'"
                                          x-text="p.nachher.erfuellt ? @js(__('erfüllt')) : @js(__('gefährdet'))"></span>
                                </div>
                            </div>
                        </template>
                    </section>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
