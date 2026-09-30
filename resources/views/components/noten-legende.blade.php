{{-- Legende zu Diagrammen und Heatmaps, damit Farbe nie die einzige Information ist (WCAG 1.4.1).
     Farbig sind nur knapp und ungenügend (NotenSkala::TEXT/BALKEN); genügend und gut stehen neutral
     und teilen sich deshalb einen Eintrag, sonst verspräche die Legende Farben, die nirgends vorkommen. --}}
@php $namen = \App\Support\NotenSkala::stufenNamen(); @endphp
<ul {{ $attributes->class(['flex flex-wrap items-center gap-x-4 gap-y-1 text-2xs text-muted']) }}>
    @foreach(['ungenuegend', 'knapp'] as $stufe)
        <li class="inline-flex items-center gap-1.5">
            <span class="size-1.5 shrink-0 rounded-full bg-note-{{ $stufe }}" aria-hidden="true"></span>{{ $namen[$stufe] }}
        </li>
    @endforeach
    <li class="inline-flex items-center gap-1.5">
        <span class="size-1.5 shrink-0 rounded-full bg-chart-6" aria-hidden="true"></span>{{ $namen['genuegend'] }} / {{ $namen['gut'] }}
    </li>
</ul>
