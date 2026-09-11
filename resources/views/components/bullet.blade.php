@props([
    'wert' => null,
    'ziel' => null,
    'grenzen' => null,     // ['gut', 'genuegend', 'kritisch'] aus den Einstellungen
    'label' => 'Gesamtschnitt',
    'skala' => true,       // Grenzwerte unter dem Balken
])
{{--
    Bullet Graph 1–6 (Few): Bänder ungenügend/knapp/genügend/gut in Grautönen, Messbalken neutral
    (knapp/ungenügend in Notenfarbe), Zielmarke als senkrechter Strich. Beschreibung als Satz im aria-label.
--}}
@php
    $skalaKlasse = \App\Support\NotenSkala::class;
    $g = $grenzen ?? $skalaKlasse::grenzen();
    $pos = fn ($v) => round($skalaKlasse::breite($v), 2);
    $f = fn ($v) => $skalaKlasse::format($v, 1);
    $balken = match ($skalaKlasse::stufe($wert)) {
        $skalaKlasse::KNAPP => 'bg-note-knapp',
        $skalaKlasse::UNGENUEGEND => 'bg-note-ungenuegend',
        default => 'bg-text',
    };
    $satz = $label.' '.$f($wert)
        .($ziel !== null ? ', Ziel '.$f($ziel) : '')
        .', genügend ab '.$f($g['genuegend']).', gut ab '.$f($g['gut']);
    $baender = [
        [1, $g['kritisch'], 'bg-muted/40'],
        [$g['kritisch'], $g['genuegend'], 'bg-muted/28'],
        [$g['genuegend'], $g['gut'], 'bg-muted/16'],
        [$g['gut'], 6, 'bg-muted/8'],
    ];
@endphp
<div {{ $attributes->class(['w-full']) }}>
    <div class="relative h-4 overflow-hidden rounded-xs" role="img" aria-label="{{ $satz }}">
        @foreach($baender as [$von, $bis, $klasse])
            <div class="absolute inset-y-0 {{ $klasse }}" style="left: {{ $pos($von) }}%; width: {{ $pos($bis) - $pos($von) }}%"></div>
        @endforeach
        @if($wert !== null)
            <div class="absolute left-0 top-1/2 h-1.5 -translate-y-1/2 {{ $balken }}" style="width: {{ $pos($wert) }}%"></div>
        @endif
        @if($ziel !== null)
            <div class="absolute inset-y-0 w-0.5 -translate-x-1/2 bg-text" style="left: {{ $pos($ziel) }}%"></div>
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
