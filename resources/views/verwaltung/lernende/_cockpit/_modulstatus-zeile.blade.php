<div class="px-5 py-2.5">
    <div class="flex items-center justify-between gap-3">
        <span class="truncate text-sm text-text">{{ $ms['label'] }}@if($ms['semester'] && $ms['typ'] !== \App\Services\Auswertung\Element::MODUL)<span class="text-muted"> · {{ $ms['semester'] }}</span>@endif</span>
        @if($ms['bewerteter_anteil_prozent'] !== null)
            <span class="shrink-0 text-xs tabular-nums text-muted">{{ \App\Support\Zahl::prozent($ms['bewerteter_anteil_prozent']) }}</span>
        @endif
    </div>
    @if($ms['bewerteter_anteil_prozent'] !== null)
        <span class="mt-1 block h-1 w-full overflow-hidden rounded-full bg-surface-2" aria-hidden="true">
            <span class="block h-full bg-chart-6" style="width: {{ $ms['bewerteter_anteil_prozent'] }}%"></span>
        </span>
    @endif
    <div class="mt-1 truncate text-xs text-muted">
        @if($ms['naechster_termin'])
            {{ $ms['naechster_termin']['titel'] }} · {{ $ms['naechster_termin']['restdauer'] }}
        @elseif($ms['dauer_seit_beginn'])
            {{ $ms['dauer_seit_beginn'] }}
        @else
            {{ __('Keine Termine') }}
        @endif
    </div>
</div>
