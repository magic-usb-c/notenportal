@props([
    'titel' => null,
    'symbol' => null,      // Symbol vor dem Titel in Akzentfarbe (App\Support\Symbole), wie die Kategorien in Health
    'link' => null,
    'linkText' => __('Alle'),
    'polster' => true,
])
{{-- Inhaltskarte: feste Fläche ohne Rand, der Grund trennt sie (np-karte). Kopf mit Titel (Title 3, halbfett),
     optional Symbol, Aktionen und Link «Alle ›» wie die Zusammenfassungskarten unter macOS. --}}
<section {{ $attributes->merge(['class' => 'np-karte flex flex-col overflow-hidden']) }}>
    @if($titel || isset($aktionen))
        <header class="flex min-h-13 flex-wrap items-center justify-between gap-x-3 gap-y-2 px-5 pb-1 pt-3">
            <h2 class="flex min-w-0 items-center gap-2 text-base font-semibold text-text">
                @if($symbol)<x-symbol :name="$symbol" strich="1.75" class="size-4.5 text-accent-text" />@endif
                <span class="min-w-0 truncate">{{ $titel }}</span>
            </h2>
            <div class="flex min-w-0 max-w-full items-center gap-2">
                {{ $aktionen ?? '' }}
                @if($link)
                    <a href="{{ $link }}" class="-mr-2 inline-flex h-7 items-center gap-0.5 whitespace-nowrap rounded-full pl-2.5 pr-1.5 text-sm text-accent-text transition-colors duration-100 hover:bg-accent/10">{{ $linkText }}<x-symbol name="chevron-right" strich="2" class="size-3.5" /></a>
                @endif
            </div>
        </header>
    @endif
    <div @class(['flex-1', 'px-5 pb-5 pt-2' => $polster])>
        {{ $slot }}
    </div>
</section>
