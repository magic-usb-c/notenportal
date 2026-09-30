@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Seitennavigation') }}" class="flex items-center justify-end gap-0.5">
        @if ($paginator->onFirstPage())
            <span class="np-knopf np-knopf-symbol np-knopf-klein" aria-disabled="true" aria-hidden="true"><x-symbol name="chevron-left" strich="2" /></span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="np-knopf np-knopf-symbol np-knopf-klein np-ziel" aria-label="{{ __('Vorherige Seite') }}"><x-symbol name="chevron-left" strich="2" /></a>
        @endif

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="np-knopf np-knopf-symbol np-knopf-klein np-ziel" aria-label="{{ __('Nächste Seite') }}"><x-symbol name="chevron-right" strich="2" /></a>
        @else
            <span class="np-knopf np-knopf-symbol np-knopf-klein" aria-disabled="true" aria-hidden="true"><x-symbol name="chevron-right" strich="2" /></span>
        @endif
    </nav>
@endif
