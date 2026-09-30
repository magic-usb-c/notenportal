@props(['daten'])
@php
    $skala = \App\Support\NotenSkala::class;
    $zelle = 'inline-flex items-center justify-center w-11 h-7 rounded-md text-xs font-semibold tabular-nums';
    $marke = fn ($wert) => $skala::stufe($wert) === $skala::UNGENUEGEND ? '▼ ' : '';
@endphp
@if($daten['gruppen'])
    {{-- Matrix Fach × Semester: scrollt bewusst quer, die Namensspalte bleibt stehen. Schmal ist sie enger,
         damit neben ihr mehr Semester sichtbar bleiben. --}}
    <div class="@container overflow-x-auto">
        <table class="w-full text-sm border-separate border-spacing-y-0.5">
            <thead>
                <tr class="text-2xs font-medium text-muted">
                    <th class="sticky left-0 bg-card text-left font-medium px-3 py-2 min-w-32 @3xl:px-5 @3xl:min-w-48">{{ __('Fach / Modul') }}</th>
                    @foreach($daten['semester'] as $s)
                        <th class="font-medium px-0.5 py-2 text-center leading-tight @3xl:px-1">{{ $s['name'] }}</th>
                    @endforeach
                    <th class="font-medium px-3 py-2 text-center @3xl:px-5">{{ __('Lehrzeit') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($daten['gruppen'] as $g)
                    <tr>
                        <th class="sticky left-0 bg-card text-left px-3 pt-3 pb-1 text-xs font-semibold text-text @3xl:px-5">{{ $g['name'] }}</th>
                        @foreach($daten['semester'] as $s)
                            <td class="px-0.5 pt-3 pb-1 text-center @3xl:px-1"><span class="text-3xs font-semibold tabular-nums {{ $skala::text($g['semester'][$s['id']] ?? null) }}">{{ $marke($g['semester'][$s['id']] ?? null) }}{{ isset($g['semester'][$s['id']]) ? $skala::format($g['semester'][$s['id']], 1) : '' }}</span></td>
                        @endforeach
                        <td class="px-3 pt-3 pb-1 text-center @3xl:px-5"><span aria-hidden="true">{{ $marke($g['note']) }}</span><x-note :wert="$g['note']" :stellen="1" class="text-sm" /></td>
                    </tr>
                    @foreach($g['zeilen'] as $z)
                        {{-- Die feste Namenszelle braucht deckenden Grund; der Verlauf legt den Zeilen-Hover darüber, damit er mitläuft --}}
                        <tr class="group hover:bg-surface-2/60">
                            <td class="sticky left-0 bg-card group-hover:bg-linear-to-r group-hover:from-surface-2/60 group-hover:to-surface-2/60 px-3 py-0.5 text-text @3xl:px-5">
                                {{-- Kürzen im Block statt in der Zelle, «offen» bleibt stehen. Breite = frühere Zellbreite (144/256 px) ohne Innenabstand --}}
                                <div class="flex max-w-30 items-baseline gap-1 @3xl:max-w-54">
                                    <span class="min-w-0 truncate" title="{{ $z['label'] }}">{{ $z['label'] }}</span>
                                    @if($z['offen'])<span class="shrink-0 text-3xs font-semibold text-accent-text">{{ __('offen') }}</span>@endif
                                </div>
                            </td>
                            @foreach($daten['semester'] as $s)
                                <td class="px-0.5 py-0.5 text-center @3xl:px-1">
                                    @if(array_key_exists($s['id'], $z['zellen']))
                                        <span class="{{ $zelle }} {{ $skala::badge($z['zellen'][$s['id']]) }}" title="{{ $z['label'] }} · {{ $s['name'] }}">{{ $marke($z['zellen'][$s['id']]) }}{{ $skala::format($z['zellen'][$s['id']]) }}</span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="px-3 py-0.5 text-center @3xl:px-5"><span aria-hidden="true">{{ $marke($z['lehrzeit']) }}</span><x-note :wert="$z['lehrzeit']" class="text-sm" /></td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
            <tfoot>
                <tr class="text-xs">
                    <th class="sticky left-0 bg-card text-left px-3 pt-3 pb-2 text-xs font-semibold text-muted @3xl:px-5">{{ __('Semesterschnitt') }}</th>
                    @foreach($daten['semester'] as $s)
                        <td class="px-0.5 pt-3 pb-2 text-center @3xl:px-1"><span aria-hidden="true">{{ $marke($daten['semesterschnitt'][$s['id']] ?? null) }}</span><x-note :wert="$daten['semesterschnitt'][$s['id']] ?? null" :stellen="1" /></td>
                    @endforeach
                    <td class="px-3 pt-3 pb-2 text-center @3xl:px-5"><span aria-hidden="true">{{ $marke($daten['gesamt']) }}</span><x-note :wert="$daten['gesamt']" :stellen="1" class="text-base font-bold" /></td>
                </tr>
            </tfoot>
        </table>
    </div>
@else
    <div class="px-5 py-10 text-center text-sm text-muted">{{ __('Noch keine Noten') }}</div>
@endif
