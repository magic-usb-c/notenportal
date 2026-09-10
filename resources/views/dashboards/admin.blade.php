<x-app-layout>
    <x-slot name="title">Übersicht</x-slot>
    <x-slot name="header">
        <div class="w-full flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-text">Betrieb</h2>
                <p class="text-sm text-muted">{{ now()->locale('de_CH')->isoFormat('dddd, D. MMMM YYYY') }}</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('admin.benutzer.create') }}" class="inline-flex items-center gap-2 px-4 h-10 rounded-xl glass-btn text-text text-sm">Benutzer anlegen</a>
                <a href="{{ route('admin.lernende.create') }}" class="inline-flex items-center gap-2 px-4 h-10 rounded-xl bg-accent text-white text-sm font-semibold np-btn-primary">
                    <span class="text-lg leading-none">+</span> Lernende
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-12 gap-5">

            <div class="lg:col-span-12 grid grid-cols-2 md:grid-cols-5 gap-4">
                <x-kachel label="Lernende" :wert="$kennzahlen['lernende']" :href="route('admin.lernende.index')" />
                <x-kachel label="Berufsbildner" :wert="$kennzahlen['berufsbildner']" :href="route('admin.berufsbildner.index')" />
                <x-kachel label="Noten {{ $kennzahlen['semester'] }}" :wert="$kennzahlen['noten_semester']" :href="route('admin.berichte.noten')" />
                <x-kachel label="Kritisch" :wert="$kennzahlen['rot']" :ton="$kennzahlen['rot'] ? 'rot' : 'neutral'" :sub="$kennzahlen['gelb'].' beobachten'" />
                <x-kachel label="Offene Meldungen" :wert="$kennzahlen['feedback']" :ton="$kennzahlen['feedback'] ? 'accent' : 'neutral'" :href="route('admin.feedback.index')" />
            </div>

            @if($einrichtung)
                <section class="lg:col-span-12 glass rounded-2xl overflow-hidden border-l-4 border-l-yellow-500">
                    <h3 class="px-5 pt-4 pb-2 text-sm font-semibold text-text">Einrichtung unvollständig</h3>
                    <div class="divide-y divide-border/70">
                        @foreach($einrichtung as $e)
                            <a href="{{ $e['link'] }}" class="px-5 py-2.5 flex items-center justify-between gap-3 hover:bg-accent/5 transition-colors">
                                <span class="text-sm text-text">{{ $e['text'] }}</span>
                                <span class="flex items-center gap-3">
                                    @if($e['anzahl'] > 1 || ! str_starts_with($e['text'], 'Semester'))
                                        <span class="px-2 py-0.5 rounded-full text-[11px] font-bold bg-yellow-500/15 text-yellow-800 dark:text-yellow-300 tabular-nums">{{ $e['anzahl'] }}</span>
                                    @endif
                                    <span class="text-xs text-accent">Beheben ›</span>
                                </span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- Berufsbildner --}}
            <x-karte titel="Berufsbildner" class="lg:col-span-7" :polster="false" :link="route('admin.berufsbildner.index')">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-[11px] uppercase tracking-widest text-muted">
                            <tr class="border-y border-border/70">
                                <th class="text-left font-medium px-5 py-2">Name</th>
                                <th class="text-right font-medium px-3 py-2">Lernende</th>
                                <th class="text-right font-medium px-3 py-2">Kritisch</th>
                                <th class="text-right font-medium px-3 py-2">Beobachten</th>
                                <th class="text-right font-medium px-5 py-2">Ungesehen</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/60">
                            @forelse($proBb as $bb)
                                <tr>
                                    <td class="px-5 py-2.5 text-text">{{ $bb->name }}</td>
                                    <td class="px-3 py-2.5 text-right tabular-nums">{{ $bb->lernende }}</td>
                                    <td class="px-3 py-2.5 text-right tabular-nums {{ $bb->rot ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-muted' }}">{{ $bb->rot }}</td>
                                    <td class="px-3 py-2.5 text-right tabular-nums {{ $bb->gelb ? 'text-yellow-700 dark:text-yellow-400 font-semibold' : 'text-muted' }}">{{ $bb->gelb }}</td>
                                    <td class="px-5 py-2.5 text-right tabular-nums {{ $bb->neu > 20 ? 'text-accent font-semibold' : 'text-muted' }}">{{ $bb->neu }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-5 py-8 text-center text-muted">Noch keine Berufsbildner</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-karte>

            <x-karte titel="Erfasste Noten pro Woche" class="lg:col-span-5">
                <div class="h-52" x-data="npChart('saeulen', {{ \Illuminate\Support\Js::from(['labels' => $aktivitaet['labels'], 'werte' => $aktivitaet['werte'], 'name' => 'Noten']) }})">
                    <canvas x-ref="canvas" role="img" aria-label="Erfasste Noten pro Woche"></canvas>
                </div>
            </x-karte>

            @if($jahrgaenge['serien'])
                <x-karte titel="Gesamtschnitt nach Lehrjahr" class="lg:col-span-7">
                    <div class="h-64" x-data="npChart('gruppen', {{ \Illuminate\Support\Js::from($jahrgaenge) }})">
                        <canvas x-ref="canvas" role="img" aria-label="Gesamtschnitt je Lehrberuf und Lehrjahr"></canvas>
                    </div>
                </x-karte>
            @endif

            <x-karte titel="Kritisch" :class="$jahrgaenge['serien'] ? 'lg:col-span-5' : 'lg:col-span-12'" :polster="false">
                <div class="divide-y divide-border/70">
                    @forelse($kritisch as $k)
                        <a href="{{ route('admin.lernende.show', $k->lernender->lernender_id) }}" class="px-5 py-2.5 flex items-center justify-between gap-3 hover:bg-accent/5 transition-colors">
                            <div class="min-w-0">
                                <div class="text-sm text-text truncate">{{ $k->lernender->benutzer->vorname }} {{ $k->lernender->benutzer->nachname }}</div>
                                <div class="text-xs text-muted truncate">{{ implode(' · ', array_slice($k->stand->gruende, 0, 2)) }}</div>
                            </div>
                            <x-note :wert="$k->stand->semesterNote" variante="badge" />
                        </a>
                    @empty
                        <div class="px-5 py-8 text-center text-sm text-muted">Niemand kritisch</div>
                    @endforelse
                </div>
            </x-karte>

            @if($lehrende->isNotEmpty())
                <x-karte titel="Lehrende bald" class="lg:col-span-12" :polster="false">
                    <div class="divide-y divide-border/70">
                        @foreach($lehrende as $l)
                            <a href="{{ route('admin.lernende.show', $l->lernender_id) }}" class="px-5 py-2.5 flex items-center justify-between gap-3 hover:bg-accent/5 transition-colors">
                                <span class="text-sm text-text">{{ $l->benutzer->vorname }} {{ $l->benutzer->nachname }} <span class="text-muted">· {{ $l->lehrberuf?->kuerzel }}</span></span>
                                <span class="text-xs text-muted tabular-nums">{{ $l->lehrende->format('d.m.Y') }}</span>
                            </a>
                        @endforeach
                    </div>
                </x-karte>
            @endif
        </div>
    </div>
</x-app-layout>
