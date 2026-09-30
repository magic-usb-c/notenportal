@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Seitennavigation') }}" class="flex flex-wrap items-center justify-between gap-3 text-sm">

        <p class="text-xs text-muted tabular-nums">
            @if ($paginator->firstItem())
                {{ __(':von–:bis von :gesamt', ['von' => $paginator->firstItem(), 'bis' => $paginator->lastItem(), 'gesamt' => $paginator->total()]) }}
            @else
                {{ __(':anzahl Einträge', ['anzahl' => $paginator->count()]) }}
            @endif
        </p>

        <div class="flex items-center gap-0.5 tabular-nums">
            @if ($paginator->onFirstPage())
                <span class="np-knopf np-knopf-symbol np-knopf-klein" aria-disabled="true" aria-hidden="true"><x-symbol name="chevron-left" strich="2" /></span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="np-knopf np-knopf-symbol np-knopf-klein np-ziel" aria-label="{{ __('Vorherige Seite') }}"><x-symbol name="chevron-left" strich="2" /></a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="inline-flex h-7 min-w-7 items-center justify-center text-xs text-muted" aria-hidden="true">{{ $element }}</span>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page" class="np-knopf np-knopf-sekundaer np-knopf-klein np-knopf-rund font-semibold">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" aria-label="{{ __('Seite :seite', ['seite' => $page]) }}"
                               class="np-knopf np-knopf-symbol np-knopf-klein np-ziel">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="np-knopf np-knopf-symbol np-knopf-klein np-ziel" aria-label="{{ __('Nächste Seite') }}"><x-symbol name="chevron-right" strich="2" /></a>
            @else
                <span class="np-knopf np-knopf-symbol np-knopf-klein" aria-disabled="true" aria-hidden="true"><x-symbol name="chevron-right" strich="2" /></span>
            @endif
        </div>
    </nav>
@endif
