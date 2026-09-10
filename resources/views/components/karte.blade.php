@props([
    'titel' => null,
    'link' => null,
    'linkText' => 'Alle',
    'polster' => true,
])
<section {{ $attributes->merge(['class' => 'glass rounded-2xl overflow-hidden flex flex-col']) }}>
    @if($titel || isset($aktionen))
        <div class="px-5 pt-4 pb-3 flex items-center justify-between gap-3">
            <h3 class="text-sm font-semibold text-text">{{ $titel }}</h3>
            <div class="flex items-center gap-2">
                {{ $aktionen ?? '' }}
                @if($link)
                    <a href="{{ $link }}" class="text-xs text-accent hover:underline whitespace-nowrap">{{ $linkText }}</a>
                @endif
            </div>
        </div>
    @endif
    <div @class(['flex-1', 'px-5 pb-5' => $polster])>
        {{ $slot }}
    </div>
</section>
