<x-app-layout>
    <x-slot name="title">{{ $lernender->benutzer->vorname }} {{ $lernender->benutzer->nachname }}</x-slot>
    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-4 flex-wrap">
            <div>
                <nav class="text-xs text-muted flex items-center gap-1 mb-1" aria-label="Brotkrumen">
                    <a href="{{ route("{$bereich}.lernende.index") }}" class="hover:text-text transition-colors">Lernende</a>
                    <span class="text-muted/40">›</span>
                    <span class="text-text">{{ $lernender->benutzer->nachname }} {{ $lernender->benutzer->vorname }}</span>
                </nav>
                <h2 class="font-semibold text-xl text-text">{{ $lernender->benutzer->nachname }} {{ $lernender->benutzer->vorname }}</h2>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <a href="{{ route("{$bereich}.lernende.noten.index", $lernender->lernender_id) }}"
                   class="inline-flex items-center px-4 h-10 rounded-xl bg-accent text-white text-sm np-btn-primary whitespace-nowrap">Noten</a>
                @can('update', $lernender)
                    <a href="{{ route("{$bereich}.lernende.edit", $lernender->lernender_id) }}"
                       class="inline-flex items-center px-4 h-10 rounded-xl glass-btn text-text text-sm whitespace-nowrap">Bearbeiten</a>
                @endcan
                <a href="{{ route("{$bereich}.lernende.noten.drucken", $lernender->lernender_id) }}" target="_blank"
                   class="inline-flex items-center px-4 h-10 rounded-xl glass-btn text-text text-sm whitespace-nowrap">Drucken</a>
                <a href="{{ route("{$bereich}.lernende.noten.export", $lernender->lernender_id) }}"
                   class="inline-flex items-center px-4 h-10 rounded-xl glass-btn text-text text-sm whitespace-nowrap">CSV</a>
            </div>
        </div>
    </x-slot>

    @php
        $benutzer = $lernender->benutzer;
        $notenfarbe = fn (?float $v) => $v === null ? 'text-muted'
            : ($v >= 5.0 ? 'text-green-700 dark:text-green-400'
            : ($v >= 4.0 ? 'text-emerald-700 dark:text-emerald-400'
            : ($v >= 3.5 ? 'text-yellow-700 dark:text-yellow-400'
            : 'text-red-600 dark:text-red-400')));
        $datum = fn ($d) => $d ? \Carbon\Carbon::parse($d)->format('d.m.Y') : '–';
        $label = 'text-xs uppercase tracking-widest text-muted font-medium';
        $feld = 'mt-1 w-full rounded-xl border border-border bg-input text-text text-sm px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring';
        $heute = today();
    @endphp

    <div class="py-6">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if(session('startpasswort'))
                <div class="glass accent-glow rounded-2xl p-5 flex flex-wrap items-center justify-between gap-4"
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

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                {{-- Stammdaten + Lehrdaten --}}
                <div class="lg:col-span-2 glass rounded-2xl p-5">
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                        <div><dt class="{{ $label }}">E-Mail</dt><dd class="text-text break-all">{{ $benutzer->email }}</dd></div>
                        <div><dt class="{{ $label }}">Benutzername</dt><dd class="text-text font-mono">{{ $benutzer->benutzername }}</dd></div>
                        <div><dt class="{{ $label }}">Lehrberuf</dt><dd class="text-text">{{ $lernender->lehrberuf?->name ?? '–' }}</dd></div>
                        <div><dt class="{{ $label }}">Lehrjahr</dt><dd class="text-text">{{ $lernender->lehrjahr() ? $lernender->lehrjahr().'. Lehrjahr' : '–' }}</dd></div>
                        <div><dt class="{{ $label }}">Lehrbeginn</dt><dd class="text-text">{{ $datum($lernender->lehrbeginn) }}</dd></div>
                        <div><dt class="{{ $label }}">Lehrende</dt><dd class="text-text">{{ $datum($lernender->lehrende) }}</dd></div>
                        <div><dt class="{{ $label }}">Klasse Schule</dt><dd class="text-text">{{ $lernender->klasse_schule ?: '–' }}</dd></div>
                        <div><dt class="{{ $label }}">Klasse BMS</dt><dd class="text-text">{{ $lernender->klasse_bms ?: '–' }}</dd></div>
                        <div class="sm:col-span-2">
                            <dt class="{{ $label }}">Bemerkung (intern)</dt>
                            <dd class="text-text whitespace-pre-line">{{ $lernender->bemerkung ?: '–' }}</dd>
                        </div>
                    </dl>
                </div>

                {{-- Ø gesamt --}}
                <div class="glass rounded-2xl p-5 flex flex-col items-center justify-center text-center np-card-lift">
                    <div class="{{ $label }}">Ø gesamt</div>
                    <div class="text-4xl font-extrabold tracking-tight tabular-nums mt-1 {{ $notenfarbe($globalAvg) }}">
                        {{ $globalAvg !== null ? number_format($globalAvg, 2) : '–' }}
                    </div>
                    <div class="text-xs text-muted mt-1">{{ $noteCount }} {{ $noteCount === 1 ? 'Note' : 'Noten' }}</div>
                    <div class="text-xs text-muted">Letzte: {{ $lastEntry?->format('d.m.Y') ?? '–' }}</div>
                </div>
            </div>

            {{-- Konto --}}
            @can('verwalten', $lernender)
                <div class="glass rounded-2xl p-5 flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="{{ $label }}">Konto</span>
                        @if($benutzer->aktiv)
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300">Aktiv</span>
                        @else
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300">Inaktiv</span>
                        @endif
                        @if($benutzer->passwort_wechsel_noetig)
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs bg-bg border border-border text-muted">Passwortwechsel ausstehend</span>
                        @endif
                    </div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <form method="POST" action="{{ route("{$bereich}.lernende.konto.passwort", $lernender->lernender_id) }}"
                              x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true"
                              onsubmit="return confirm('Neues Startpasswort erzeugen? Das bisherige Passwort wird ungültig.');">
                            @csrf
                            <button type="submit" :disabled="loading" class="px-4 h-10 rounded-xl glass-btn text-text text-sm disabled:opacity-60">Passwort zurücksetzen</button>
                        </form>
                        <form method="POST" action="{{ route("{$bereich}.lernende.konto.aktiv", $lernender->lernender_id) }}"
                              x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true"
                              onsubmit="return confirm('{{ $benutzer->aktiv ? 'Konto deaktivieren? Anmelden ist danach nicht mehr möglich.' : 'Konto aktivieren?' }}');">
                            @csrf
                            <button type="submit" :disabled="loading"
                                    class="px-4 h-10 rounded-xl text-sm border disabled:opacity-60 {{ $benutzer->aktiv ? 'border-red-300 dark:border-red-800 text-red-600 dark:text-red-400 hover:bg-red-500/10' : 'border-border text-text hover:bg-accent/5' }}">
                                {{ $benutzer->aktiv ? 'Deaktivieren' : 'Aktivieren' }}
                            </button>
                        </form>
                    </div>
                </div>
            @endcan

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                {{-- Betreuungen --}}
                <div class="glass rounded-2xl overflow-hidden">
                    <div class="px-5 py-4 border-b border-border"><h3 class="font-semibold text-text text-sm">Betreuung</h3></div>
                    <div class="divide-y divide-border">
                        @forelse($lernender->betreuungen as $bt)
                            @php $offen = ! $bt->gueltig_bis || $bt->gueltig_bis->gte($heute); @endphp
                            <div class="px-5 py-3 flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="text-sm font-medium text-text">
                                        {{ $bt->berufsbildner?->benutzer?->nachname }} {{ $bt->berufsbildner?->benutzer?->vorname }}
                                    </div>
                                    <div class="text-xs text-muted">{{ $bt->gueltig_von->format('d.m.Y') }} – {{ $bt->gueltig_bis?->format('d.m.Y') ?? 'offen' }}</div>
                                </div>
                                @if($offen)
                                    @can('betreuungVerwalten', $lernender)
                                        <form method="POST" action="{{ route("{$bereich}.betreuungen.beenden", [$lernender->lernender_id, $bt->betreuung_id]) }}"
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
                        <form method="POST" action="{{ route("{$bereich}.lernende.betreuung.store", $lernender->lernender_id) }}"
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
                <div class="glass rounded-2xl overflow-hidden">
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
                                        <form method="POST" action="{{ route("{$bereich}.tracks.beenden", [$lernender->lernender_id, $t->lernender_track_id]) }}"
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
                        <form method="POST" action="{{ route("{$bereich}.lernende.tracks.store", $lernender->lernender_id) }}"
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

            <x-noten-verlauf :points="$notenVerlauf" title="Notenverlauf" subtitle="letzte {{ $notenVerlauf->count() }} Noten" />

            {{-- Semester-Ø --}}
            <div class="glass rounded-2xl overflow-hidden">
                <div class="px-5 py-4 border-b border-border"><h3 class="font-semibold text-text text-sm">Ø pro Semester</h3></div>
                @if($semStats->isEmpty())
                    <div class="px-5 py-6 text-sm text-muted text-center">Noch keine Noten.</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm text-text">
                            <thead class="bg-bg text-muted">
                                <tr>
                                    <th class="text-left p-3">Semester</th>
                                    <th class="text-center p-3">Noten</th>
                                    <th class="text-center p-3 whitespace-nowrap">Ø gewichtet</th>
                                    <th class="text-center p-3">Bestanden</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                @foreach($semStats as $s)
                                    @php $avg = $s->avg !== null ? (float) $s->avg : null; @endphp
                                    <tr class="even:bg-bg/30 hover:bg-accent/5">
                                        <td class="p-3 font-medium">
                                            <a href="{{ route("{$bereich}.lernende.noten.index", ['lernender_id' => $lernender->lernender_id, 'semester_id' => $s->semester_id]) }}"
                                               class="hover:text-accent">{{ $s->sem_label }}</a>
                                        </td>
                                        <td class="p-3 text-center text-muted tabular-nums">{{ $s->count }}</td>
                                        <td class="p-3 text-center font-semibold tabular-nums {{ $notenfarbe($avg) }}">{{ $avg !== null ? number_format($avg, 2) : '–' }}</td>
                                        <td class="p-3 text-center text-muted tabular-nums">{{ (int) $s->passed }} / {{ $s->count }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
