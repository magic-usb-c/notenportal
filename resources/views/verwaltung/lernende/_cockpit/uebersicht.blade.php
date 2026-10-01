{{-- Cockpit Übersicht: links Stand + Zeugnisnoten-Heatmap (Hauptelement), rechts Hinweise/Ziele/Prüfungen/Aktivität. --}}
@php
    $delta = $stand->delta();
    $heute = today();
@endphp
<div class="grid grid-cols-12 items-start gap-5">
    <div class="col-span-8 flex flex-col gap-5">
        {{-- Stand als Kennzahlenleiste: vier gleich gewichtete Werte mit Bezeichnung und Erläuterung darunter --}}
        @php
            $qv = \App\Services\Auswertung\Notenbaum\Abschluss::hauptergebnis($stand->auswertung);
            $naechste = $pruefungen->first(fn ($p) => $p->datum->gte($heute));
        @endphp
        <section class="np-karte grid grid-cols-4 divide-x divide-border" aria-label="{{ __('Stand') }}">
            <div class="flex flex-col gap-1 px-5 py-4">
                @if($qv)
                    <a href="{{ route($bereich.'.learners.qualification', $stand->auswertung->lernenderId) }}" class="inline-flex min-h-6 items-center self-start text-xs font-medium text-accent-text underline-offset-2 hover:underline">{{ $qv->wurzel()->vollstaendig ? __('QV-Gesamtnote') : __('QV-Prognose') }}</a>
                @else
                    <div class="flex min-h-6 items-center text-xs font-medium text-muted">{{ __('Gesamtschnitt') }}</div>
                @endif
                <x-note :wert="$stand->auswertung->gesamtNote" variante="hero" :stellen="1" class="text-3xl leading-none" />
                @if($qv && ! $qv->wurzel()->vollstaendig)
                    <div class="text-xs tabular-nums text-muted">{{ __(':anteil % erfasst', ['anteil' => (int) round($qv->erfasst() * 100)]) }}</div>
                @endif
            </div>
            <div class="flex flex-col gap-1 px-5 py-4">
                <div class="flex min-h-6 items-center text-xs font-medium text-muted"><x-semester :id="$stand->semesterId" :lernender="$stand->auswertung->lernenderId" /></div>
                <x-note :wert="$stand->semesterNote" variante="hero" :stellen="1" class="text-3xl leading-none" />
                @if($delta !== null && $delta != 0)
                    <div @class(['text-xs', 'text-muted' => $delta > 0, 'font-medium text-note-knapp' => $delta < 0])>
                        <span aria-hidden="true">{{ $delta > 0 ? '▲' : '▼' }}</span> {{ __(':delta zum Vorsemester', ['delta' => ($delta > 0 ? '+' : '−').\App\Support\NotenSkala::format(abs($delta), 1)]) }}
                    </div>
                @endif
            </div>
            <div class="flex min-w-0 flex-col gap-1 px-5 py-4">
                <div class="flex min-h-6 items-center text-xs font-medium text-muted">{{ __('Nächste Prüfung') }}</div>
                <div class="text-3xl font-semibold leading-none text-text">{{ $naechste ? \App\Support\Format::date($naechste->datum, 'tag_monat') : '–' }}</div>
                @if($naechste)
                    <div class="truncate text-xs text-muted">{{ $naechste->bezeichnung() }} · {{ \App\Support\Format::wann($naechste->datum) }}</div>
                @endif
            </div>
            <div class="flex flex-col gap-1 px-5 py-4">
                <div class="flex min-h-6 items-center text-xs font-medium text-muted">{{ __('Letzte Prüfung') }}</div>
                <div class="text-3xl font-semibold leading-none text-text">{{ $stand->letztePruefung ? \App\Support\Format::date($stand->letztePruefung, 'tag_monat') : '–' }}</div>
                @if($stand->letztePruefung)
                    <div class="text-xs text-muted">{{ \App\Support\Format::wann($stand->letztePruefung) }}</div>
                @endif
            </div>
        </section>

        {{-- Zeugnisnoten --}}
        <x-karte :titel="__('Zeugnisnoten')" :polster="false">
            @if($heatmap['gruppen'])
                <div class="px-5 pb-2 pt-1"><x-noten-legende art="zellen" /></div>
            @endif
            <x-heatmap :daten="$heatmap" />
        </x-karte>
    </div>

    <div class="col-span-4 flex flex-col gap-5">
        @if($stand->gruende)
            <x-karte :titel="__('Hinweise')">
                <div class="flex flex-col gap-2">
                    @foreach($stand->gruende as $g)
                        <div class="flex items-center gap-2 text-sm">
                            <span class="size-1.5 shrink-0 rounded-full {{ $stand->status === 'rot' ? 'bg-note-ungenuegend' : 'bg-note-knapp' }}" aria-hidden="true"></span>
                            <span class="text-text">{{ $g }}</span>
                        </div>
                    @endforeach
                </div>
            </x-karte>
        @endif

        @if($ziele)
            <x-karte :titel="__('Ziele')">
                <div class="flex flex-col gap-2">
                    @foreach($ziele as $z)
                        <a href="{{ $z['link'] }}" class="flex items-center justify-between gap-3 rounded-lg border border-border/70 px-3 py-2 text-sm transition-colors duration-100 hover:bg-surface-2/60">
                            <span class="truncate text-text">{{ $z['label'] }}</span>
                            <span class="shrink-0 tabular-nums"><x-note :wert="$z['aktuell']" :stellen="1" /> <span class="text-muted">/ {{ \App\Support\NotenSkala::format($z['zielwert']) }}</span></span>
                        </a>
                    @endforeach
                </div>
            </x-karte>
        @endif

        @if($pruefungen->isNotEmpty())
            <x-karte :titel="__('Geplante Prüfungen')" :polster="false">
                <div class="divide-y divide-border/70">
                    @foreach($pruefungen as $p)
                        @php $vorbei = $p->datum->lt($heute); @endphp
                        <div class="px-5 py-2.5">
                            <div class="truncate text-sm text-text">{{ $p->bezeichnung() }}</div>
                            <div class="text-xs {{ $vorbei ? 'font-medium text-note-knapp' : 'text-muted' }}">{{ $p->datum->format('d.m.Y') }}{{ $vorbei ? ' · '.__('Note fehlt') : '' }} · {{ \App\Support\Zahl::prozent($p->gewichtung_prozent) }}</div>
                        </div>
                    @endforeach
                </div>
            </x-karte>
        @endif

        @if($modulstatus->isNotEmpty())
            @php
                [$modulstatusFertig, $modulstatusOffen] = $modulstatus->partition(fn ($ms) => ($ms['bewerteter_anteil_prozent'] ?? 0) >= 100);
            @endphp
            <x-karte :titel="__('Modulstatus')" :polster="false">
                <div class="divide-y divide-border/70">
                    @foreach($modulstatusOffen as $ms)
                        @include('verwaltung.lernende._cockpit._modulstatus-zeile')
                    @endforeach
                </div>
                @if($modulstatusFertig->isNotEmpty())
                    <details class="group/tabelle np-details border-t border-border/70">
                        <summary class="flex min-h-11 cursor-pointer list-none items-center gap-1.5 px-5 text-xs font-medium text-muted hover:text-text">
                            <span class="inline-block transition-transform duration-200 group-open/tabelle:rotate-90" aria-hidden="true">▸</span>
                            {{ __('Vollständig bewertet') }} <span class="tabular-nums">{{ $modulstatusFertig->count() }}</span>
                        </summary>
                        <div class="divide-y divide-border/70 border-t border-border/70">
                            @foreach($modulstatusFertig as $ms)
                                @include('verwaltung.lernende._cockpit._modulstatus-zeile')
                            @endforeach
                        </div>
                    </details>
                @endif
            </x-karte>
        @endif

        @if($letzteNoten->isNotEmpty())
            <x-karte :titel="__('Letzte Aktivität')" :polster="false">
                <div class="divide-y divide-border/70">
                    @foreach($letzteNoten as $n)
                        <div class="flex items-center justify-between gap-3 px-5 py-2.5">
                            <div class="min-w-0">
                                <div class="truncate text-sm text-text">{{ $n->fach?->name ?? trim(($n->modulBelegung?->modul?->modul_nummer ?? '').' '.($n->modulBelegung?->modul?->titel ?? '')) }}</div>
                                <div class="text-xs text-muted">{{ $n->pruefungsdatum->format('d.m.Y') }}</div>
                            </div>
                            <x-note :wert="$n->note_wert" :stufe="$n->note_stufe" variante="badge" />
                        </div>
                    @endforeach
                </div>
            </x-karte>
        @endif
    </div>
</div>
