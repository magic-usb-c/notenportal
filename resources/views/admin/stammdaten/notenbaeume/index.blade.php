{{-- Notenbäume: die Zeile öffnet den Baum, «Exportieren» lädt ihn als JSON. Laden und Importieren sind Sheets
     (HIG «Sheets»): Titel, Felder, Abbrechen und Bestätigen unten rechts. Nach einem Validierungsfehler öffnet
     das betroffene Sheet wieder, damit die Meldung beim Feld steht. --}}
@php
    $feld = 'np-feld mt-1.5';
    $bezuege = collect($vorlagen)->map(fn ($v) => $v['bezug'])->all();
    $vorlageFehler = $errors->hasAny(['vorlage', 'lehrberuf_id']);
    $importFehler = $errors->hasAny(['datei', 'datei_lehrberuf_id']);
@endphp
<x-app-layout>
    <x-slot name="title">{{ __('Notenbäume') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :titel="__('Notenbäume')" :zaehler="$baeume->count() ?: null">
            <x-slot:aktionen>
                <button type="button" class="np-knopf np-knopf-sekundaer" x-data @click="$dispatch('open-modal', 'import-grade-tree')">
                    {{ __('Importieren…') }}
                </button>
                <button type="button" class="np-knopf np-knopf-primaer" x-data @click="$dispatch('open-modal', 'load-grade-tree-template')">
                    <x-symbol name="plus" strich="2" />{{ __('Vorlage laden…') }}
                </button>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-8">
            @if($baeume->isEmpty())
                <div class="np-karte">
                    <x-leer symbol="queue-list" :titel="__('Noch keine Notenbäume')" />
                </div>
            @else
                <div class="np-karte p-2">
                    <table class="np-tabelle table-fixed text-sm">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('Name') }}</th>
                                <th scope="col" class="w-96">{{ __('Gilt für') }}</th>
                                <th scope="col" class="w-28 text-right">{{ __('Noten') }}</th>
                                <th scope="col" class="w-36"><span class="sr-only">{{ __('Aktionen') }}</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($baeume as $b)
                                @php
                                    $ziel = route('admin.master-data.grade-trees.show', $b->baum_id);
                                    $gilt = $b->bezug === 'lehrberuf' ? $b->lehrberuf : __('Bildungsgang :track', ['track' => $b->track_typ]);
                                @endphp
                                <tr data-href="{{ $ziel }}">
                                    <td>
                                        <div class="flex min-w-0 items-center gap-2">
                                            <a href="{{ $ziel }}" class="truncate font-medium {{ $b->aktiv ? 'text-text' : 'text-muted' }}">{{ $b->name }}</a>
                                            @unless($b->aktiv)
                                                <span class="np-marke shrink-0 text-muted">{{ __('Inaktiv') }}</span>
                                            @endunless
                                        </div>
                                    </td>
                                    <td class="truncate {{ $b->aktiv ? 'text-text' : 'text-muted' }}">{{ $gilt }}</td>
                                    <td class="text-right text-muted">{{ $b->positionen }}</td>
                                    <td class="text-right">
                                        <x-zeilen-link :href="route('admin.master-data.grade-trees.export', $b->baum_id)" :label="__('Exportieren')" :zeile="$b->name" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <x-modal name="load-grade-tree-template" maxWidth="lg" :show="$vorlageFehler" focusable>
        <form method="POST" action="{{ route('admin.master-data.grade-trees.template') }}" role="dialog" aria-modal="true" aria-labelledby="load-grade-tree-template-title"
              x-data="{ loading: false, vorlage: @js(old('vorlage', array_key_first($vorlagen) ?? '')), bezuege: @js($bezuege) }" @submit="loading = true">
            @csrf
            <div class="flex flex-col gap-4 p-6">
                <h2 id="load-grade-tree-template-title" class="text-base font-semibold text-text">{{ __('Vorlage laden') }}</h2>
                <div>
                    <label for="vorlage" class="text-sm font-medium text-text">{{ __('Vorlage') }} *</label>
                    <select id="vorlage" name="vorlage" x-model="vorlage" required class="{{ $feld }}" @error('vorlage') aria-invalid="true" aria-describedby="vorlage-fehler" @enderror>
                        @foreach($vorlagen as $schluessel => $v)
                            <option value="{{ $schluessel }}">{{ $v['name'] }}</option>
                        @endforeach
                    </select>
                    @error('vorlage')<p id="vorlage-fehler" class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                </div>
                <div x-show="bezuege[vorlage] !== 'bildungsgang'">
                    <label for="vorlage-lehrberuf" class="text-sm font-medium text-text">{{ __('Lehrberuf') }} *</label>
                    <select id="vorlage-lehrberuf" name="lehrberuf_id" class="{{ $feld }}" :disabled="bezuege[vorlage] === 'bildungsgang'"
                            @error('lehrberuf_id') aria-invalid="true" aria-describedby="vorlage-lehrberuf-fehler" @enderror>
                        <option value="">{{ __('Bitte wählen') }}</option>
                        @foreach($lehrberufe as $l)
                            <option value="{{ $l->lehrberuf_id }}" @selected((int) old('lehrberuf_id') === (int) $l->lehrberuf_id || $lehrberufe->count() === 1)>{{ $l->name }}</option>
                        @endforeach
                    </select>
                    @error('lehrberuf_id')<p id="vorlage-lehrberuf-fehler" class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="flex justify-end gap-2 px-6 pb-6">
                <button type="button" class="np-knopf np-knopf-sekundaer" @click="$dispatch('close-modal', 'load-grade-tree-template')">{{ __('Abbrechen') }}</button>
                <button type="submit" :disabled="loading" class="np-knopf np-knopf-primaer">{{ __('Laden') }}</button>
            </div>
        </form>
    </x-modal>

    <x-modal name="import-grade-tree" maxWidth="lg" :show="$importFehler" focusable>
        <form method="POST" action="{{ route('admin.master-data.grade-trees.import') }}" enctype="multipart/form-data" role="dialog" aria-modal="true"
              aria-labelledby="import-grade-tree-title" x-data="{ loading: false }" @submit="loading = true">
            @csrf
            <div class="flex flex-col gap-4 p-6">
                <h2 id="import-grade-tree-title" class="text-base font-semibold text-text">{{ __('Datei importieren') }}</h2>
                <div>
                    <label for="datei" class="text-sm font-medium text-text">{{ __('Datei (JSON)') }} *</label>
                    <x-datei-feld id="datei" name="datei" accept=".json,application/json" required
                                  :aria-invalid="$errors->has('datei') ? 'true' : null" :aria-describedby="$errors->has('datei') ? 'datei-fehler' : null" />
                    @error('datei')<p id="datei-fehler" class="mt-1 whitespace-pre-line text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="datei-lehrberuf" class="text-sm font-medium text-text">{{ __('Lehrberuf') }}</label>
                    <select id="datei-lehrberuf" name="datei_lehrberuf_id" class="{{ $feld }}"
                            @error('datei_lehrberuf_id') aria-invalid="true" aria-describedby="datei-lehrberuf-fehler" @enderror>
                        <option value="">{{ __('Bildungsgang laut Datei') }}</option>
                        @foreach($lehrberufe as $l)
                            <option value="{{ $l->lehrberuf_id }}" @selected((int) old('datei_lehrberuf_id') === (int) $l->lehrberuf_id)>{{ $l->name }}</option>
                        @endforeach
                    </select>
                    @error('datei_lehrberuf_id')<p id="datei-lehrberuf-fehler" class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="flex justify-end gap-2 px-6 pb-6">
                <button type="button" class="np-knopf np-knopf-sekundaer" @click="$dispatch('close-modal', 'import-grade-tree')">{{ __('Abbrechen') }}</button>
                <button type="submit" :disabled="loading" class="np-knopf np-knopf-primaer">{{ __('Importieren') }}</button>
            </div>
        </form>
    </x-modal>
</x-app-layout>
