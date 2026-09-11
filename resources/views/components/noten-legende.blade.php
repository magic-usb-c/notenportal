{{-- Legende der vier Notenstufen (ungenügend/knapp/genügend/gut), damit Farbe in Diagrammen und
     Heatmaps nie die einzige Information ist (WCAG 1.4.1). Grenzen kommen aus den Einstellungen. --}}
<ul {{ $attributes->class(['flex flex-wrap items-center gap-x-4 gap-y-1 text-2xs text-muted']) }}>
    @foreach(\App\Support\NotenSkala::stufenNamen() as $stufe => $name)
        <li class="inline-flex items-center gap-1.5">
            <span class="size-1.5 shrink-0 rounded-full bg-note-{{ $stufe }}" aria-hidden="true"></span>{{ $name }}
        </li>
    @endforeach
</ul>
