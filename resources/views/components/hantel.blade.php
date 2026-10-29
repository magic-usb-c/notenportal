@props([
    'paket',                      // Antwort der Seite (StatistikAntwort::paket)
    'schluessel' => 'hantel',     // Teil des Pakets
    'id' => 'np-hantel',          // Pflicht für den Ersatz nach einer Filteränderung (npFilter)
    'titel' => null,              // null = Titel aus meta
])
{{--
    Hantel (Dumbbell Plot): je Zeile ein Ring für das Vorsemester, ein Punkt für jetzt (knapp und ungenügend in
    Notenfarbe), dazwischen Strich und Pfeil, rechts die Veränderung als Text. Achse 1–6, Haarlinie bei genügend.
    Sie ist ein serverseitig gezeichneter Baustein: npFilter ersetzt ihn nach einer Filteränderung durch die neue
    Antwort der Seite. Die Tabelle steht darunter in «Als Tabelle».
--}}
@php
    $skala = \App\Support\NotenSkala::class;
    $d = $paket['diagramm'][$schluessel];
    $tabelle = $paket['tabelle'][$schluessel];
    $satz = $paket['zusammenfassung'][$schluessel];
    $titel ??= $paket['meta'][$schluessel]['titel'] ?? null;
    $zeilen = $d['zeilen'];
    $pos = fn ($v) => round($skala::breite($v), 2);
    $delta = function (?float $v) use ($skala): string {
        if ($v === null) {
            return '–';
        }

        return ($v > 0.004 ? '+' : ($v < -0.004 ? '−' : '±')).$skala::format(abs($v), 1);
    };
@endphp
<x-diagramm :titel="$titel" {{ $attributes->merge(['id' => $id, 'data-np-baustein' => $schluessel]) }}>
    @if($zeilen === [])
        <p class="py-6 text-center text-sm text-muted">{{ $satz }}</p>
    @else
        <div class="mb-2 flex flex-wrap items-center gap-4 text-xs text-muted" aria-hidden="true">
            <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-full border-2 border-muted bg-card"></span>{{ $d['vorsemester'] ?? __('Vorsemester') }}</span>
            <span class="inline-flex items-center gap-1.5"><span class="size-2.5 rounded-full bg-chart-1"></span>{{ $d['semester'] ?? __('Dieses Semester') }}</span>
        </div>
        <ul class="flex flex-col" role="list">
            @foreach($zeilen as $z)
                @php
                    $von = $z['vorher'];
                    $bis = $z['jetzt'];
                    $beide = $von !== null && $bis !== null;
                    $links = $beide ? min($pos($von), $pos($bis)) : null;
                    $breite = $beide ? abs($pos($bis) - $pos($von)) : 0;
                @endphp
                <li class="flex items-center gap-3 py-1">
                    <span class="w-40 shrink-0 truncate text-sm text-text" title="{{ $z['fach'] }}">{{ $z['fach'] }}</span>
                    <div class="relative h-6 flex-1" aria-hidden="true">
                        <div class="absolute left-0 top-1/2 w-full -translate-y-1/2 border-t border-border"></div>
                        <div class="absolute inset-y-0 w-px bg-muted/40" style="left: {{ $pos($d['grenze']) }}%"></div>
                        @if($beide && $breite > 0)
                            <div class="absolute top-1/2 h-1 -translate-y-1/2 rounded-full bg-muted/50" style="left: {{ $links }}%; width: {{ $breite }}%"></div>
                            @if($breite >= 8)
                                <svg class="absolute top-1/2 size-3 -translate-x-1/2 -translate-y-1/2 text-muted {{ $bis < $von ? 'rotate-180' : '' }}" style="left: {{ round(($pos($von) + $pos($bis)) / 2, 2) }}%" viewBox="0 0 12 12">
                                    <path d="M3 2 L9 6 L3 10 Z" fill="currentColor"/>
                                </svg>
                            @endif
                        @endif
                        @if($von !== null)
                            <span class="absolute top-1/2 size-2.5 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-muted bg-card" style="left: {{ $pos($von) }}%"></span>
                        @endif
                        @if($bis !== null)
                            <span class="absolute top-1/2 size-3 -translate-x-1/2 -translate-y-1/2 rounded-full {{ $skala::balken($bis) }}" style="left: {{ $pos($bis) }}%"></span>
                        @endif
                    </div>
                    <span class="w-14 shrink-0 text-right text-sm tabular-nums text-text">
                        <span aria-hidden="true">{{ $delta($z['delta']) }}</span>
                        <span class="sr-only">{{ $beide ? __(':von auf :bis', ['von' => $skala::format($von, 1), 'bis' => $skala::format($bis, 1)]).', '.$delta($z['delta']) : ($bis !== null ? $skala::format($bis, 1) : __('keine Noten')) }}</span>
                    </span>
                </li>
            @endforeach
            <li class="flex items-center gap-3" aria-hidden="true">
                <span class="w-40 shrink-0"></span>
                <div class="relative h-4 flex-1 text-2xs tabular-nums text-muted">
                    @foreach([1, 2, 3, 4, 5, 6] as $note)
                        <span class="absolute -translate-x-1/2" style="left: {{ $pos($note) }}%">{{ $note }}</span>
                    @endforeach
                </div>
                <span class="w-14 shrink-0"></span>
            </li>
        </ul>
        <p class="mt-3 text-xs text-muted">{{ $satz }}</p>
    @endif
    <p class="sr-only" aria-live="polite" data-np-ansage>{{ $satz }}</p>
    <x-slot:tabelle>
        <table class="np-tabelle text-sm">
            <thead>
                <tr>
                    @foreach($tabelle['spalten'] as $i => $spalte)
                        <th scope="col" @class(['text-right' => $i > 0])>{{ $spalte }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($tabelle['zeilen'] as $zeile)
                    <tr>
                        @foreach($zeile as $i => $zelle)
                            <td @class(['text-right' => $i > 0])>{{ $zelle }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-slot:tabelle>
</x-diagramm>
