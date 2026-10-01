<x-einrichtung schritt="modules" :stand="$stand" :titel="__('Module')">
    @php
        $feld = 'np-feld mt-1';
        $label = 'text-sm font-medium text-text';
        $beispiele = [
            'schule' => "901 Beispielmodul Planung\n902 Beispielmodul Auswertung",
            'uek' => "951 Beispielkurs Grundlagen\n952 Beispielkurs Vertiefung",
        ];
    @endphp

    @if($lehrberufe->isEmpty())
        <section class="np-karte p-8 flex flex-col items-center gap-3 text-center">
            <p class="text-sm text-muted">{{ __('Noch keine Lehrberufe') }}</p>
            <a href="{{ route('admin.setup', 'professions') }}" class="np-knopf np-knopf-primaer">{{ __('Lehrberufe anlegen') }}</a>
        </section>
        @include('admin.einrichtung._fuss', ['schritt' => 'modules', 'knopf' => false])
    @else
        <div class="flex flex-wrap gap-1.5" role="tablist" aria-label="{{ __('Lehrberuf') }}">
            @foreach($lehrberufe as $lb)
                @php $ist = $aktiv && (int) $aktiv->lehrberuf_id === (int) $lb->lehrberuf_id; @endphp
                <a href="{{ route('admin.setup', ['schritt' => 'modules', 'lehrberuf_id' => $lb->lehrberuf_id]) }}" role="tab" aria-selected="{{ $ist ? 'true' : 'false' }}" title="{{ $lb->name }}"
                   @class(['inline-flex items-center gap-2 px-3 min-h-9 rounded-full text-sm border transition-colors',
                       'border-accent/50 bg-accent/10 text-accent-text' => $ist, 'border-border text-muted hover:text-text' => ! $ist])>
                    {{ $lb->kuerzel }}
                    <span @class(['text-3xs tabular-nums px-1.5 rounded-full', 'bg-accent/15' => $lb->anzahl, 'bg-bg' => ! $lb->anzahl])>{{ $lb->anzahl }}</span>
                </a>
            @endforeach
        </div>

        <form method="POST" action="{{ route('admin.setup.modules') }}" class="flex flex-col gap-5"
              x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
            @csrf
            <input type="hidden" name="lehrberuf_id" value="{{ $aktiv->lehrberuf_id }}">
            <section class="np-karte p-6 flex flex-col gap-4">
                <h3 class="text-sm font-semibold text-text">{{ $aktiv->name }}</h3>
                <div class="grid grid-cols-2 gap-4">
                    @foreach(['schule' => __('Module Schule'), 'uek' => __('Module ÜK')] as $name => $text)
                        <div>
                            <label for="{{ $name }}" class="{{ $label }}">{{ $text }}</label>
                            <textarea id="{{ $name }}" name="{{ $name }}" rows="8" placeholder="{{ $beispiele[$name] }}" class="{{ $feld }} font-mono text-sm">{{ old($name) }}</textarea>
                            @error($name)<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                </div>
                <p class="text-xs text-muted">
                    {{ __('Viele Module von Hand? Der Modulkatalog lässt sich als Datei einlesen – mit Nummern, Titeln, Versionen und Handlungszielen.') }}
                    <a href="{{ route('admin.master-data.modules.catalog') }}" class="text-accent-text hover:underline">{{ __('Katalog einlesen') }}</a>
                </p>
                <div class="w-56">
                    <label for="ziel" class="{{ $label }}">{{ __('Gewichtssumme je Modul') }}</label>
                    <input id="ziel" name="ziel" type="number" required min="1" max="9999" step="1" value="{{ old('ziel', 100) }}" class="{{ $feld }} tabular-nums">
                    @error('ziel')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                </div>
            </section>

            @if($zugeordnet->isNotEmpty())
                <section class="np-karte p-5">
                    <div class="flex items-baseline justify-between gap-3 mb-3">
                        <h3 class="text-sm font-semibold text-text">{{ __('Zugeordnet') }} · {{ $zugeordnet->count() }}</h3>
                        <a href="{{ route('admin.master-data.professions.show', $aktiv->lehrberuf_id) }}" class="text-xs text-accent-text hover:underline">{{ __('Pflicht, Semester und Lernort bearbeiten') }}</a>
                    </div>
                    <div class="grid grid-cols-2 gap-5">
                        @foreach($zugeordnet->groupBy(fn ($m) => $m->lernort ?? '–') as $lernort => $liste)
                            <div>
                                <div class="text-xs font-medium text-muted mb-2">{{ $lernort }}</div>
                                <ul class="flex flex-col gap-1 text-sm">
                                    @foreach($liste as $m)
                                        <li class="flex gap-3 min-w-0"><span class="tabular-nums text-muted w-12 shrink-0">{{ $m->modul_nummer }}</span><span class="truncate text-text">{{ $m->titel }}</span></li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            @include('admin.einrichtung._fuss', ['schritt' => 'modules', 'knopf' => __('Module zuordnen'), 'weiterText' => __('Weiter')])
        </form>
    @endif
</x-einrichtung>
