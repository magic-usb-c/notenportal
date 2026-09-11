<x-app-layout>
    <x-slot name="title">Agenda</x-slot>
    <x-slot name="header">
        <div class="w-full flex flex-wrap items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-text">Agenda</h2>
            <div class="flex items-center gap-2 flex-wrap">
                <div class="inline-flex rounded-lg bg-bg/60 border border-border p-0.5 text-sm" role="radiogroup" aria-label="Ansicht">
                    <a href="{{ route('learner.exams.index', array_filter(['ansicht' => null, 'lektionen' => $zeigeLektionen ? 1 : null])) }}"
                       role="radio" aria-checked="{{ $ansicht === 'liste' ? 'true' : 'false' }}"
                       class="h-8 inline-flex items-center rounded-md px-3 {{ $ansicht === 'liste' ? 'bg-card text-accent shadow-xs' : 'text-muted' }}">Liste</a>
                    <a href="{{ route('learner.exams.index', array_filter(['ansicht' => 'monat', 'monat' => $monat->format('Y-m'), 'lektionen' => $zeigeLektionen ? 1 : null])) }}"
                       role="radio" aria-checked="{{ $ansicht === 'monat' ? 'true' : 'false' }}"
                       class="h-8 inline-flex items-center rounded-md px-3 {{ $ansicht === 'monat' ? 'bg-card text-accent shadow-xs' : 'text-muted' }}">Monat</a>
                </div>
                <a href="{{ route('learner.exams.index') }}?kalender=1" class="inline-flex items-center gap-2 px-4 h-10 rounded-xl glass-btn text-text text-sm whitespace-nowrap">Kalender</a>
                <a href="{{ route('learner.grades.calculator') }}" class="inline-flex items-center gap-2 px-4 h-10 rounded-xl glass-btn text-text text-sm whitespace-nowrap">Was brauche ich?</a>
                <a href="{{ route('learner.exams.index') }}?planen=1" class="inline-flex items-center px-4 h-10 rounded-xl bg-accent text-white text-sm np-btn-primary whitespace-nowrap">Prüfung planen</a>
            </div>
        </div>
    </x-slot>

    <div class="py-6" x-data="{
            init() {
                @if($bearbeiten || request()->has('planen')) this.$dispatch('open-drawer', 'pruefung'); @endif
                @if(request()->has('kalender')) this.$dispatch('open-drawer', 'kalender'); @endif
            },
            tagAusgewaehlt: null,
        }">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col gap-5">

            <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm">
                <span class="inline-flex items-center gap-1.5 text-text"><span class="text-accent" aria-hidden="true">●</span> Prüfungen</span>
                <span class="inline-flex items-center gap-1.5 text-text"><span class="text-accent" aria-hidden="true">◆</span> Erkannt, nicht zugeordnet</span>
                <span class="inline-flex items-center gap-1.5 text-text"><span class="text-muted" aria-hidden="true">◇</span> Schulnetz-Termine</span>
                <a href="{{ route('learner.exams.index', array_filter(['ansicht' => $ansicht === 'monat' ? 'monat' : null, 'monat' => $ansicht === 'monat' ? $monat->format('Y-m') : null, 'lektionen' => $zeigeLektionen ? null : 1])) }}"
                   class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 {{ $zeigeLektionen ? 'bg-accent/10 text-accent' : 'text-muted hover:text-text' }}">
                    <span aria-hidden="true">○</span> Stundenplan{{ $zeigeLektionen ? ' ausblenden' : ' einblenden' }}
                </a>
            </div>

            @if($ansicht === 'liste')
                <div class="flex flex-col gap-5">
                    @if($gruppen['ueberfaellig']->isNotEmpty())
                        <section class="glass rounded-2xl overflow-hidden border-l-4 border-l-yellow-500">
                            <h3 class="px-5 py-3 font-semibold text-text border-b border-border/70">Note fehlt <span class="ml-1 text-sm text-muted tabular-nums">{{ $gruppen['ueberfaellig']->count() }}</span></h3>
                            <div class="divide-y divide-border/70">
                                @foreach($gruppen['ueberfaellig'] as $e)
                                    @include('lernender.agenda._eintrag', ['e' => $e, 'faellig' => true])
                                @endforeach
                            </div>
                        </section>
                    @endif

                    <section class="glass rounded-2xl overflow-hidden">
                        <h3 class="px-5 py-3 font-semibold text-text border-b border-border/70">Diese Woche <span class="ml-1 text-sm text-muted tabular-nums">{{ $gruppen['diese_woche']->count() }}</span></h3>
                        <div class="divide-y divide-border/70">
                            @forelse($gruppen['diese_woche'] as $e)
                                @include('lernender.agenda._eintrag', ['e' => $e, 'faellig' => false])
                            @empty
                                <div class="px-5 py-8 text-center text-sm text-muted">Nichts geplant</div>
                            @endforelse
                        </div>
                    </section>

                    @if($gruppen['naechste_woche']['eintraege']->isNotEmpty())
                        <section class="glass rounded-2xl overflow-hidden">
                            <h3 class="px-5 py-3 font-semibold text-text border-b border-border/70">{{ $gruppen['naechste_woche']['label'] }}</h3>
                            <div class="divide-y divide-border/70">
                                @foreach($gruppen['naechste_woche']['eintraege'] as $e)
                                    @include('lernender.agenda._eintrag', ['e' => $e, 'faellig' => false])
                                @endforeach
                            </div>
                        </section>
                    @endif

                    @if($gruppen['spaeter']->isNotEmpty())
                        <section class="glass rounded-2xl overflow-hidden">
                            <h3 class="px-5 py-3 font-semibold text-text border-b border-border/70">Später <span class="ml-1 text-sm text-muted tabular-nums">{{ $gruppen['spaeter']->count() }}</span></h3>
                            <div class="divide-y divide-border/70">
                                @foreach($gruppen['spaeter'] as $e)
                                    @include('lernender.agenda._eintrag', ['e' => $e, 'faellig' => false])
                                @endforeach
                            </div>
                        </section>
                    @endif
                </div>
            @else
                {{-- Monat: Raster ab sm, kompakte Liste je Tag mit Einträgen auf Mobil --}}
                <div class="flex items-center justify-between">
                    <a href="{{ route('learner.exams.index', ['ansicht' => 'monat', 'monat' => $monat->subMonth()->format('Y-m'), 'lektionen' => $zeigeLektionen ? 1 : null]) }}"
                       class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-muted hover:text-text hover:bg-bg" aria-label="Vorheriger Monat">‹</a>
                    <h3 class="font-semibold text-text">{{ $monat->locale('de_CH')->translatedFormat('F Y') }}</h3>
                    <a href="{{ route('learner.exams.index', ['ansicht' => 'monat', 'monat' => $monat->addMonth()->format('Y-m'), 'lektionen' => $zeigeLektionen ? 1 : null]) }}"
                       class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-muted hover:text-text hover:bg-bg" aria-label="Nächster Monat">›</a>
                </div>

                <div class="hidden sm:grid grid-cols-7 gap-2">
                    @foreach(['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'] as $wt)
                        <div class="text-2xs uppercase tracking-wider text-muted text-center pb-1">{{ $wt }}</div>
                    @endforeach
                    @foreach($monatsraster as $tag)
                        @php
                            $chips = $tag['eintraege']->take(3);
                            $mehr = $tag['eintraege']->count() - $chips->count();
                            $tagJson = $tag['eintraege']->map(fn ($e) => [
                                'titel' => $e['titel'], 'zeit' => $e['zeit'], 'nebentext' => $e['nebentext'], 'art' => $e['art'],
                                'href' => $e['art'] === 'pruefung' ? route('learner.exams.index', ['bearbeiten' => $e['pruefung']->pruefung_id]) : null,
                            ])->values();
                        @endphp
                        <button type="button"
                                @click="tagAusgewaehlt = { datum: '{{ $tag['datum']->locale('de_CH')->translatedFormat('D, d.M.') }}', eintraege: @js($tagJson) }; $dispatch('open-drawer', 'tag')"
                                class="min-h-24 rounded-lg border border-border p-1.5 text-left flex flex-col gap-1 hover:bg-accent/5 transition-colors
                                       {{ $tag['imMonat'] ? 'bg-card' : 'bg-bg/40 text-muted' }}">
                            <span class="text-xs tabular-nums {{ $tag['heute'] ? 'inline-flex items-center justify-center w-5 h-5 rounded-full bg-accent text-white' : 'text-muted' }}">{{ $tag['datum']->format('d') }}</span>
                            @foreach($chips as $c)
                                <span class="text-[11px] truncate leading-tight {{ in_array($c['art'], ['pruefung', 'erkannt'], true) ? 'text-accent' : 'text-muted' }}">
                                    {{ ['pruefung' => '●', 'erkannt' => '◆', 'termin' => '◇', 'lektion' => '○'][$c['art']] }} {{ $c['titel'] }}
                                </span>
                            @endforeach
                            @if($mehr > 0)
                                <span class="text-[11px] text-muted">+{{ $mehr }}</span>
                            @endif
                        </button>
                    @endforeach
                </div>

                {{-- Mobil: kompakte Liste statt Raster --}}
                <div class="sm:hidden flex flex-col gap-5">
                    @foreach($monatsraster as $tag)
                        @continue($tag['eintraege']->isEmpty())
                        <section class="glass rounded-2xl overflow-hidden">
                            <h3 class="px-5 py-3 font-semibold text-text border-b border-border/70">{{ $tag['datum']->locale('de_CH')->translatedFormat('D, d. M') }}</h3>
                            <div class="divide-y divide-border/70">
                                @foreach($tag['eintraege'] as $e)
                                    @include('lernender.agenda._eintrag', ['e' => $e, 'faellig' => $e['ueberfaellig']])
                                @endforeach
                            </div>
                        </section>
                    @endforeach
                    @if(collect($monatsraster)->every(fn ($tag) => $tag['eintraege']->isEmpty()))
                        <p class="glass rounded-2xl px-5 py-8 text-center text-sm text-muted">Nichts geplant im {{ $monat->locale('de_CH')->translatedFormat('F') }}</p>
                    @endif
                </div>
            @endif
        </div>

        <x-drawer name="pruefung" :titel="$bearbeiten ? 'Prüfung bearbeiten' : 'Prüfung planen'">
            @include('lernender.agenda._form')
        </x-drawer>

        <x-drawer name="kalender" titel="Kalender">
            @include('lernender.agenda._kalender')
        </x-drawer>

        <x-drawer name="tag" :titel="'Tag'">
            <template x-if="tagAusgewaehlt">
                <div class="flex flex-col gap-3">
                    <h4 class="font-semibold text-text" x-text="tagAusgewaehlt?.datum"></h4>
                    <template x-if="tagAusgewaehlt && tagAusgewaehlt.eintraege.length === 0">
                        <p class="text-sm text-muted">Nichts geplant.</p>
                    </template>
                    <template x-for="e in (tagAusgewaehlt ? tagAusgewaehlt.eintraege : [])" :key="e.titel + e.zeit">
                        <a :href="e.href ?? '#'" class="flex items-start gap-2 rounded-lg border border-border px-3 py-2.5"
                           :class="e.href ? 'hover:bg-accent/5' : 'pointer-events-none'">
                            <span aria-hidden="true" :class="['pruefung', 'erkannt'].includes(e.art) ? 'text-accent' : 'text-muted'"
                                  x-text="({pruefung: '●', erkannt: '◆', termin: '◇', lektion: '○'})[e.art]"></span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm text-text truncate" x-text="e.titel"></span>
                                <span class="block text-xs text-muted" x-text="(e.zeit ? e.zeit + ' Uhr · ' : '') + e.nebentext"></span>
                            </span>
                        </a>
                    </template>
                </div>
            </template>
        </x-drawer>
    </div>
</x-app-layout>
