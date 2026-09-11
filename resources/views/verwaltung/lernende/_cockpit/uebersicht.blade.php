{{-- Cockpit Übersicht: links Stand + Zeugnisnoten-Heatmap (Hauptelement), rechts Hinweise/Ziele/Prüfungen/Aktivität. --}}
@php
    $delta = $stand->delta();
    $heute = today();
@endphp
<div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
    <div class="lg:col-span-8 flex flex-col gap-5">
        {{-- Stand --}}
        <section class="rounded-xl border border-border bg-card p-5 flex flex-wrap items-center gap-6">
            <div>
                <div class="text-xs font-medium text-muted">{{ __('Gesamtschnitt') }}</div>
                <x-note :wert="$stand->auswertung->gesamtNote" variante="hero" :stellen="1" class="block text-4xl mt-0.5" />
            </div>
            <x-sparkline :werte="$stand->verlauf" :breite="140" :hoehe="40" :zahl="false" />
            <div class="ml-auto text-right">
                <div class="text-xs font-medium text-muted">{{ $stand->auswertung->konfiguration->semesterName($stand->semesterId) }}</div>
                <div class="flex items-baseline justify-end gap-2">
                    <x-note :wert="$stand->semesterNote" :stellen="1" class="text-xl" />
                    @if($delta !== null && $delta != 0)
                        <span class="text-xs font-semibold {{ $delta > 0 ? 'text-text' : 'text-note-knapp' }}">{{ $delta > 0 ? '▲ +' : '▼ ' }}{{ \App\Support\NotenSkala::format(abs($delta), 1) }}</span>
                    @endif
                </div>
            </div>
            <div class="text-right text-xs text-muted">
                <div>{{ __('Letzte Prüfung') }}</div>
                <div class="text-sm text-text">{{ $stand->letztePruefung?->format('d.m.Y') ?? '–' }}</div>
            </div>
        </section>

        {{-- Zeugnisnoten --}}
        <x-karte :titel="__('Zeugnisnoten')" :polster="false" class="flex-1">
            @if($heatmap['gruppen'])
                <div class="px-5 pb-2 pt-1"><x-noten-legende /></div>
            @endif
            <x-heatmap :daten="$heatmap" />
        </x-karte>
    </div>

    <div class="lg:col-span-4 flex flex-col gap-5">
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

        @if($letzteNoten->isNotEmpty())
            <x-karte :titel="__('Letzte Aktivität')" :polster="false">
                <div class="divide-y divide-border/70">
                    @foreach($letzteNoten as $n)
                        <div class="flex items-center justify-between gap-3 px-5 py-2.5">
                            <div class="min-w-0">
                                <div class="truncate text-sm text-text">{{ $n->fach?->name ?? trim(($n->modulBelegung?->modul?->modul_nummer ?? '').' '.($n->modulBelegung?->modul?->titel ?? '')) }}</div>
                                <div class="text-xs text-muted">{{ $n->pruefungsdatum->format('d.m.Y') }}</div>
                            </div>
                            <x-note :wert="$n->note_wert" variante="badge" />
                        </div>
                    @endforeach
                </div>
            </x-karte>
        @endif
    </div>
</div>
