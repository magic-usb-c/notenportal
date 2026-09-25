@props([
    'titel' => null,
    'link' => null,
    'linkText' => __('Alle'),
    'polster' => true,
])
{{-- Inhaltskarte E0: feste Fläche, kein Glas, kein Schatten --}}
<section {{ $attributes->merge(['class' => 'rounded-xl border border-border bg-card overflow-hidden flex flex-col']) }}>
    @if($titel || isset($aktionen))
        <header class="flex min-h-12 flex-wrap items-center justify-between gap-x-3 gap-y-2 px-5 py-2">
            <h2 class="text-sm font-semibold text-text">{{ $titel }}</h2>
            <div class="flex min-w-0 max-w-full items-center gap-2">
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
