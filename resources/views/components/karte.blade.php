@props([
    'titel' => null,
    'link' => null,
    'linkText' => 'Alle',
    'polster' => true,
])
{{-- Inhaltskarte E0: feste Fläche, kein Glas, kein Schatten --}}
<section {{ $attributes->merge(['class' => 'rounded-xl border border-border bg-card overflow-hidden flex flex-col']) }}>
    @if($titel || isset($aktionen))
        <header class="flex min-h-12 items-center justify-between gap-3 px-5 py-2">
            <h2 class="text-sm font-semibold text-text">{{ $titel }}</h2>
            <div class="flex items-center gap-2">
                {{ $aktionen ?? '' }}
                @if($link)
                    <a href="{{ $link }}" class="whitespace-nowrap text-xs text-accent-text underline-offset-2 hover:underline">{{ $linkText }}</a>
                @endif
            </div>
        </header>
    @endif
    <div @class(['flex-1', 'px-5 pb-5' => $polster])>
        {{ $slot }}
    </div>
</section>
