<x-app-layout>
    <x-slot name="title">{{ $lernender->benutzer->vorname }} {{ $lernender->benutzer->nachname }}</x-slot>
    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-4 flex-wrap">
            <div>
                <nav class="text-xs text-muted flex items-center gap-1 mb-1" aria-label="Brotkrumen">
                    <a href="{{ route("{$bereich}.learners.index") }}" class="hover:text-text transition-colors">Lernende</a>
                    <span class="text-muted/40">›</span>
                    <span class="text-text">{{ $lernender->benutzer->vorname }} {{ $lernender->benutzer->nachname }}</span>
                </nav>
                <div class="flex items-center gap-3">
                    <h2 class="font-semibold text-xl text-text">{{ $lernender->benutzer->vorname }} {{ $lernender->benutzer->nachname }}</h2>
                    <x-status :status="$stand->status" />
                </div>
                <p class="text-sm text-muted">{{ $lernender->lehrberuf?->name ?? '–' }}@if($lernender->lehrjahr()) · {{ $lernender->lehrjahr() }}. Lehrjahr @endif</p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <a href="{{ route("{$bereich}.learners.grades.index", $lernender->lernender_id) }}"
                   class="inline-flex items-center px-4 h-10 rounded-xl bg-accent text-white text-sm np-btn-primary whitespace-nowrap">Noten</a>
                <a href="{{ route("{$bereich}.learners.documents.index", $lernender->lernender_id) }}"
                   class="inline-flex items-center px-4 h-10 rounded-xl glass-btn text-text text-sm whitespace-nowrap">Dokumente</a>
                @can('noteAnlegen', $lernender)
                    <a href="{{ route("{$bereich}.learners.grades.import.index", $lernender->lernender_id) }}"
                       class="inline-flex items-center px-4 h-10 rounded-xl glass-btn text-text text-sm whitespace-nowrap">Import</a>
                @endcan
                <a href="{{ route("{$bereich}.learners.calculator", $lernender->lernender_id) }}"
                   class="inline-flex items-center px-4 h-10 rounded-xl glass-btn text-text text-sm whitespace-nowrap">Rechner</a>
                @can('update', $lernender)
                    <a href="{{ route("{$bereich}.learners.edit", $lernender->lernender_id) }}"
                       class="inline-flex items-center px-4 h-10 rounded-xl glass-btn text-text text-sm whitespace-nowrap">Bearbeiten</a>
                @endcan
                <a href="{{ route("{$bereich}.learners.grades.print", $lernender->lernender_id) }}" target="_blank"
                   class="inline-flex items-center px-4 h-10 rounded-xl glass-btn text-text text-sm whitespace-nowrap">Drucken</a>
                <a href="{{ route("{$bereich}.learners.grades.export", $lernender->lernender_id) }}"
                   class="inline-flex items-center px-4 h-10 rounded-xl glass-btn text-text text-sm whitespace-nowrap">CSV</a>
            </div>
        </div>
    </x-slot>

    @php
        $benutzer = $lernender->benutzer;
        $a = $stand->auswertung;
        $delta = $stand->delta();
        $datum = fn ($d) => $d ? \Carbon\Carbon::parse($d)->format('d.m.Y') : '–';
        $label = 'text-xs uppercase tracking-widest text-muted font-medium';
        $feld = 'mt-1 w-full rounded-xl border border-border bg-input text-text text-sm px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring';
        $heute = today();
    @endphp

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-12 gap-5">

            @if(session('startpasswort'))
                <div class="lg:col-span-12 glass accent-glow rounded-2xl p-5 flex flex-wrap items-center justify-between gap-4"
                     x-data="{ kopiert: false }">
                    <div>
                        <div class="{{ $label }}">Startpasswort · wird nur einmal angezeigt</div>
                        <div class="mt-1 font-mono text-2xl font-bold tracking-wider text-text select-all" x-ref="pw">{{ session('startpasswort') }}</div>
                    </div>
                    <button type="button"
                            @click="navigator.clipboard.writeText($refs.pw.textContent.trim()); kopiert = true; setTimeout(() => kopiert = false, 2000)"
                            class="px-4 h-10 rounded-xl glass-btn text-text text-sm">
                        <span x-show="!kopiert">Kopieren</span>
                        <span x-show="kopiert" x-cloak>Kopiert</span>
                    </button>
                </div>
            @endif

            {{-- Stand --}}
            <section class="lg:col-span-4 glass rounded-3xl p-6 flex flex-col gap-4 relative overflow-hidden">
                <div class="absolute -top-24 -right-16 w-64 h-64 rounded-full bg-accent/10 blur-3xl pointer-events-none" aria-hidden="true"></div>
                <div class="relative flex items-start justify-between gap-3">
                    <div>
                        <div class="{{ $label }}">Gesamtschnitt</div>
                        <x-note :wert="$a->gesamtNote" variante="hero" :stellen="1" class="block text-5xl mt-1" />
                    </div>
                    <div class="text-right">
                        <div class="{{ $label }}">{{ $a->konfiguration->semesterName($stand->semesterId) }}</div>
                        <x-note :wert="$stand->semesterNote" :stellen="1" class="text-2xl font-extrabold" />
                        @if($delta !== null && $delta != 0)
                            <div class="text-xs font-semibold {{ $delta > 0 ? 'text-green-700 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">{{ $delta > 0 ? '▲ +' : '▼ ' }}{{ \App\Support\NotenSkala::format($delta, 1) }}</div>
                        @endif
                    </div>
                </div>
                <div class="relative flex flex-col gap-1.5">
                    @forelse($stand->gruende as $g)
                        <div class="flex items-center gap-2 text-sm">
                            <span class="w-1.5 h-1.5 rounded-full shrink-0 {{ $stand->status === 'rot' ? 'bg-red-500' : 'bg-yellow-500' }}" aria-hidden="true"></span>
                            <span class="text-text">{{ $g }}</span>
                        </div>
                    @empty
                        <div class="text-sm text-green-700 dark:text-green-400">Keine Auffälligkeiten</div>
                    @endforelse
                </div>
                <div class="relative mt-auto pt-3 border-t border-border/70 grid grid-cols-2 gap-3 text-xs text-muted">
                    <div>Letzte Prüfung<div class="text-sm text-text">{{ $stand->letztePruefung?->format('d.m.Y') ?? '–' }}</div></div>
                    <div>Lehrende<div class="text-sm text-text">{{ $datum($lernender->lehrende) }}</div></div>
                </div>
            </section>

            {{-- Verlauf --}}
            <x-karte titel="Verlauf" class="lg:col-span-8"
                     x-data="{ modus: 'kategorien', fach: 0, d: {{ \Illuminate\Support\Js::from($verlauf) }} }">
                <x-slot:aktionen>
                    <div class="flex items-center gap-1 p-0.5 rounded-lg bg-bg/60 border border-border text-xs">
                        <button type="button" @click="modus = 'kategorien'" class="px-2.5 min-h-8 rounded-md whitespace-nowrap" :class="modus === 'kategorien' ? 'bg-card text-accent shadow-sm' : 'text-muted'">Kategorien</button>
                        <button type="button" @click="modus = 'fach'" x-show="d.faecher.length" class="px-2.5 min-h-8 rounded-md whitespace-nowrap" :class="modus === 'fach' ? 'bg-card text-accent shadow-sm' : 'text-muted'">Fach</button>
                    </div>
                    <select x-show="modus === 'fach'" x-model.number="fach" class="rounded-lg border border-border bg-input text-text text-xs py-1.5 pl-2 pr-7" aria-label="Fach">
                        <template x-for="(f, i) in d.faecher" :key="i"><option :value="i" x-text="f.name"></option></template>
                    </select>
                </x-slot:aktionen>
                @if(count($verlauf['labels']))
                    <div class="h-64" x-data="npChart('verlauf')"
                         x-effect="zeichne(modus === 'fach' && d.faecher[fach]
                            ? { labels: d.labels, grenze: d.grenze, serien: [{ name: d.faecher[fach].name, werte: d.faecher[fach].werte, farbe: '--accent', dick: true }] }
                            : { labels: d.labels, grenze: d.grenze, serien: d.serien })">
                        <canvas x-ref="canvas" role="img" aria-label="Notenverlauf je Semester"></canvas>
                    </div>
                @else
                    <div class="py-12 text-center text-sm text-muted">Noch keine Noten</div>
                @endif
            </x-karte>

            {{-- Zeugnisnoten Fach × Semester --}}
            <x-karte titel="Zeugnisnoten" class="lg:col-span-12" :polster="false">
                <x-heatmap :daten="$heatmap" />
            </x-karte>

            {{-- Ziele und Prüfungen des Lernenden --}}
            <x-karte titel="Ziele" class="lg:col-span-6">
                <div class="flex flex-col gap-2">
                    @forelse($ziele as $z)
                        <a href="{{ $z['link'] }}" class="flex items-center justify-between gap-3 rounded-xl border border-border/70 bg-bg/40 hover:bg-accent/5 px-4 py-2.5 transition-colors">
                            <span class="text-sm text-text truncate">{{ $z['label'] }}</span>
                            <span class="text-sm tabular-nums shrink-0"><x-note :wert="$z['aktuell']" :stellen="1" /> <span class="text-muted">/ {{ \App\Support\NotenSkala::format($z['zielwert']) }}</span></span>
                        </a>
                    @empty
                        <div class="py-6 text-center text-sm text-muted">Keine Ziele gesetzt</div>
                    @endforelse
                </div>
            </x-karte>

            <x-karte titel="Geplante Prüfungen" class="lg:col-span-6" :polster="false">
                <div class="divide-y divide-border/70">
                    @forelse($pruefungen as $p)
                        @php $vorbei = $p->datum->lt($heute); @endphp
                        <div class="px-5 py-2.5 flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-sm text-text truncate">{{ $p->bezeichnung() }}</div>
                                <div class="text-xs {{ $vorbei ? 'text-yellow-700 dark:text-yellow-400 font-medium' : 'text-muted' }}">{{ $p->datum->format('d.m.Y') }}{{ $vorbei ? ' · Note fehlt' : '' }} · {{ \App\Support\Zahl::prozent($p->gewichtung_prozent) }}</div>
                            </div>
                        </div>
                    @empty
                        <div class="px-5 py-8 text-center text-sm text-muted">Keine</div>
                    @endforelse
                </div>
            </x-karte>

            {{-- Stammdaten --}}
            <x-karte titel="Profil" class="lg:col-span-8">
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                    <div><dt class="{{ $label }}">E-Mail</dt><dd class="text-text break-all"><a href="mailto:{{ $benutzer->email }}" class="hover:text-accent">{{ $benutzer->email }}</a></dd></div>
                    <div><dt class="{{ $label }}">Benutzername</dt><dd class="text-text font-mono">{{ $benutzer->benutzername }}</dd></div>
                    <div><dt class="{{ $label }}">Lehrbeginn</dt><dd class="text-text">{{ $datum($lernender->lehrbeginn) }}</dd></div>
                    <div><dt class="{{ $label }}">Lehrende</dt><dd class="text-text">{{ $datum($lernender->lehrende) }}</dd></div>
                    <div><dt class="{{ $label }}">Klasse Schule</dt><dd class="text-text">{{ $lernender->klasse_schule ?: '–' }}</dd></div>
                    <div><dt class="{{ $label }}">Klasse BMS</dt><dd class="text-text">{{ $lernender->klasse_bms ?: '–' }}</dd></div>
                    <div class="sm:col-span-2">
                        <dt class="{{ $label }}">Bemerkung (intern)</dt>
                        <dd class="text-text whitespace-pre-line">{{ $lernender->bemerkung ?: '–' }}</dd>
                    </div>
                </dl>
            </x-karte>

            {{-- Konto --}}
            <x-karte titel="Konto" class="lg:col-span-4">
                <div class="flex flex-col gap-3">
                    <div class="flex items-center gap-2 flex-wrap">
                        @if($benutzer->aktiv)
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300">Aktiv</span>
                        @else
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300">Inaktiv</span>
                        @endif
                        @if($benutzer->passwort_wechsel_noetig)
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-bg border border-border text-muted">Passwortwechsel ausstehend</span>
                        @endif
                    </div>
                    @can('verwalten', $lernender)
                        <div class="flex items-center gap-2 flex-wrap">
                            <form method="POST" action="{{ route("{$bereich}.learners.account.password", $lernender->lernender_id) }}"
                                  x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true"
                                  onsubmit="return confirm('Neues Startpasswort erzeugen? Das bisherige Passwort wird ungültig.');">
                                @csrf
                                <button type="submit" :disabled="loading" class="px-4 h-10 rounded-xl glass-btn text-text text-sm disabled:opacity-60">Passwort zurücksetzen</button>
                            </form>
                            <form method="POST" action="{{ route("{$bereich}.learners.account.active", $lernender->lernender_id) }}"
                                  x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true"
                                  onsubmit="return confirm('{{ $benutzer->aktiv ? 'Konto deaktivieren? Anmelden ist danach nicht mehr möglich.' : 'Konto aktivieren?' }}');">
                                @csrf
                                <button type="submit" :disabled="loading"
                                        class="px-4 h-10 rounded-xl text-sm border disabled:opacity-60 {{ $benutzer->aktiv ? 'border-red-300 dark:border-red-800 text-red-600 dark:text-red-400 hover:bg-red-500/10' : 'border-border text-text hover:bg-accent/5' }}">
                                    {{ $benutzer->aktiv ? 'Deaktivieren' : 'Aktivieren' }}
                                </button>
                            </form>
                        </div>
                    @endcan
                </div>
            </x-karte>

            {{-- Betreuungen --}}
            <div class="lg:col-span-6 glass rounded-2xl overflow-hidden">
                <div class="px-5 py-4 border-b border-border"><h3 class="font-semibold text-text text-sm">Betreuung</h3></div>
                <div class="divide-y divide-border">
                    @forelse($lernender->betreuungen as $bt)
                        @php $offen = ! $bt->gueltig_bis || $bt->gueltig_bis->gte($heute); @endphp
                        <div class="px-5 py-3 flex items-center justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-sm font-medium text-text">
                                    {{ $bt->berufsbildner?->benutzer?->vorname }} {{ $bt->berufsbildner?->benutzer?->nachname }}
                                </div>
                                <div class="text-xs text-muted">{{ $bt->gueltig_von->format('d.m.Y') }} – {{ $bt->gueltig_bis?->format('d.m.Y') ?? 'offen' }}</div>
                            </div>
                            @if($offen)
                                @can('betreuungVerwalten', $lernender)
                                    <form method="POST" action="{{ route("{$bereich}.supervisions.end", [$lernender->lernender_id, $bt->betreuung_id]) }}"
                                          x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true"
                                          onsubmit="return confirm('Betreuung beenden?');">
                                        @csrf
                                        <button type="submit" :disabled="loading"
                                                class="px-3 min-h-[36px] rounded-lg border border-red-300 dark:border-red-800 text-red-600 dark:text-red-400 text-xs hover:bg-red-500/10 disabled:opacity-60">Beenden</button>
                                    </form>
                                @endcan
                            @endif
                        </div>
                    @empty
                        <div class="px-5 py-5 text-sm text-muted text-center">Keine Betreuung.</div>
                    @endforelse
                </div>
                @can('betreuungVerwalten', $lernender)
                    <form method="POST" action="{{ route("{$bereich}.learners.supervision.store", $lernender->lernender_id) }}"
                          class="px-5 py-4 border-t border-border bg-bg/40 grid grid-cols-1 sm:grid-cols-3 gap-3 items-end"
                          x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true"
                          onsubmit="return confirm('Betreuung zuweisen? Die bisherige Betreuung endet am Vortag.');">
                        @csrf
                        <div class="sm:col-span-2">
                            <label for="berufsbildner_id" class="{{ $label }}">Berufsbildner *</label>
                            <select id="berufsbildner_id" name="berufsbildner_id" required class="{{ $feld }}">
                                <option value="">Bitte wählen</option>
                                @foreach($berufsbildnerListe as $bb)
                                    <option value="{{ $bb->berufsbildner_id }}" @selected(old('berufsbildner_id') == $bb->berufsbildner_id)>
                                        {{ $bb->benutzer->nachname }} {{ $bb->benutzer->vorname }}
                                    </option>
                                @endforeach
                            </select>
                            @error('berufsbildner_id')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="gueltig_von" class="{{ $label }}">Ab *</label>
                            <input id="gueltig_von" type="date" name="gueltig_von" required value="{{ old('gueltig_von', now()->toDateString()) }}" class="{{ $feld }}">
                            @error('gueltig_von')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                        </div>
                        <button type="submit" :disabled="loading"
                                class="sm:col-span-3 h-10 rounded-xl bg-accent text-white text-sm np-btn-primary disabled:opacity-60">Zuweisen</button>
                    </form>
                @endcan
            </div>

            {{-- Tracks --}}
            <div class="lg:col-span-6 glass rounded-2xl overflow-hidden">
                <div class="px-5 py-4 border-b border-border"><h3 class="font-semibold text-text text-sm">Schul-Tracks</h3></div>
                <div class="divide-y divide-border">
                    @forelse($lernender->tracks as $t)
                        <div class="px-5 py-3 flex flex-wrap items-center gap-3">
                            <span class="inline-flex px-2.5 py-1 rounded-lg text-xs font-bold {{ $t->end_datum ? 'bg-bg text-muted border border-border' : 'bg-accent/10 text-accent' }}">{{ $t->track_typ }}</span>
                            <div class="flex-1 min-w-0 text-sm text-text">
                                ab {{ $t->start_datum->format('d.m.Y') }}
                                @if($t->startSemester)<span class="text-muted">({{ $t->startSemester->bezeichnung }})</span>@endif
                                @if($t->end_datum)
                                    <span class="text-muted">– {{ $t->end_datum->format('d.m.Y') }}{{ $t->endSemester ? ' ('.$t->endSemester->bezeichnung.')' : '' }}</span>
                                @endif
                            </div>
                            @if(! $t->end_datum)
                                @can('verwalten', $lernender)
                                    <form method="POST" action="{{ route("{$bereich}.tracks.end", [$lernender->lernender_id, $t->lernender_track_id]) }}"
                                          class="flex items-center gap-2"
                                          x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true"
                                          onsubmit="return confirm('Track {{ $t->track_typ }} beenden?');">
                                        @csrf
                                        <label for="end_semester_{{ $t->lernender_track_id }}" class="sr-only">Endsemester</label>
                                        <select id="end_semester_{{ $t->lernender_track_id }}" name="end_semester_id" required
                                                class="rounded-lg border border-border bg-input text-text text-xs px-2 py-1 min-h-[36px] focus:ring-2 focus:ring-ring focus:border-ring">
                                            @foreach($semesterListe as $s)
                                                <option value="{{ $s->semester_id }}">{{ $s->bezeichnung }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" :disabled="loading"
                                                class="px-3 min-h-[36px] rounded-lg border border-red-300 dark:border-red-800 text-red-600 dark:text-red-400 text-xs hover:bg-red-500/10 disabled:opacity-60">Beenden</button>
                                    </form>
                                @endcan
                            @endif
                        </div>
                    @empty
                        <div class="px-5 py-5 text-sm text-muted text-center">Kein Track.</div>
                    @endforelse
                </div>
                @can('verwalten', $lernender)
                    <form method="POST" action="{{ route("{$bereich}.learners.tracks.store", $lernender->lernender_id) }}"
                          class="px-5 py-4 border-t border-border bg-bg/40 grid grid-cols-1 sm:grid-cols-3 gap-3 items-end"
                          x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                        @csrf
                        <div>
                            <label for="track_typ" class="{{ $label }}">Track *</label>
                            <select id="track_typ" name="track_typ" required class="{{ $feld }}">
                                <option value="BMS">BMS</option>
                                <option value="ABU">ABU</option>
                            </select>
                            @error('track_typ')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="start_datum" class="{{ $label }}">Start *</label>
                            <input id="start_datum" type="date" name="start_datum" required value="{{ old('start_datum', now()->toDateString()) }}" class="{{ $feld }}">
                            @error('start_datum')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="start_semester_id" class="{{ $label }}">Semester *</label>
                            <select id="start_semester_id" name="start_semester_id" required class="{{ $feld }}">
                                @foreach($semesterListe as $s)
                                    <option value="{{ $s->semester_id }}">{{ $s->bezeichnung }}</option>
                                @endforeach
                            </select>
                            @error('start_semester_id')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                        </div>
                        <button type="submit" :disabled="loading"
                                class="sm:col-span-3 h-10 rounded-xl bg-accent text-white text-sm np-btn-primary disabled:opacity-60">Track starten</button>
                    </form>
                @endcan
            </div>
        </div>
    </div>
</x-app-layout>
