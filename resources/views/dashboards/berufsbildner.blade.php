<x-app-layout>
    <x-slot name="title">Übersicht</x-slot>
    <x-slot name="header">
        <x-seitenkopf :titel="'Hallo '.auth()->user()->vorname" :untertitel="now()->locale('de_CH')->isoFormat('dddd, D. MMMM YYYY')">
            <x-slot:aktionen>
                <a href="{{ route('trainer.grades.export_all') }}" class="inline-flex h-9 items-center gap-2 rounded-lg glass-btn px-3.5 text-sm font-medium text-text">CSV</a>
                <a href="{{ route('trainer.learners.create') }}" class="inline-flex h-9 items-center gap-2 rounded-lg bg-accent px-3.5 text-sm font-medium text-accent-contrast np-btn-primary">
                    <span class="text-lg leading-none">+</span> Lernende
                </a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col gap-5">

            {{-- Braucht Aufmerksamkeit --}}
            @if(count($aufmerksamkeit))
                <x-karte titel="Braucht Aufmerksamkeit" :polster="false">
                    <div class="divide-y divide-border/70">
                        @foreach($aufmerksamkeit as $eintrag)
                            @php $z = $eintrag['zeile']; @endphp
                            <a href="{{ route('trainer.learners.show', $z->lernender->lernender_id) }}" class="flex items-center gap-3 px-5 py-3 transition-colors duration-100 hover:bg-surface-2/60">
                                <span class="size-1.5 shrink-0 rounded-full {{ $z->stand->status === 'rot' ? 'bg-note-ungenuegend' : 'bg-note-knapp' }}" aria-hidden="true"></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-medium text-text">{{ $z->lernender->benutzer->vorname }} {{ $z->lernender->benutzer->nachname }}</span>
                                    <span class="block truncate text-xs text-muted">{{ implode(' · ', $eintrag['gruende']) }}</span>
                                </span>
                                <span class="text-muted" aria-hidden="true">›</span>
                            </a>
                        @endforeach
                    </div>
                </x-karte>
            @else
                <p class="px-1 text-sm text-muted">Alle {{ $zeilen->count() }} Lernenden im Plan</p>
            @endif

            {{-- Meine Lernenden --}}
            <x-karte titel="Meine Lernenden" :polster="false"
                     x-data="{
                        filter: 'alle',
                        suche: '',
                        zeilen: {{ \Illuminate\Support\Js::from($zeilen->map(fn ($z) => ['status' => $z->stand->status, 'neu' => $z->neu])->values()) }},
                        get zaehlAlle() { return this.zeilen.length },
                        get zaehlKritisch() { return this.zeilen.filter(z => z.status === 'rot').length },
                        get zaehlBeobachten() { return this.zeilen.filter(z => z.status === 'gelb').length },
                        get zaehlNeu() { return this.zeilen.filter(z => z.neu > 0).length },
                     }">
                <x-slot:aktionen>
                    <input type="search" x-model="suche" placeholder="Suchen" aria-label="Lernende suchen"
                           class="h-8 w-28 rounded-lg border border-border-strong/70 bg-input px-3 text-sm text-text placeholder:text-muted focus:border-accent focus:ring-2 focus:ring-ring/30 sm:w-48">
                    <div role="radiogroup" x-radiogroup aria-label="Filter" class="hidden items-center gap-1 rounded-lg bg-surface-2 p-0.5 text-xs sm:inline-flex">
                        <button type="button" role="radio" :aria-checked="filter === 'alle'" @click="filter = 'alle'" class="h-8 whitespace-nowrap rounded-md px-2.5" :class="filter === 'alle' ? 'bg-card text-text shadow-xs' : 'text-muted'" x-text="'Alle ' + zaehlAlle"></button>
                        <button type="button" role="radio" :aria-checked="filter === 'rot'" @click="filter = 'rot'" class="h-8 whitespace-nowrap rounded-md px-2.5" :class="filter === 'rot' ? 'bg-card text-text shadow-xs' : 'text-muted'" x-text="'Kritisch ' + zaehlKritisch"></button>
                        <button type="button" role="radio" :aria-checked="filter === 'gelb'" @click="filter = 'gelb'" class="h-8 whitespace-nowrap rounded-md px-2.5" :class="filter === 'gelb' ? 'bg-card text-text shadow-xs' : 'text-muted'" x-text="'Beobachten ' + zaehlBeobachten"></button>
                        <button type="button" role="radio" :aria-checked="filter === 'neu'" @click="filter = 'neu'" class="h-8 whitespace-nowrap rounded-md px-2.5" :class="filter === 'neu' ? 'bg-card text-text shadow-xs' : 'text-muted'" x-text="'Neue Noten ' + zaehlNeu"></button>
                    </div>
                </x-slot:aktionen>

                @if($zeilen->isEmpty())
                    <p class="px-5 pb-5 text-sm text-muted">Keine aktiv betreuten Lernenden</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm tabular-nums">
                            <thead>
                                <tr class="border-y border-border">
                                    <th class="h-9 bg-surface-2 px-3 text-left text-2xs font-medium text-muted"><span class="sr-only">Status</span></th>
                                    <th class="h-9 bg-surface-2 px-3 text-left text-2xs font-medium text-muted">Lernende</th>
                                    <th class="h-9 bg-surface-2 px-2 text-right text-2xs font-medium text-muted">Lj</th>
                                    <th class="hidden h-9 bg-surface-2 px-3 text-left text-2xs font-medium text-muted md:table-cell">Verlauf</th>
                                    <th class="h-9 bg-surface-2 px-3 text-right text-2xs font-medium text-muted">Semester</th>
                                    <th class="hidden h-9 bg-surface-2 px-3 text-right text-2xs font-medium text-muted sm:table-cell">Gesamt</th>
                                    <th class="h-9 bg-surface-2 px-3 text-right text-2xs font-medium text-muted">Neue Noten</th>
                                    <th class="hidden h-9 bg-surface-2 px-5 text-left text-2xs font-medium text-muted lg:table-cell">Nächste Prüfung</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($zeilen as $z)
                                    @php
                                        $b = $z->lernender->benutzer;
                                        $s = $z->stand;
                                        $d = $s->delta();
                                    @endphp
                                    <tr class="border-b border-border last:border-0 hover:bg-surface-2/60"
                                        x-show="(filter === 'alle' || filter === '{{ $s->status }}' || (filter === 'neu' && {{ $z->neu }} > 0)) && (suche === '' || @js(mb_strtolower($b->vorname.' '.$b->nachname)).includes(suche.toLowerCase()))">
                                        <td class="h-11 px-3"><x-status :status="$s->status" /></td>
                                        <td class="h-11 px-3">
                                            <a href="{{ route('trainer.learners.show', $z->lernender->lernender_id) }}" class="font-medium text-text hover:text-accent-text">{{ $b->vorname }} {{ $b->nachname }}</a>
                                            <div class="truncate text-xs text-muted" title="{{ $z->lernender->lehrberuf?->name }}">{{ $z->lernender->lehrberuf?->kuerzel }}</div>
                                        </td>
                                        <td class="h-11 px-2 text-right">{{ $z->lehrjahr ?? '–' }}</td>
                                        <td class="hidden h-11 px-3 md:table-cell"><x-sparkline :werte="$s->verlauf" :zahl="false" /></td>
                                        <td class="h-11 px-3 text-right whitespace-nowrap">
                                            <x-note :wert="$s->semesterNote" :stellen="1" />
                                            @if($d !== null && $d != 0)
                                                <span class="block text-2xs {{ $d > 0 ? 'text-text' : 'text-note-knapp' }}">{{ $d > 0 ? '▲ +' : '▼ ' }}{{ \App\Support\NotenSkala::format(abs($d), 1) }}</span>
                                            @endif
                                        </td>
                                        <td class="hidden h-11 px-3 text-right sm:table-cell"><x-note :wert="$s->auswertung->gesamtNote" :stellen="1" /></td>
                                        <td class="h-11 px-3 text-right">
                                            <a href="{{ route('trainer.learners.grades.index', $z->lernender->lernender_id) }}" class="{{ $z->neu ? 'font-semibold text-accent-text' : 'text-muted' }} hover:underline underline-offset-2">{{ $z->neu }}</a>
                                        </td>
                                        <td class="hidden h-11 px-5 lg:table-cell">
                                            @if($z->naechstePruefung)
                                                <div class="truncate text-text">{{ $z->naechstePruefung->bezeichnung() }}</div>
                                                <div class="text-xs text-muted">{{ $z->naechstePruefung->datum->format('d.m.Y') }}</div>
                                            @else
                                                <span class="text-muted">–</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-karte>

            <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
                {{-- Nächste 14 Tage --}}
                <x-karte titel="Nächste 14 Tage" class="lg:col-span-8" :polster="false">
                    @if($agenda->isEmpty())
                        <p class="px-5 py-8 text-center text-sm text-muted">Keine geplant</p>
                    @else
                        <div class="divide-y divide-border/70">
                            @foreach($agenda as $tag => $pruefungenAmTag)
                                @php $datum = \Carbon\Carbon::parse($tag); @endphp
                                <div class="px-5 py-3">
                                    <div class="mb-2 text-xs font-medium text-muted">{{ $datum->locale('de_CH')->isoFormat('dddd, D. MMMM') }}</div>
                                    <div class="flex flex-col gap-2">
                                        @foreach($pruefungenAmTag as $p)
                                            <div class="flex items-center gap-3">
                                                <div class="min-w-0 flex-1">
                                                    <div class="truncate text-sm text-text">{{ $p->bezeichnung() }}</div>
                                                    <div class="truncate text-xs text-muted">{{ $p->lernender->benutzer->vorname }} {{ $p->lernender->benutzer->nachname }} · {{ \App\Support\Zahl::prozent($p->gewichtung_prozent) }}</div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-karte>

                {{-- Lehrende bald --}}
                @if($lehrende->isNotEmpty())
                    <x-karte titel="Lehrende bald" class="lg:col-span-4" :polster="false">
                        <div class="divide-y divide-border/70">
                            @foreach($lehrende as $l)
                                <a href="{{ route('trainer.learners.show', $l->lernender_id) }}" class="flex items-center justify-between gap-3 px-5 py-2.5 transition-colors duration-100 hover:bg-surface-2/60">
                                    <span class="text-sm text-text">{{ $l->benutzer->vorname }} {{ $l->benutzer->nachname }}</span>
                                    <span class="text-xs tabular-nums text-muted">{{ $l->lehrende->format('d.m.Y') }} · in {{ (int) now()->startOfDay()->diffInDays($l->lehrende) }} Tagen</span>
                                </a>
                            @endforeach
                        </div>
                    </x-karte>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
