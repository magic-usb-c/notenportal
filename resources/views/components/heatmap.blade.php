@props(['daten'])
@php
    $skala = \App\Support\NotenSkala::class;
    $zelle = 'inline-flex items-center justify-center w-11 h-7 rounded-md text-xs font-semibold tabular-nums';
@endphp
@if($daten['gruppen'])
    <div class="overflow-x-auto">
        <table class="w-full text-sm border-separate border-spacing-y-0.5">
            <thead>
                <tr class="text-[11px] uppercase tracking-widest text-muted">
                    <th class="sticky left-0 bg-card text-left font-medium px-5 py-2 min-w-48">Fach / Modul</th>
                    @foreach($daten['semester'] as $s)
                        <th class="font-medium px-1 py-2 text-center whitespace-nowrap">{{ $s['name'] }}</th>
                    @endforeach
                    <th class="font-medium px-5 py-2 text-center">Lehrzeit</th>
                </tr>
            </thead>
            <tbody>
                @foreach($daten['gruppen'] as $g)
                    <tr>
                        <th class="sticky left-0 bg-card text-left px-5 pt-3 pb-1 text-xs font-semibold text-text">{{ $g['name'] }}</th>
                        @foreach($daten['semester'] as $s)
                            <td class="px-1 pt-3 pb-1 text-center"><span class="text-[11px] font-semibold tabular-nums {{ $skala::text($g['semester'][$s['id']] ?? null) }}">{{ isset($g['semester'][$s['id']]) ? $skala::format($g['semester'][$s['id']], 1) : '' }}</span></td>
                        @endforeach
                        <td class="px-5 pt-3 pb-1 text-center"><x-note :wert="$g['note']" :stellen="1" class="text-sm" /></td>
                    </tr>
                    @foreach($g['zeilen'] as $z)
                        <tr class="hover:bg-accent/5">
                            <td class="sticky left-0 bg-card px-5 py-0.5 text-text truncate max-w-64" title="{{ $z['label'] }}">
                                {{ $z['label'] }}
                                @if($z['offen'])<span class="ml-1 text-[10px] text-accent font-semibold">offen</span>@endif
                            </td>
                            @foreach($daten['semester'] as $s)
                                <td class="px-1 py-0.5 text-center">
                                    @if(array_key_exists($s['id'], $z['zellen']))
                                        <span class="{{ $zelle }} {{ $skala::badge($z['zellen'][$s['id']]) }}" title="{{ $z['label'] }} · {{ $s['name'] }}">{{ $skala::format($z['zellen'][$s['id']]) }}</span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="px-5 py-0.5 text-center"><x-note :wert="$z['lehrzeit']" class="text-sm" /></td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
            <tfoot>
                <tr class="text-xs">
                    <th class="sticky left-0 bg-card text-left px-5 pt-3 pb-2 font-semibold text-muted uppercase tracking-widest text-[11px]">Semesterschnitt</th>
                    @foreach($daten['semester'] as $s)
                        <td class="px-1 pt-3 pb-2 text-center"><x-note :wert="$daten['semesterschnitt'][$s['id']] ?? null" :stellen="1" /></td>
                    @endforeach
                    <td class="px-5 pt-3 pb-2 text-center"><x-note :wert="$daten['gesamt']" :stellen="1" class="text-base font-extrabold" /></td>
                </tr>
            </tfoot>
        </table>
    </div>
@else
    <div class="px-5 py-10 text-center text-sm text-muted">Noch keine Noten</div>
@endif
