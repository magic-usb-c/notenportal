@props([
    'titel',
    'untertitel' => null,   // Metazeile unter dem Titel
    'zaehler' => null,      // Anzahl neben dem Titel, z. B. Treffer
    'schmal' => false,      // Lese-/Formularseite: Kopf in derselben Spalte wie der Inhalt (np-spalte)
    'zurueck' => null,      // URL der übergeordneten Seite: Zurück-Knopf vorne in der Symbolleiste
])
{{--
    Grosser Titel der Seite (HIG «Toolbars»: large title). Titel, Zurück und Aktionen gehen zusätzlich als Stack
    an die Symbolleiste (layouts/navigation): der Titel erscheint dort klein, sobald der grosse beim Scrollen
    unter ihr verschwindet; die Aktionen (Slot «aktionen», höchstens eine Primäraktion) stehen hinten in der
    Leiste und bleiben so immer erreichbar. Default-Slot: Bedienelement direkt neben dem Titel.
    Die Slots werden vor dem Layout gerendert, deshalb kommen die Stacks rechtzeitig an.
--}}
@push('np-titel'){{ $titel }}@endpush
@if($zurueck)
    @push('np-zurueck')
        <a href="{{ $zurueck }}" class="np-knopf np-knopf-symbol" aria-label="{{ __('Zurück') }}" title="{{ __('Zurück') }}"><x-symbol name="chevron-left" strich="2" /></a>
    @endpush
@endif
@isset($aktionen)
    @push('np-aktionen'){{ $aktionen }}@endpush
@endisset
<div {{ $attributes->class(['flex flex-wrap items-end justify-between gap-x-4 gap-y-3', 'np-spalte' => $schmal]) }}>
    {{-- Bedienelement neben dem Titel auf dessen Zeile, die Metazeile darunter über die ganze Breite --}}
    <div class="min-w-0">
        <div class="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-2">
            <h1 data-np-titel class="flex min-w-0 items-baseline gap-2.5 text-2xl font-bold text-text">
                <span class="min-w-0 break-words">{{ $titel }}</span>
                @if($zaehler !== null)
                    <span class="whitespace-nowrap text-lg font-normal tabular-nums text-muted">{{ $zaehler }}</span>
                @endif
            </h1>
            {{ $slot }}
        </div>
        @if($untertitel)
            <p class="mt-1 text-sm text-muted">{{ $untertitel }}</p>
        @endif
    </div>
</div>
