@props([
    'titel' => null,
    'frage' => null,
    'fazit' => null, // Kurzfazit für aria-label, z. B. "3 von 20 ungenügend"
    'polster' => true,
    // Mit typ zeichnet die Komponente selbst (npChart): fokussierbare Hülle, Ansage, Tipp, Canvas, Zusammenfassung.
    // Ohne typ steht der Inhalt im Slot wie bisher.
    'typ' => null,
    'daten' => null,
    'optionen' => [],
    'zusammenfassung' => null,
    'hoehe' => 'h-64',
])
<section {{ $attributes->merge(['class' => 'np-karte flex flex-col']) }}>
    @if($titel || $frage)
        <header class="flex min-h-13 flex-col justify-center px-5 pb-1 pt-3">
            @if($titel)<h2 class="text-base font-semibold text-text">{{ $titel }}</h2>@endif
            @if($frage)<p class="mt-0.5 text-xs text-muted">{{ $frage }}</p>@endif
        </header>
    @endif
    <div @if($fazit && ! $typ) role="group" aria-label="{{ $fazit }}" @endif @class(['flex-1 min-w-0', 'px-5 pb-3 pt-2' => $polster])>
        @if($typ)
            <div x-data="npChart(@js($typ), @js($daten), @js($optionen))" tabindex="0" role="group" aria-label="{{ $titel ?? $fazit ?? $zusammenfassung }}" class="rounded-lg">
                <div class="relative {{ $hoehe }}">
                    <canvas x-ref="canvas" role="img" aria-label="{{ $zusammenfassung ?? $fazit ?? $titel }}"></canvas>
                    <div class="np-diagramm-tipp glass-overlay absolute pointer-events-none z-20 px-3 py-2 text-xs rounded-xl text-text whitespace-nowrap transition-opacity duration-100" style="opacity: 0" aria-hidden="true"></div>
                </div>
                <p class="np-diagramm-ansage sr-only" aria-live="polite"></p>
            </div>
            @if($zusammenfassung)
                <p class="mt-3 text-xs text-muted">{{ $zusammenfassung }}</p>
            @endif
        @else
            {{ $slot }}
        @endif
    </div>
    @isset($tabelle)
        <details class="group/tabelle np-details border-t border-border">
            <summary class="flex min-h-9 cursor-pointer list-none items-center gap-1.5 px-5 py-2.5 text-xs font-medium text-muted hover:bg-surface-2/60 hover:text-text">
                <span class="inline-block transition-transform duration-200 group-open/tabelle:rotate-90" aria-hidden="true">▸</span>
                {{ __('Als Tabelle') }}
            </summary>
            <div class="overflow-x-auto px-5 pb-4">
                {{ $tabelle }}
            </div>
        </details>
    @endisset
</section>
