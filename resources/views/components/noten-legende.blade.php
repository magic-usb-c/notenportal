@props(['art' => 'punkte', 'neutral' => 'bg-chart-1'])
{{-- Legende zu Diagrammen und Heatmaps, damit Farbe nie die einzige Information ist (WCAG 1.4.1).
     Knapp und ungenügend tragen ihre Notenfarbe (NotenSkala::TEXT/BALKEN); genügend und gut stehen in der
     Diagrammfarbe und teilen sich deshalb einen Eintrag, sonst verspräche die Legende Farben, die nirgends vorkommen.
     art="zellen" zeigt Muster wie die Heatmap-Zellen (NotenSkala::badge), sonst Punkte in Balkenfarbe.
     neutral = Klasse des Punkts für genügend/gut; sie muss die Farbe der neutralen Balken daneben tragen
     (Standard bg-chart-1 wie NotenSkala::balken() und das Histogramm, «Wo stehe ich» bg-text/50). --}}
@php
    $namen = \App\Support\NotenSkala::stufenNamen();
    $muster = $art === 'zellen'
        ? [
            'ungenuegend' => 'h-3 w-4 rounded-sm bg-note-ungenuegend/14 ring-1 ring-inset ring-note-ungenuegend/50',
            'knapp' => 'h-3 w-4 rounded-sm bg-note-knapp/14 ring-1 ring-inset ring-note-knapp/50',
            'neutral' => 'h-3 w-4 rounded-sm bg-surface-2 ring-1 ring-inset ring-border-strong/60',
        ]
        : [
            'ungenuegend' => 'size-1.5 rounded-full bg-note-ungenuegend',
            'knapp' => 'size-1.5 rounded-full bg-note-knapp',
            'neutral' => 'size-1.5 rounded-full '.$neutral,
        ];
@endphp
<ul {{ $attributes->class(['flex flex-wrap items-center gap-x-4 gap-y-1 text-2xs text-muted']) }} data-legende="{{ $art }}">
    @foreach(['ungenuegend', 'knapp'] as $stufe)
        <li class="inline-flex items-center gap-1.5">
            <span class="shrink-0 {{ $muster[$stufe] }}" aria-hidden="true"></span>{{ $namen[$stufe] }}
        </li>
    @endforeach
    <li class="inline-flex items-center gap-1.5">
        <span class="shrink-0 {{ $muster['neutral'] }}" aria-hidden="true"></span>{{ $namen['genuegend'] }} / {{ $namen['gut'] }}
    </li>
</ul>
