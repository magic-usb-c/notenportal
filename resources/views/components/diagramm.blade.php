@props([
    'titel' => null,
    'frage' => null,
    'fazit' => null, // Kurzfazit für aria-label, z. B. "3 von 20 ungenügend"
    'polster' => true,
])
<section {{ $attributes->merge(['class' => 'np-karte flex flex-col']) }}>
    @if($titel || $frage)
        <header class="px-5 pt-4 pb-1">
            @if($titel)<h3 class="text-sm font-semibold text-text">{{ $titel }}</h3>@endif
            @if($frage)<p class="mt-0.5 text-xs text-muted">{{ $frage }}</p>@endif
        </header>
    @endif
    <div @if($fazit) role="group" aria-label="{{ $fazit }}" @endif @class(['flex-1 min-w-0', 'px-5 pb-3 pt-2' => $polster])>
        {{ $slot }}
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
