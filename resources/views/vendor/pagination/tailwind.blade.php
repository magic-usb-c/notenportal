@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Navigation" class="flex items-center justify-between gap-3 flex-wrap text-sm">

        {{-- Ergebnisinfo --}}
        <div class="text-muted">
            @if ($paginator->firstItem())
                {{ __(':von–:bis von :gesamt', ['von' => $paginator->firstItem(), 'bis' => $paginator->lastItem(), 'gesamt' => $paginator->total()]) }}
            @else
                {{ __(':anzahl Einträge', ['anzahl' => $paginator->count()]) }}
            @endif
        </div>

        {{-- Seiten-Links --}}
        <div class="flex items-center gap-1">

            {{-- Zurück --}}
            @if ($paginator->onFirstPage())
                <span class="inline-flex items-center px-3 py-1.5 rounded-xl border border-border text-muted cursor-not-allowed">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                    </svg>
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev"
                   class="inline-flex items-center px-3 py-1.5 rounded-xl border border-border text-text hover:bg-bg transition-colors">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                    </svg>
                </a>
            @endif

            {{-- Seiten --}}
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="px-3 py-1.5 text-muted">{{ $element }}</span>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="inline-flex items-center px-3 py-1.5 rounded-xl bg-accent text-accent-contrast font-semibold cursor-default">
                                {{ $page }}
                            </span>
                        @else
                            <a href="{{ $url }}"
                               class="inline-flex items-center px-3 py-1.5 rounded-xl border border-border text-text hover:bg-bg transition-colors">
                                {{ $page }}
                            </a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            {{-- Weiter --}}
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next"
                   class="inline-flex items-center px-3 py-1.5 rounded-xl border border-border text-text hover:bg-bg transition-colors">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                    </svg>
                </a>
            @else
                <span class="inline-flex items-center px-3 py-1.5 rounded-xl border border-border text-muted cursor-not-allowed">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                    </svg>
                </span>
            @endif

        </div>
    </nav>
@endif
