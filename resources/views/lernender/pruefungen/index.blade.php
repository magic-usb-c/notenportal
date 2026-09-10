<x-app-layout>
    <x-slot name="title">Prüfungen</x-slot>
    <x-slot name="header">
        <div class="w-full flex flex-wrap items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-text">Prüfungen</h2>
            <a href="{{ route('lernender.noten.rechner') }}" class="inline-flex items-center gap-2 px-4 h-10 rounded-xl glass-btn text-text text-sm">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m-6 4h6m-6 4h4m5 4H5a2 2 0 01-2-2V5a2 2 0 012-2h10l4 4v11a2 2 0 01-2 2z"/></svg>
                Was brauche ich?
            </a>
        </div>
    </x-slot>

    @php
        $label = 'text-xs uppercase tracking-widest text-muted font-medium';
        $feld = 'mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2.5 focus:ring-2 focus:ring-ring focus:border-ring';
        $fehler = 'mt-1 text-xs text-red-600 dark:text-red-400';
        $b = $bearbeiten;
    @endphp

    <div class="py-6">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-5 gap-5 items-start">

            <section class="lg:col-span-2 glass rounded-2xl p-5" id="planen">
                <h3 class="font-semibold text-text mb-4">{{ $b ? 'Prüfung bearbeiten' : 'Prüfung planen' }}</h3>
                <form method="POST" action="{{ $b ? route('lernender.pruefungen.update', $b->pruefung_id) : route('lernender.pruefungen.store') }}"
                      class="flex flex-col gap-4" x-data="{ loading: false, gewicht: @js((string) old('gewichtung_prozent', $b?->gewichtung_prozent ?? 100)) }"
                      @submit="if (!$event.defaultPrevented) loading = true">
                    @csrf
                    @if($b)
                        @method('PUT')
                    @endif

                    <div>
                        <label for="bezug" class="{{ $label }}">Fach / Modul <span class="text-red-600 dark:text-red-400">*</span></label>
                        <select id="bezug" name="bezug" required class="{{ $feld }}">
                            <option value="">Bitte wählen</option>
                            @foreach($bezugOptionen as $gruppe => $optionen)
                                <optgroup label="{{ $gruppe }}">
                                    @foreach($optionen as $o)
                                        <option value="{{ $o['wert'] }}" @selected(old('bezug', $b?->bezug()) === $o['wert'])>{{ $o['label'] }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                        @error('bezug')<p class="{{ $fehler }}">{{ $message }}</p>@enderror
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="datum" class="{{ $label }}">Datum <span class="text-red-600 dark:text-red-400">*</span></label>
                            <input type="date" id="datum" name="datum" required value="{{ old('datum', $b?->datum?->toDateString()) }}" class="{{ $feld }}">
                            @error('datum')<p class="{{ $fehler }}">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="gewichtung_prozent" class="{{ $label }}">Gewichtung %</label>
                            <input type="number" id="gewichtung_prozent" name="gewichtung_prozent" min="0" max="100" step="0.01" required x-model="gewicht" class="{{ $feld }}">
                            @error('gewichtung_prozent')<p class="{{ $fehler }}">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div class="flex gap-1.5 -mt-2 justify-end">
                        @foreach([25, 50, 100] as $g)
                            <button type="button" @click="gewicht = '{{ $g }}'" class="min-h-9 min-w-12 px-3 rounded-lg border text-xs transition-colors"
                                    :class="parseFloat(gewicht) === {{ $g }} ? 'border-accent/50 bg-accent/10 text-accent' : 'border-border text-muted hover:text-text'">{{ $g }}%</button>
                        @endforeach
                    </div>

                    <div>
                        <label for="titel" class="{{ $label }}">Titel</label>
                        <input id="titel" name="titel" maxlength="150" value="{{ old('titel', $b?->titel) }}" class="{{ $feld }}">
                        @error('titel')<p class="{{ $fehler }}">{{ $message }}</p>@enderror
                    </div>

                    <div class="flex gap-2">
                        @if($b)
                            <a href="{{ route('lernender.pruefungen.index') }}" class="inline-flex items-center justify-center px-4 h-11 rounded-xl glass-btn text-text text-sm">Abbrechen</a>
                        @endif
                        <button :disabled="loading" class="flex-1 h-11 rounded-xl bg-accent text-white font-semibold np-btn-primary disabled:opacity-60">{{ $b ? 'Speichern' : 'Planen' }}</button>
                    </div>
                </form>
            </section>

            <div class="lg:col-span-3 flex flex-col gap-5">
                @if($ohneNote->isNotEmpty())
                    <section class="glass rounded-2xl overflow-hidden border-l-4 border-l-yellow-500">
                        <h3 class="px-5 py-3 font-semibold text-text border-b border-border/70">Note fehlt <span class="ml-1 text-sm text-muted tabular-nums">{{ $ohneNote->count() }}</span></h3>
                        <div class="divide-y divide-border/70">
                            @foreach($ohneNote as $p)
                                @include('lernender.pruefungen._zeile', ['p' => $p, 'faellig' => true])
                            @endforeach
                        </div>
                    </section>
                @endif

                <section class="glass rounded-2xl overflow-hidden">
                    <h3 class="px-5 py-3 font-semibold text-text border-b border-border/70">Anstehend <span class="ml-1 text-sm text-muted tabular-nums">{{ $anstehend->count() }}</span></h3>
                    <div class="divide-y divide-border/70">
                        @forelse($anstehend as $p)
                            @include('lernender.pruefungen._zeile', ['p' => $p, 'faellig' => false])
                        @empty
                            <div class="px-5 py-10 text-center text-sm text-muted">Keine Prüfungen geplant</div>
                        @endforelse
                    </div>
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
