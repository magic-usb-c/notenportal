<x-app-layout>
    <x-slot name="title">Übersicht</x-slot>
    <x-slot name="header">
        <div class="w-full flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-text">Hallo {{ auth()->user()->vorname }}</h2>
                @if($lehrzeit)
                    <p class="text-sm text-muted">{{ $lehrzeit['beruf'] }}@if($lehrzeit['lehrjahr']) · {{ $lehrzeit['lehrjahr'] }}. Lehrjahr @endif</p>
                @endif
            </div>
            <div class="flex gap-2">
                <a href="{{ route('lernender.pruefungen.index') }}" class="inline-flex items-center gap-2 px-4 h-10 rounded-xl glass-btn text-text text-sm">Prüfung planen</a>
                <a href="{{ route('lernender.noten.create') }}" class="inline-flex items-center gap-2 px-4 h-10 rounded-xl bg-accent text-white text-sm font-semibold np-btn-primary">
                    <span class="text-lg leading-none">+</span> Note
                </a>
            </div>
        </div>
    </x-slot>

    @php
        $a = $auswertung;
        $delta = $stand->delta();
    @endphp

    <div class="py-6">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-12 gap-5">

            {{-- Stand: Gesamtschnitt, Semester, Lehrzeit --}}
            <section class="lg:col-span-5 glass rounded-3xl p-6 relative overflow-hidden flex flex-col">
                <div class="absolute -top-24 -right-16 w-72 h-72 rounded-full bg-accent/10 blur-3xl pointer-events-none" aria-hidden="true"></div>
                @if($a->gesamtNote !== null)
                    <div class="relative flex items-start justify-between gap-4">
                        <div>
                            <div class="text-[11px] uppercase tracking-widest text-muted font-medium">Gesamtschnitt</div>
                            <x-note :wert="$a->gesamtNote" variante="hero" :stellen="1" class="block mt-1 text-6xl" />
                        </div>
                        <x-sparkline :werte="$stand->verlauf" :breite="120" :hoehe="44" class="mt-3" />
                    </div>
                    <div class="relative mt-5 grid grid-cols-2 gap-3">
                        <div class="rounded-2xl bg-bg/60 border border-border/70 px-4 py-3">
                            <div class="text-[11px] uppercase tracking-widest text-muted">{{ $a->konfiguration->semesterName($stand->semesterId) }}</div>
                            <div class="mt-0.5 flex items-baseline gap-2">
                                <x-note :wert="$stand->semesterNote" :stellen="1" class="text-2xl font-extrabold" />
                                @if($delta !== null && $delta != 0)
                                    <span class="text-xs font-semibold {{ $delta > 0 ? 'text-green-700 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                        {{ $delta > 0 ? '▲ +' : '▼ ' }}{{ \App\Support\NotenSkala::format($delta, 1) }}
                                    </span>
                                @endif
                            </div>
                        </div>
                        <div class="rounded-2xl bg-bg/60 border border-border/70 px-4 py-3">
                            <div class="text-[11px] uppercase tracking-widest text-muted">Ungenügend</div>
                            <div class="mt-0.5 text-2xl font-extrabold tabular-nums {{ count($stand->ungenuegend) ? 'text-red-600 dark:text-red-400' : 'text-text' }}">{{ count($stand->ungenuegend) }}</div>
                        </div>
                    </div>
                @else
                    <div class="relative py-6 text-center">
                        <div class="text-[11px] uppercase tracking-widest text-muted font-medium">Gesamtschnitt</div>
                        <div class="mt-2 text-5xl font-extrabold text-muted/40">–</div>
                        <a href="{{ route('lernender.noten.create') }}" class="mt-5 inline-flex items-center gap-2 px-5 h-11 rounded-xl bg-accent text-white text-sm font-semibold np-btn-primary">Erste Note erfassen</a>
                    </div>
                @endif

                @if($lehrzeit)
                    <div class="relative mt-auto pt-5">
                        <div class="flex items-center justify-between text-xs text-muted mb-1.5">
                            <span>Lehrzeit</span>
                            <span class="tabular-nums">{{ $lehrzeit['tage'] > 0 ? 'noch '.$lehrzeit['tage'].' Tage' : 'abgeschlossen' }}</span>
                        </div>
                        <div class="h-1.5 rounded-full bg-bg border border-border overflow-hidden" role="progressbar" aria-valuenow="{{ $lehrzeit['prozent'] }}" aria-valuemin="0" aria-valuemax="100">
                            <div class="h-full rounded-full bg-accent" style="width: {{ $lehrzeit['prozent'] }}%"></div>
                        </div>
                    </div>
                @endif
            </section>

            {{-- Kategorien --}}
            <div class="lg:col-span-7 grid grid-cols-1 sm:grid-cols-2 gap-4 content-start">
                @forelse($kategorien as $kat)
                    <a href="{{ route('lernender.noten.index', ['kategorie_id' => $kat['id']]) }}" class="glass glass-lift rounded-2xl p-4 flex flex-col gap-2">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-sm font-semibold text-text truncate">{{ $kat['name'] }}</span>
                            @if($kat['promotion'])
                                <x-status :status="$kat['promotion']['erfuellt'] ? 'gruen' : 'rot'" :text="$kat['promotion']['erfuellt'] ? 'Promotion ok' : 'Promotion gefährdet'" />
                            @endif
                        </div>
                        <div class="flex items-end justify-between gap-3">
                            <div>
                                <x-note :wert="$kat['note']" variante="hero" :stellen="1" class="text-4xl" />
                                <div class="text-xs text-muted tabular-nums">Semester <x-note :wert="$kat['semester']" :stellen="1" /></div>
                            </div>
                            <x-sparkline :werte="$kat['verlauf']" />
                        </div>
                    </a>
                @empty
                    <div class="sm:col-span-2 glass rounded-2xl p-8 text-center text-sm text-muted">Noch keine Noten</div>
                @endforelse
            </div>

            {{-- Zu tun --}}
            <x-karte titel="Zu tun" class="lg:col-span-5" :polster="false">
                @if($zuTun)
                    <x-slot:aktionen><span class="inline-flex items-center justify-center min-w-6 h-6 px-1.5 rounded-full text-[11px] font-bold bg-accent/15 text-accent">{{ count($zuTun) }}</span></x-slot:aktionen>
                @endif
                <div class="divide-y divide-border/70">
                    @forelse($zuTun as $t)
                        <a href="{{ $t['link'] }}" class="px-5 py-3 flex items-center gap-3 hover:bg-accent/5 transition-colors">
                            <span @class(['w-2 h-2 rounded-full shrink-0', 'bg-red-500' => $t['ton'] === 'rot', 'bg-yellow-500' => $t['ton'] === 'gelb', 'bg-accent' => $t['ton'] === 'accent', 'bg-muted/50' => $t['ton'] === 'neutral']) aria-hidden="true"></span>
                            <span class="flex-1 min-w-0">
                                <span class="block text-sm text-text truncate">{{ $t['text'] }}</span>
                                @if($t['detail'])<span class="block text-xs text-muted">{{ $t['detail'] }}</span>@endif
                            </span>
                            <span class="text-muted" aria-hidden="true">›</span>
                        </a>
                    @empty
                        <div class="px-5 py-8 flex flex-col items-center gap-2 text-sm text-muted">
                            <svg class="w-8 h-8 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            Alles erledigt
                        </div>
                    @endforelse
                </div>
            </x-karte>

            {{-- Ziele --}}
            <x-karte titel="Ziele" class="lg:col-span-7" :link="route('lernender.noten.rechner')" link-text="Rechner">
                <div class="flex flex-col gap-3">
                    @forelse($ziele as $z)
                        @php
                            $l = $z['loesung'];
                            $erreicht = $z['aktuell'] !== null && $z['aktuell'] >= $z['zielwert'] - 1e-9;
                        @endphp
                        <a href="{{ $z['link'] }}" class="rounded-2xl border border-border/70 bg-bg/40 hover:bg-accent/5 px-4 py-3 flex flex-col gap-2 transition-colors">
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-sm font-medium text-text truncate">{{ $z['label'] }}</span>
                                <span class="text-sm tabular-nums shrink-0">
                                    <x-note :wert="$z['aktuell']" :stellen="1" /> <span class="text-muted">/ {{ \App\Support\NotenSkala::format($z['zielwert']) }}</span>
                                </span>
                            </div>
                            <div class="relative h-1.5 rounded-full bg-bg border border-border overflow-hidden">
                                <div class="absolute inset-y-0 left-0 rounded-full {{ \App\Support\NotenSkala::balken($z['aktuell']) }}" style="width: {{ \App\Support\NotenSkala::breite($z['aktuell']) }}%"></div>
                                <div class="absolute inset-y-0 w-0.5 bg-text/60" style="left: {{ \App\Support\NotenSkala::breite($z['zielwert']) }}%"></div>
                            </div>
                            <div class="text-xs">
                                @switch($l['status'])
                                    @case('benoetigt')
                                        <span class="text-muted">Nötig in {{ $l['unbekannte'] === 1 ? 'der offenen Prüfung' : 'den '.$l['unbekannte'].' offenen Prüfungen' }}:</span>
                                        <span class="font-bold tabular-nums {{ \App\Support\NotenSkala::bedarf($l['note']) }}">{{ \App\Support\NotenSkala::format($l['note'], 2) }}</span>
                                        @break
                                    @case('erreicht')
                                        <span class="text-green-700 dark:text-green-400 font-medium">Gesichert</span>
                                        @break
                                    @case('unerreichbar')
                                        <span class="text-red-600 dark:text-red-400 font-medium">Nicht mehr erreichbar · höchstens {{ \App\Support\NotenSkala::format($l['maximum'], 1) }}</span>
                                        @break
                                    @default
                                        <span class="{{ $erreicht ? 'text-green-700 dark:text-green-400' : 'text-muted' }}">{{ $erreicht ? 'Erreicht' : 'Keine offenen Prüfungen geplant' }}</span>
                                @endswitch
                            </div>
                        </a>
                    @empty
                        <div class="py-6 text-center">
                            <a href="{{ route('lernender.noten.rechner') }}" class="inline-flex items-center gap-2 px-4 h-10 rounded-xl glass-btn text-text text-sm">Ziel setzen</a>
                        </div>
                    @endforelse
                </div>
            </x-karte>

            {{-- Verlauf --}}
            @if(count($verlauf['labels']) > 0)
                <x-karte titel="Verlauf" class="lg:col-span-8"
                         x-data="{ modus: 'kategorien', fach: 0, d: {{ \Illuminate\Support\Js::from($verlauf) }} }">
                    <x-slot:aktionen>
                        <div class="flex items-center gap-1 p-0.5 rounded-lg bg-bg/60 border border-border text-xs">
                            <button type="button" @click="modus = 'kategorien'" class="px-2.5 min-h-8 rounded-md" :class="modus === 'kategorien' ? 'bg-card text-accent shadow-sm' : 'text-muted'">Kategorien</button>
                            <button type="button" @click="modus = 'fach'" x-show="d.faecher.length" class="px-2.5 min-h-8 rounded-md" :class="modus === 'fach' ? 'bg-card text-accent shadow-sm' : 'text-muted'">Fach</button>
                        </div>
                        <select x-show="modus === 'fach'" x-model.number="fach" class="rounded-lg border border-border bg-input text-text text-xs py-1.5 pl-2 pr-7" aria-label="Fach">
                            <template x-for="(f, i) in d.faecher" :key="i"><option :value="i" x-text="f.name"></option></template>
                        </select>
                    </x-slot:aktionen>
                    <div class="h-64" x-data="npChart('verlauf')"
                         x-effect="zeichne(modus === 'fach' && d.faecher[fach]
                            ? { labels: d.labels, grenze: d.grenze, serien: [{ name: d.faecher[fach].name, werte: d.faecher[fach].werte, farbe: '--accent', dick: true }] }
                            : { labels: d.labels, grenze: d.grenze, serien: d.serien })">
                        <canvas x-ref="canvas" role="img" aria-label="Notenverlauf je Semester"></canvas>
                    </div>
                </x-karte>
            @endif

            {{-- Nächste Prüfungen --}}
            <x-karte titel="Nächste Prüfungen" :class="count($verlauf['labels']) > 0 ? 'lg:col-span-4' : 'lg:col-span-12'" :link="route('lernender.pruefungen.index')" link-text="Planen" :polster="false">
                <div class="divide-y divide-border/70">
                    @forelse($naechste as $p)
                        @php $tage = (int) now()->startOfDay()->diffInDays($p->datum, false); @endphp
                        <div class="px-5 py-2.5 flex items-center gap-3">
                            <div class="w-10 text-center shrink-0">
                                <div class="text-base font-bold text-text leading-none tabular-nums">{{ $p->datum->format('d') }}</div>
                                <div class="text-[10px] uppercase text-muted">{{ $p->datum->locale('de_CH')->translatedFormat('M') }}</div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-sm text-text truncate">{{ $p->bezeichnung() }}</div>
                                <div class="text-xs {{ $tage <= 3 ? 'text-accent font-medium' : 'text-muted' }}">{{ $tage === 0 ? 'heute' : ($tage === 1 ? 'morgen' : 'in '.$tage.' Tagen') }} · {{ \App\Support\Zahl::prozent($p->gewichtung_prozent) }}</div>
                            </div>
                        </div>
                    @empty
                        <div class="px-5 py-8 text-center text-sm text-muted">Keine geplant</div>
                    @endforelse
                </div>
            </x-karte>

            {{-- Stärken und Schwächen --}}
            @php $anzahlBalken = max(count($balken['semester']['labels']), count($balken['lehrzeit']['labels'])); @endphp
            @if($anzahlBalken > 0)
                <x-karte titel="Wo stehe ich pro Fach und Modul" class="lg:col-span-7"
                         x-data="{ modus: {{ \Illuminate\Support\Js::from(count($balken['semester']['labels']) ? 'semester' : 'lehrzeit') }}, d: {{ \Illuminate\Support\Js::from($balken) }}, g: {{ \Illuminate\Support\Js::from($grenzen) }} }">
                    <x-slot:aktionen>
                        <div class="flex items-center gap-1 p-0.5 rounded-lg bg-bg/60 border border-border text-xs">
                            <button type="button" @click="modus = 'semester'" x-show="d.semester.labels.length" class="px-2.5 min-h-8 rounded-md" :class="modus === 'semester' ? 'bg-card text-accent shadow-sm' : 'text-muted'" x-text="d.semester.name"></button>
                            <button type="button" @click="modus = 'lehrzeit'" class="px-2.5 min-h-8 rounded-md" :class="modus === 'lehrzeit' ? 'bg-card text-accent shadow-sm' : 'text-muted'">Lehrzeit</button>
                        </div>
                    </x-slot:aktionen>
                    <div x-data="npChart('balken')" :style="`height: ${Math.max(120, d[modus].labels.length * 28 + 40)}px`"
                         x-effect="zeichne({ labels: d[modus].labels, werte: d[modus].werte, grenzen: g })">
                        <canvas x-ref="canvas" role="img" aria-label="Zeugnisnoten je Fach und Modul"></canvas>
                    </div>
                </x-karte>
            @endif

            {{-- Letzte Noten --}}
            <x-karte titel="Letzte Noten" :class="$anzahlBalken > 0 ? 'lg:col-span-5' : 'lg:col-span-12'" :link="route('lernender.noten.index')" :polster="false">
                <div class="divide-y divide-border/70">
                    @forelse($letzteNoten as $n)
                        <a href="{{ route('lernender.noten.index', ['_open' => $n->note_id]) }}" class="px-5 py-2.5 flex items-center justify-between gap-3 hover:bg-accent/5 transition-colors">
                            <div class="min-w-0">
                                <div class="text-sm text-text truncate">{{ $n->fach?->name ?? trim(($n->modulBelegung?->modul?->modul_nummer ?? '').' '.($n->modulBelegung?->modul?->titel ?? '')) }}</div>
                                <div class="text-xs text-muted">{{ $n->pruefungsdatum->format('d.m.Y') }}@if($n->titel) · {{ $n->titel }}@endif</div>
                            </div>
                            <x-note :wert="$n->note_wert" variante="badge" />
                        </a>
                    @empty
                        <div class="px-5 py-8 text-center text-sm text-muted">Noch keine Noten</div>
                    @endforelse
                </div>
            </x-karte>
        </div>
    </div>
</x-app-layout>
