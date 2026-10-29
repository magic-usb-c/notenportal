@props([
    'paket',                      // Antwort der Seite (StatistikAntwort::paket)
    'schluessel' => 'zeitleiste', // Teil des Pakets
    'id' => 'np-zeitleiste',      // Pflicht für den Ersatz nach einer Filteränderung (npFilter)
    'titel' => null,              // null = Titel aus meta
])
{{--
    Zeitleiste: kommende Prüfungen auf einer Datumsachse von heute bis zum Ende des Zeitraums, darunter je Prüfung
    Datum, Name und die nötige Note als Text («benötigt 4.5», «bestanden», «nicht erreichbar»). Die Farbe unterstützt nur:
    blau für eine nötige Note, Notenfarbe für nicht erreichbar, grau für bestanden. Serverseitig gezeichneter Baustein
    (npFilter ersetzt ihn nach einer Filteränderung).
--}}
@php
    $skala = \App\Support\NotenSkala::class;
    $d = $paket['diagramm'][$schluessel];
    $tabelle = $paket['tabelle'][$schluessel];
    $satz = $paket['zusammenfassung'][$schluessel];
    $titel ??= $paket['meta'][$schluessel]['titel'] ?? null;
    $zeilen = $d['zeilen'];
    $von = \Illuminate\Support\Carbon::parse($d['von']);
    $bis = \Illuminate\Support\Carbon::parse($d['bis']);
    $spanne = max(1, $von->diffInDays($bis));
    $breite = 600;
    $rand = 8;
    $x = fn (string $datum) => round($rand + min(1, max(0, $von->diffInDays(\Illuminate\Support\Carbon::parse($datum)) / $spanne)) * ($breite - 2 * $rand), 2);
    $klasse = fn (string $status) => match ($status) {
        \App\Services\Auswertung\Zielrechner::UNERREICHBAR => 'fill-note-ungenuegend',
        \App\Services\Auswertung\Zielrechner::BENOETIGT => 'fill-chart-1',
        default => 'fill-muted',
    };
@endphp
<x-diagramm :titel="$titel" {{ $attributes->merge(['id' => $id, 'data-np-baustein' => $schluessel]) }}>
    @if($zeilen === [])
        <p class="py-6 text-center text-sm text-muted">{{ $satz }}</p>
    @else
        <svg class="w-full" viewBox="0 0 {{ $breite }} 16" role="group" aria-label="{{ $titel }}: {{ $satz }}">
            <line x1="{{ $rand }}" x2="{{ $breite - $rand }}" y1="8" y2="8" class="stroke-border" stroke-width="1.5" stroke-linecap="round"/>
            <line x1="{{ $rand }}" x2="{{ $rand }}" y1="2" y2="14" class="stroke-muted" stroke-width="1.5" stroke-linecap="round"/>
            @foreach($zeilen as $z)
                @if($z['datum'] !== null)
                    <circle cx="{{ $x($z['datum']) }}" cy="8" r="5" class="{{ $klasse($z['status']) }}">
                        <title>{{ \Illuminate\Support\Carbon::parse($z['datum'])->format('d.m.Y') }}, {{ $z['titel'] }}, {{ $z['text'] }}</title>
                    </circle>
                @endif
            @endforeach
        </svg>
        <div class="mt-1 flex justify-between text-2xs tabular-nums text-muted" aria-hidden="true">
            <span>{{ __('Heute') }}</span>
            <span>{{ $bis->format('d.m.Y') }}</span>
        </div>
        <ol class="mt-3 flex flex-col divide-y divide-border" role="list">
            @foreach($zeilen as $z)
                <li class="flex items-baseline gap-3 py-2 text-sm">
                    <span class="w-24 shrink-0 tabular-nums text-muted">{{ $z['datum'] !== null ? \Illuminate\Support\Carbon::parse($z['datum'])->format('d.m.Y') : '–' }}</span>
                    <span class="min-w-0 flex-1 truncate text-text" title="{{ $z['titel'] }}">{{ $z['titel'] }}</span>
                    <span @class(['shrink-0 tabular-nums', 'text-note-ungenuegend' => $z['status'] === \App\Services\Auswertung\Zielrechner::UNERREICHBAR, 'text-text' => $z['status'] !== \App\Services\Auswertung\Zielrechner::UNERREICHBAR])>{{ $z['text'] }}</span>
                </li>
            @endforeach
        </ol>
        <p class="mt-3 text-xs text-muted">{{ $satz }}</p>
    @endif
    <p class="sr-only" aria-live="polite" data-np-ansage>{{ $satz }}</p>
    <x-slot:tabelle>
        <table class="np-tabelle text-sm">
            <thead>
                <tr>
                    @foreach($tabelle['spalten'] as $spalte)
                        <th scope="col">{{ $spalte }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($tabelle['zeilen'] as $zeile)
                    <tr>
                        @foreach($zeile as $zelle)
                            <td>{{ $zelle }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-slot:tabelle>
</x-diagramm>
