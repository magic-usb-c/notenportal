@props(['daten'])
@php
    $skala = \App\Support\NotenSkala::class;
    $zelle = 'inline-flex items-center justify-center w-11 h-7 rounded-md text-xs font-semibold tabular-nums';
    $marke = fn ($wert) => $skala::stufe($wert) === $skala::UNGENUEGEND ? '▼ ' : '';
@endphp
@if($daten['gruppen'])
    {{-- Matrix Fach × Semester: die Namensspalte bleibt stehen, falls die Semester einmal nicht in die Breite passen. --}}
    <div class="overflow-x-auto">
        <table class="w-full text-sm border-separate border-spacing-y-0.5">
            <thead>
                <tr class="text-2xs font-medium text-muted">
                    <th scope="col" class="sticky left-0 min-w-48 bg-card px-5 py-2 text-left font-medium">{{ __('Fach / Modul') }}</th>
                    @foreach($daten['semester'] as $s)
                        <th scope="col" class="px-1 py-2 text-center font-medium leading-tight">{{ $s['name'] }}</th>
                    @endforeach
                    <th scope="col" class="px-5 py-2 text-center font-medium">{{ __('Lehrzeit') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($daten['gruppen'] as $g)
                    <tr>
                        <th scope="row" class="sticky left-0 bg-card px-5 pb-1 pt-3 text-left text-xs font-semibold text-text">{{ $g['name'] }}</th>
                        @foreach($daten['semester'] as $s)
                            <td class="px-1 pb-1 pt-3 text-center"><span class="text-3xs font-semibold tabular-nums {{ $skala::text($g['semester'][$s['id']] ?? null) }}">{{ $marke($g['semester'][$s['id']] ?? null) }}{{ isset($g['semester'][$s['id']]) ? $skala::format($g['semester'][$s['id']], 1) : '' }}</span></td>
                        @endforeach
                        <td class="px-5 pb-1 pt-3 text-center"><span aria-hidden="true">{{ $marke($g['note']) }}</span><x-note :wert="$g['note']" :stellen="1" class="text-sm" /></td>
                    </tr>
                    @foreach($g['zeilen'] as $z)
                        {{-- Die feste Namenszelle braucht deckenden Grund; der Verlauf legt den Zeilen-Hover darüber, damit er mitläuft --}}
                        <tr class="group hover:bg-surface-2/60">
                            <th scope="row" class="sticky left-0 bg-card px-5 py-0.5 text-left font-normal text-text group-hover:bg-linear-to-r group-hover:from-surface-2/60 group-hover:to-surface-2/60">
                                {{-- Kürzen im Block statt in der Zelle, «offen» bleibt stehen --}}
                                <div class="flex max-w-54 items-baseline gap-1">
                                    <span class="min-w-0 truncate" title="{{ $z['label'] }}">{{ $z['label'] }}</span>
                                    @if($z['offen'])<span class="shrink-0 text-3xs font-semibold text-accent-text">{{ __('offen') }}</span>@endif
                                </div>
                            </th>
                            @foreach($daten['semester'] as $s)
                                <td class="px-1 py-0.5 text-center">
                                    @if(array_key_exists($s['id'], $z['zellen']))
                                        <span class="{{ $zelle }} {{ $skala::badge($z['zellen'][$s['id']]) }}" title="{{ $z['label'] }} · {{ $s['name'] }}">{{ $marke($z['zellen'][$s['id']]) }}{{ $skala::format($z['zellen'][$s['id']]) }}</span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="px-5 py-0.5 text-center"><span aria-hidden="true">{{ $marke($z['lehrzeit']) }}</span><x-note :wert="$z['lehrzeit']" class="text-sm" /></td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
            <tfoot>
                <tr class="text-xs">
                    <th scope="row" class="sticky left-0 bg-card px-5 pb-2 pt-3 text-left text-xs font-semibold text-muted">{{ __('Semesterschnitt') }}</th>
                    @foreach($daten['semester'] as $s)
                        <td class="px-1 pb-2 pt-3 text-center"><span aria-hidden="true">{{ $marke($daten['semesterschnitt'][$s['id']] ?? null) }}</span><x-note :wert="$daten['semesterschnitt'][$s['id']] ?? null" :stellen="1" /></td>
                    @endforeach
                    <td class="px-5 pb-2 pt-3 text-center"><span aria-hidden="true">{{ $marke($daten['gesamt']) }}</span><x-note :wert="$daten['gesamt']" :stellen="1" class="text-base font-semibold" /></td>
                </tr>
            </tfoot>
        </table>
    </div>
@else
    <div class="px-5 py-10 text-center text-sm text-muted">{{ __('Noch keine Noten') }}</div>
@endif
