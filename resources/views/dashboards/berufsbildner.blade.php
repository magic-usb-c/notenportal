<x-app-layout>
    <x-slot name="title">Übersicht</x-slot>
    <x-slot name="header">
        <div class="w-full flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-text">Hallo {{ auth()->user()->vorname }}</h2>
                <p class="text-sm text-muted">{{ now()->locale('de_CH')->isoFormat('dddd, D. MMMM YYYY') }}</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('trainer.grades.export_all') }}" class="inline-flex items-center gap-2 px-4 h-10 rounded-xl glass-btn text-text text-sm">CSV</a>
                <a href="{{ route('trainer.learners.create') }}" class="inline-flex items-center gap-2 px-4 h-10 rounded-xl bg-accent text-white text-sm font-semibold np-btn-primary">
                    <span class="text-lg leading-none">+</span> Lernende
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-12 gap-5"
             x-data="{ filter: 'alle', suche: '' }">

            <div class="lg:col-span-12 grid grid-cols-2 md:grid-cols-4 gap-4">
                <x-kachel label="Lernende" :wert="$kennzahlen['lernende']" :href="route('trainer.learners.index')" />
                <x-kachel label="Neue Noten" :wert="$kennzahlen['neu']" :ton="$kennzahlen['neu'] ? 'accent' : 'neutral'" @click.prevent="filter = 'neu'" href="#klasse" />
                <x-kachel label="Kritisch" :wert="$kennzahlen['rot']" :ton="$kennzahlen['rot'] ? 'rot' : 'neutral'" @click.prevent="filter = 'rot'" href="#klasse" />
                <x-kachel label="Beobachten" :wert="$kennzahlen['gelb']" :ton="$kennzahlen['gelb'] ? 'gelb' : 'neutral'" @click.prevent="filter = 'gelb'" href="#klasse" />
            </div>

            {{-- Klassenübersicht --}}
            <x-karte titel="Meine Lernenden" class="lg:col-span-12" :polster="false" id="klasse">
                <x-slot:aktionen>
                    <input type="search" x-model="suche" placeholder="Suchen" aria-label="Lernende suchen"
                           class="w-36 sm:w-48 rounded-lg border border-border bg-input text-text text-sm py-1.5 px-3 focus:ring-2 focus:ring-ring">
                    <div class="hidden sm:flex items-center gap-1 p-0.5 rounded-lg bg-bg/60 border border-border text-xs">
                        @foreach(['alle' => 'Alle', 'rot' => 'Kritisch', 'gelb' => 'Beobachten', 'neu' => 'Neue Noten'] as $wert => $name)
                            <button type="button" @click="filter = '{{ $wert }}'" class="px-2.5 min-h-8 rounded-md whitespace-nowrap" :class="filter === '{{ $wert }}' ? 'bg-card text-accent shadow-sm' : 'text-muted'">{{ $name }}</button>
                        @endforeach
                    </div>
                </x-slot:aktionen>

                @if($zeilen->isEmpty())
                    <div class="px-5 pb-10 pt-4 text-center text-sm text-muted">Keine aktiv betreuten Lernenden</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="text-[11px] uppercase tracking-widest text-muted">
                                <tr class="border-y border-border/70">
                                    <th class="text-left font-medium px-5 py-2">Lernende</th>
                                    <th class="text-left font-medium px-3 py-2 hidden md:table-cell">Verlauf</th>
                                    <th class="text-right font-medium px-3 py-2">Semester</th>
                                    <th class="text-right font-medium px-3 py-2 hidden sm:table-cell">Gesamt</th>
                                    <th class="text-left font-medium px-3 py-2 hidden lg:table-cell">Hinweise</th>
                                    <th class="px-5 py-2"><span class="sr-only">Aktionen</span></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border/60">
                                @foreach($zeilen as $z)
                                    @php
                                        $b = $z->lernender->benutzer;
                                        $s = $z->stand;
                                        $d = $s->delta();
                                    @endphp
                                    <tr class="hover:bg-accent/5 transition-colors"
                                        x-show="(filter === 'alle' || filter === '{{ $s->status }}' || (filter === 'neu' && {{ $z->neu }} > 0)) && (suche === '' || @js(mb_strtolower($b->vorname.' '.$b->nachname)).includes(suche.toLowerCase()))">
                                        <td class="px-5 py-3">
                                            <div class="flex items-center gap-3">
                                                <span @class(['w-2.5 h-2.5 rounded-full shrink-0', 'bg-red-500' => $s->status === 'rot', 'bg-yellow-500' => $s->status === 'gelb', 'bg-green-500' => $s->status === 'gruen'])
                                                      title="{{ ['rot' => 'kritisch', 'gelb' => 'beobachten', 'gruen' => 'im Plan'][$s->status] }}"></span>
                                                <div class="min-w-0">
                                                    <a href="{{ route('trainer.learners.show', $z->lernender->lernender_id) }}" class="font-semibold text-text hover:text-accent">{{ $b->vorname }} {{ $b->nachname }}</a>
                                                    <div class="text-xs text-muted truncate">
                                                        {{ $z->lernender->lehrberuf?->kuerzel }}@if($z->lehrjahr) · {{ $z->lehrjahr }}. Lehrjahr @endif @if($z->lernender->klasse_schule) · {{ $z->lernender->klasse_schule }} @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-3 py-3 hidden md:table-cell"><x-sparkline :werte="$s->verlauf" /></td>
                                        <td class="px-3 py-3 text-right whitespace-nowrap">
                                            <x-note :wert="$s->semesterNote" :stellen="1" class="text-base" />
                                            @if($d !== null && $d != 0)
                                                <span class="block text-[11px] {{ $d > 0 ? 'text-green-700 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">{{ $d > 0 ? '▲ +' : '▼ ' }}{{ \App\Support\NotenSkala::format($d, 1) }}</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-3 text-right hidden sm:table-cell"><x-note :wert="$s->auswertung->gesamtNote" :stellen="1" /></td>
                                        <td class="px-3 py-3 hidden lg:table-cell">
                                            <div class="flex flex-wrap gap-1.5 max-w-md">
                                                @foreach(array_slice($s->gruende, 0, 3) as $g)
                                                    <span class="px-2 py-0.5 rounded-md text-[11px] {{ $s->status === 'rot' ? 'bg-red-500/10 text-red-700 dark:text-red-300' : 'bg-yellow-500/10 text-yellow-800 dark:text-yellow-300' }}">{{ $g }}</span>
                                                @endforeach
                                                @if(count($s->gruende) > 3)<span class="text-[11px] text-muted">+{{ count($s->gruende) - 3 }}</span>@endif
                                            </div>
                                        </td>
                                        <td class="px-5 py-3">
                                            <div class="flex items-center justify-end gap-1">
                                                <a href="{{ route('trainer.learners.grades.index', $z->lernender->lernender_id) }}"
                                                   class="inline-flex items-center gap-1.5 px-3 min-h-9 rounded-lg text-sm {{ $z->neu ? 'bg-accent text-white np-btn-primary' : 'text-accent hover:bg-accent/10' }}">
                                                    Noten @if($z->neu)<span class="text-[11px] font-bold">{{ $z->neu }}</span>@endif
                                                </a>
                                                <a href="{{ route('trainer.learners.calculator', $z->lernender->lernender_id) }}" class="hidden sm:inline-flex items-center justify-center w-9 h-9 rounded-lg text-muted hover:text-text hover:bg-bg" aria-label="Rechner" title="Rechner">
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m-6 4h6m-6 4h4m5 4H5a2 2 0 01-2-2V5a2 2 0 012-2h10l4 4v11a2 2 0 01-2 2z"/></svg>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-karte>

            {{-- Wo es kippt --}}
            <x-karte titel="Wo es kippt" class="lg:col-span-5" :polster="false">
                <div class="divide-y divide-border/70">
                    @forelse($brennpunkte as $p)
                        <a href="{{ route('trainer.learners.grades.index', $p['zeile']->lernender->lernender_id) }}" class="px-5 py-2.5 flex items-center justify-between gap-3 hover:bg-accent/5 transition-colors">
                            <div class="min-w-0">
                                <div class="text-sm text-text truncate">{{ $p['label'] }}</div>
                                <div class="text-xs text-muted truncate">{{ $p['zeile']->lernender->benutzer->vorname }} {{ $p['zeile']->lernender->benutzer->nachname }}</div>
                            </div>
                            <div class="shrink-0 tabular-nums text-sm">
                                @if($p['vorher'] !== null)<span class="text-muted">{{ \App\Support\NotenSkala::format($p['vorher']) }} →</span>@endif
                                <x-note :wert="$p['note']" variante="badge" />
                            </div>
                        </a>
                    @empty
                        <div class="px-5 py-8 text-center text-sm text-muted">Nichts auffällig</div>
                    @endforelse
                </div>
            </x-karte>

            {{-- Vergleich: Verlauf der Betreuten als Small Multiples, gleiche Skala 1–6 --}}
            @if($zeilen->isNotEmpty())
                <x-diagramm titel="Verlauf im Vergleich" frage="Wer weicht vom eigenen Verlauf ab?" class="lg:col-span-7"
                            :fazit="$kennzahlen['rot'].' von '.$kennzahlen['lernende'].' Lernenden kritisch, Skala 1 bis 6, genügend ab '.\App\Support\NotenSkala::format($grenzen['genuegend'], 1)">
                    <x-slot:tabelle>
                        <table class="w-full text-sm tabular-nums">
                            <thead class="text-2xs text-muted">
                                <tr><th class="text-left px-3 py-2 font-medium">Lernende</th><th class="text-left px-3 py-2 font-medium">Verlauf je Semester</th><th class="text-right px-3 py-2 font-medium">Aktuell</th></tr>
                            </thead>
                            <tbody>
                                @foreach($zeilen as $z)
                                    @php $verlaufWerte = collect($z->stand->verlauf)->filter(fn ($v) => $v !== null); @endphp
                                    <tr class="border-t border-border">
                                        <td class="px-3 py-2">{{ $z->lernender->benutzer->vorname }} {{ $z->lernender->benutzer->nachname }}</td>
                                        <td class="px-3 py-2">{{ $verlaufWerte->isEmpty() ? '–' : $verlaufWerte->map(fn ($v) => \App\Support\NotenSkala::format($v, 1))->implode(' · ') }}</td>
                                        <td class="px-3 py-2 text-right">{{ \App\Support\NotenSkala::format($z->stand->semesterNote, 1) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </x-slot:tabelle>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                        @foreach($zeilen as $z)
                            @php $verlaufWerte = collect($z->stand->verlauf)->filter(fn ($v) => $v !== null); @endphp
                            <div class="rounded-lg border border-border/70 p-2">
                                <div class="mb-1 flex items-center justify-between gap-2">
                                    <span class="truncate text-xs font-medium text-text">{{ $z->lernender->benutzer->vorname }} {{ mb_substr($z->lernender->benutzer->nachname, 0, 1) }}.</span>
                                    <x-note :wert="$z->stand->semesterNote" :stellen="1" variante="badge" class="shrink-0" />
                                </div>
                                @if($verlaufWerte->isEmpty())
                                    <p class="flex h-10 items-center text-2xs text-muted">Keine Zeugnisnoten</p>
                                @else
                                    <div class="h-10" x-data="npChart('spark', {{ \Illuminate\Support\Js::from(['werte' => $z->stand->verlauf, 'grenzen' => $grenzen]) }})">
                                        <canvas x-ref="canvas" role="img" aria-label="Verlauf {{ $z->lernender->benutzer->vorname }} {{ $z->lernender->benutzer->nachname }}: {{ $verlaufWerte->map(fn ($v) => \App\Support\NotenSkala::format($v, 1))->implode(', ') }}"></canvas>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </x-diagramm>
            @endif

            {{-- Prüfungen --}}
            <x-karte titel="Prüfungen der nächsten 14 Tage" class="lg:col-span-6" :polster="false">
                <div class="divide-y divide-border/70">
                    @forelse($pruefungen as $p)
                        <div class="px-5 py-2.5 flex items-center gap-3">
                            <div class="w-10 text-center shrink-0">
                                <div class="text-base font-bold text-text leading-none tabular-nums">{{ $p->datum->format('d') }}</div>
                                <div class="text-[10px] uppercase text-muted">{{ $p->datum->locale('de_CH')->translatedFormat('M') }}</div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="text-sm text-text truncate">{{ $p->bezeichnung() }}</div>
                                <div class="text-xs text-muted truncate">{{ $p->lernender->benutzer->vorname }} {{ $p->lernender->benutzer->nachname }} · {{ \App\Support\Zahl::prozent($p->gewichtung_prozent) }}</div>
                            </div>
                        </div>
                    @empty
                        <div class="px-5 py-8 text-center text-sm text-muted">Keine geplant</div>
                    @endforelse
                </div>
            </x-karte>

            <x-karte titel="Lehrende bald" class="lg:col-span-6" :polster="false">
                <div class="divide-y divide-border/70">
                    @forelse($lehrende as $l)
                        <a href="{{ route('trainer.learners.show', $l->lernender_id) }}" class="px-5 py-2.5 flex items-center justify-between gap-3 hover:bg-accent/5 transition-colors">
                            <span class="text-sm text-text">{{ $l->benutzer->vorname }} {{ $l->benutzer->nachname }}</span>
                            <span class="text-xs text-muted tabular-nums">{{ $l->lehrende->format('d.m.Y') }} · in {{ (int) now()->startOfDay()->diffInDays($l->lehrende) }} Tagen</span>
                        </a>
                    @empty
                        <div class="px-5 py-8 text-center text-sm text-muted">Keine</div>
                    @endforelse
                </div>
            </x-karte>
        </div>
    </div>
</x-app-layout>
