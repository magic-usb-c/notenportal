@props([
    'titel',
    'untertitel' => null,   // Metazeile unter dem Titel
    'zaehler' => null,      // Anzahl neben dem Titel, z. B. Treffer
    'schmal' => false,      // Lese-/Formularseite: Kopf so breit wie der Inhalt (max-w-3xl)
])
{{--
    Seitenkopf im gemeinsamen Container (layouts/app): Titel links, rechts höchstens eine
    Primär- und zwei Sekundäraktionen (Slot «aktionen»). Mobil brechen die Aktionen unter den Titel.
    Default-Slot: Bedienelement direkt neben dem Titel (z. B. Semesterwechsler).
--}}
<div {{ $attributes->class(['flex flex-wrap items-end justify-between gap-x-4 gap-y-3', 'max-w-3xl' => $schmal]) }}>
    <div class="flex min-w-0 flex-wrap items-center gap-x-3 gap-y-2">
        <div class="min-w-0">
            <h1 class="flex items-baseline gap-2 text-xl font-semibold text-text">
                <span class="min-w-0 break-words">{{ $titel }}</span>
                @if($zaehler !== null)
                    <span class="text-base font-normal tabular-nums text-muted">{{ $zaehler }}</span>
                @endif
            </h1>
            @if($untertitel)
                <p class="mt-0.5 text-sm text-muted">{{ $untertitel }}</p>
            @endif
        </div>
        {{ $slot }}
    </div>
    @isset($aktionen)
        <div class="flex flex-wrap items-center gap-2">
            {{ $aktionen }}
        </div>
    @endisset
</div>
