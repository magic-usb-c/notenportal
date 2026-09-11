@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Navigation" class="flex gap-2 items-center justify-between text-sm">

        @if ($paginator->onFirstPage())
            <span class="inline-flex items-center px-3 py-1.5 rounded-xl border border-border text-muted cursor-not-allowed">
                {{ __('Zurück') }}
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev"
               class="inline-flex items-center px-3 py-1.5 rounded-xl border border-border text-text hover:bg-bg transition-colors">
                {{ __('Zurück') }}
            </a>
        @endif

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next"
               class="inline-flex items-center px-3 py-1.5 rounded-xl border border-border text-text hover:bg-bg transition-colors">
                {{ __('Weiter') }}
            </a>
        @else
            <span class="inline-flex items-center px-3 py-1.5 rounded-xl border border-border text-muted cursor-not-allowed">
                {{ __('Weiter') }}
            </span>
        @endif

    </nav>
@endif
