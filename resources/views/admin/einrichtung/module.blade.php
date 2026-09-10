<x-einrichtung schritt="module" :stand="$stand" titel="Module">
    @php
        $feld = 'mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring';
        $label = 'text-xs uppercase tracking-widest text-muted font-medium';
        $beispiele = [
            'schule' => "431 Aufträge im IT-Umfeld selbstständig durchführen\n162 Daten analysieren und modellieren",
            'uek' => "106 Datenbanken abfragen, bearbeiten und warten\n187 ICT-Arbeitsplatz in Betrieb nehmen",
        ];
    @endphp

    @if($lehrberufe->isEmpty())
        <section class="glass rounded-2xl p-8 flex flex-col items-center gap-3 text-center">
            <p class="text-sm text-muted">Noch keine Lehrberufe</p>
            <a href="{{ route('admin.einrichtung', 'lehrberufe') }}" class="inline-flex items-center px-5 h-10 rounded-xl bg-accent text-white text-sm font-semibold np-btn-primary">Lehrberufe anlegen</a>
        </section>
        @include('admin.einrichtung._fuss', ['schritt' => 'module', 'knopf' => false])
    @else
        <div class="flex flex-wrap gap-1.5" role="tablist" aria-label="Lehrberuf">
            @foreach($lehrberufe as $lb)
                @php $ist = $aktiv && (int) $aktiv->lehrberuf_id === (int) $lb->lehrberuf_id; @endphp
                <a href="{{ route('admin.einrichtung', ['schritt' => 'module', 'lehrberuf_id' => $lb->lehrberuf_id]) }}" role="tab" aria-selected="{{ $ist ? 'true' : 'false' }}" title="{{ $lb->name }}"
                   @class(['inline-flex items-center gap-2 px-3 min-h-9 rounded-full text-sm border transition-colors',
                       'border-accent/50 bg-accent/10 text-accent' => $ist, 'border-border text-muted hover:text-text' => ! $ist])>
                    {{ $lb->kuerzel }}
                    <span @class(['text-[11px] tabular-nums px-1.5 rounded-full', 'bg-accent/15' => $lb->anzahl, 'bg-bg' => ! $lb->anzahl])>{{ $lb->anzahl }}</span>
                </a>
            @endforeach
        </div>

        <form method="POST" action="{{ route('admin.einrichtung.module') }}" class="flex flex-col gap-5"
              x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
            @csrf
            <input type="hidden" name="lehrberuf_id" value="{{ $aktiv->lehrberuf_id }}">
            <section class="glass rounded-2xl p-6 flex flex-col gap-4">
                <h3 class="text-sm font-semibold text-text">{{ $aktiv->name }}</h3>
                <div class="grid md:grid-cols-2 gap-4">
                    @foreach(['schule' => 'Module Schule', 'uek' => 'Module ÜK'] as $name => $text)
                        <div>
                            <label for="{{ $name }}" class="{{ $label }}">{{ $text }}</label>
                            <textarea id="{{ $name }}" name="{{ $name }}" rows="8" placeholder="{{ $beispiele[$name] }}" class="{{ $feld }} font-mono text-sm">{{ old($name) }}</textarea>
                            @error($name)<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                </div>
                <div class="w-56">
                    <label for="ziel" class="{{ $label }}">Gewichtssumme je Modul *</label>
                    <input id="ziel" name="ziel" type="number" required min="1" max="9999" step="1" value="{{ old('ziel', 100) }}" class="{{ $feld }} tabular-nums">
                    @error('ziel')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                </div>
            </section>

            @if($zugeordnet->isNotEmpty())
                <section class="glass rounded-2xl p-5">
                    <div class="flex items-baseline justify-between gap-3 mb-3">
                        <h3 class="text-sm font-semibold text-text">Zugeordnet · {{ $zugeordnet->count() }}</h3>
                        <a href="{{ route('admin.stammdaten.lehrberufe.show', $aktiv->lehrberuf_id) }}" class="text-xs text-accent hover:underline">Pflicht, Semester und Lernort bearbeiten</a>
                    </div>
                    <div class="grid md:grid-cols-2 gap-5">
                        @foreach($zugeordnet->groupBy(fn ($m) => $m->lernort ?? '–') as $lernort => $liste)
                            <div>
                                <div class="text-[11px] uppercase tracking-widest text-muted mb-2">{{ $lernort }}</div>
                                <ul class="flex flex-col gap-1 text-sm">
                                    @foreach($liste as $m)
                                        <li class="flex gap-3 min-w-0"><span class="font-mono text-muted w-12 shrink-0">{{ $m->modul_nummer }}</span><span class="truncate text-text">{{ $m->titel }}</span></li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            @include('admin.einrichtung._fuss', ['schritt' => 'module', 'knopf' => 'Module zuordnen', 'weiterText' => 'Weiter'])
        </form>
    @endif
</x-einrichtung>
