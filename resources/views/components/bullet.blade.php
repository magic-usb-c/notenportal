@props([
    'wert' => null,
    'ziel' => null,
    'grenzen' => null,     // ['gut', 'genuegend', 'kritisch'] aus den Einstellungen
    'label' => __('Gesamtschnitt'),
    'skala' => true,       // Grenzwerte unter dem Balken
])
{{--
    Bullet Graph 1–6 (Few) als Kapsel: Spur mit den Bändern ungenügend/knapp/genügend/gut in Grautönen, Messbalken
    in der Diagrammfarbe (knapp/ungenügend in Notenfarbe), Zielmarke als Strich über die Spur hinaus.
    Beschreibung als Satz im aria-label.
--}}
@php
    $skalaKlasse = \App\Support\NotenSkala::class;
    $g = $grenzen ?? $skalaKlasse::grenzen();
    $pos = fn ($v) => round($skalaKlasse::breite($v), 2);
    $f = fn ($v) => $skalaKlasse::format($v, 1);
    $balken = match ($skalaKlasse::stufe($wert)) {
        $skalaKlasse::KNAPP => 'bg-note-knapp',
        $skalaKlasse::UNGENUEGEND => 'bg-note-ungenuegend',
        default => 'bg-chart-1',
    };
    $satz = $label.' '.$f($wert)
        .($ziel !== null ? ', '.__('Ziel :wert', ['wert' => $f($ziel)]) : '')
        .', '.__('genügend ab :wert', ['wert' => $f($g['genuegend'])]).', '.__('gut ab :wert', ['wert' => $f($g['gut'])]);
    $baender = [
        [1, $g['kritisch'], 'bg-muted/40'],
        [$g['kritisch'], $g['genuegend'], 'bg-muted/28'],
        [$g['genuegend'], $g['gut'], 'bg-muted/18'],
        [$g['gut'], 6, 'bg-muted/10'],
    ];
@endphp
<div {{ $attributes->class(['w-full']) }}>
    <div class="relative py-1" role="img" aria-label="{{ $satz }}">
        {{-- Balken schmaler als die Spur, damit die Bänder (ungenügend/knapp/genügend/gut) sichtbar bleiben --}}
        <div class="relative h-3 overflow-hidden rounded-full">
            @foreach($baender as [$von, $bis, $klasse])
                <div class="absolute inset-y-0 {{ $klasse }}" style="left: {{ $pos($von) }}%; width: {{ $pos($bis) - $pos($von) }}%"></div>
            @endforeach
            @if($wert !== null)
                <div class="absolute inset-y-1 left-0 rounded-full {{ $balken }}" style="width: {{ $pos($wert) }}%"></div>
            @endif
        </div>
        @if($ziel !== null)
            <div class="absolute inset-y-0 w-0.5 -translate-x-1/2 rounded-full bg-text" style="left: {{ $pos($ziel) }}%"></div>
        @endif
    </div>
    @if($skala)
        <div class="relative mt-1 h-4 text-2xs tabular-nums text-muted" aria-hidden="true">
            <span class="absolute left-0">1</span>
            @foreach([$g['kritisch'], $g['genuegend'], $g['gut']] as $grenze)
                <span class="absolute -translate-x-1/2" style="left: {{ $pos($grenze) }}%">{{ $f($grenze) }}</span>
            @endforeach
            <span class="absolute right-0">6</span>
        </div>
    @endif
</div>
