@php
    $feld = 'mt-1.5 h-10 w-full rounded-lg border border-border-strong/70 bg-input px-3 text-sm text-text focus:border-accent focus:ring-2 focus:ring-ring/30';
    $bezuege = collect($vorlagen)->map(fn ($v) => $v['bezug'])->all();
@endphp
<x-app-layout>
    <x-slot name="title">{{ __('Notenbäume') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :titel="__('Notenbäume')" :zaehler="$baeume->count() ?: null" />
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-4 sm:px-6 lg:px-8 flex flex-col gap-8">

            @if($baeume->isNotEmpty())
                <div class="overflow-x-auto rounded-xl border border-border bg-card">
                    <table class="w-full text-sm tabular-nums">
                        <thead class="bg-surface-2">
                            <tr>
                                <th scope="col" class="h-9 px-4 text-left text-2xs font-medium text-muted">{{ __('Name') }}</th>
                                <th scope="col" class="h-9 px-4 text-left text-2xs font-medium text-muted">{{ __('Gilt für') }}</th>
                                <th scope="col" class="h-9 px-4 text-right text-2xs font-medium text-muted">{{ __('Erfasste Noten') }}</th>
                                <th scope="col" class="h-9 px-4 text-left text-2xs font-medium text-muted">{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($baeume as $b)
                                <tr class="border-b border-border last:border-0 hover:bg-surface-2/60">
                                    <td class="h-11 px-4">
                                        <a href="{{ route('admin.master-data.grade-trees.show', $b->baum_id) }}" class="inline-flex min-h-6 items-center font-medium text-accent-text underline-offset-2 hover:underline">{{ $b->name }}</a>
                                    </td>
                                    <td class="px-4 text-text">{{ $b->bezug === 'lehrberuf' ? $b->lehrberuf : __('Bildungsgang :track', ['track' => $b->track_typ]) }}</td>
                                    <td class="px-4 text-right text-muted">{{ $b->positionen }}</td>
                                    <td class="px-4">
                                        @if($b->aktiv)
                                            <span class="rounded-md bg-note-gut/14 px-2 py-0.5 text-xs text-note-gut">{{ __('aktiv') }}</span>
                                        @else
                                            <span class="rounded-md border border-border bg-surface-2 px-2 py-0.5 text-xs text-muted">{{ __('inaktiv') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <x-karte :titel="__('Vorlage laden')">
                    <form method="POST" action="{{ route('admin.master-data.grade-trees.template') }}" class="flex flex-col gap-4"
                          x-data="{ loading: false, vorlage: @js(old('vorlage', array_key_first($vorlagen) ?? '')), bezuege: @js($bezuege) }" @submit="loading = true">
                        @csrf
                        <div>
                            <label for="vorlage" class="text-sm font-medium text-text">{{ __('Vorlage') }} <span class="text-note-ungenuegend">*</span></label>
                            <select id="vorlage" name="vorlage" x-model="vorlage" required class="{{ $feld }}" @error('vorlage') aria-invalid="true" aria-describedby="vorlage-fehler" @enderror>
                                @foreach($vorlagen as $schluessel => $v)
                                    <option value="{{ $schluessel }}">{{ $v['name'] }}</option>
                                @endforeach
                            </select>
                            @error('vorlage')<p id="vorlage-fehler" class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                        </div>
                        <div x-show="bezuege[vorlage] !== 'bildungsgang'">
                            <label for="vorlage-lehrberuf" class="text-sm font-medium text-text">{{ __('Lehrberuf') }} <span class="text-note-ungenuegend">*</span></label>
                            <select id="vorlage-lehrberuf" name="lehrberuf_id" class="{{ $feld }}" :disabled="bezuege[vorlage] === 'bildungsgang'"
                                    @error('lehrberuf_id') aria-invalid="true" aria-describedby="vorlage-lehrberuf-fehler" @enderror>
                                <option value="">{{ __('Bitte wählen') }}</option>
                                @foreach($lehrberufe as $l)
                                    <option value="{{ $l->lehrberuf_id }}" @selected((int) old('lehrberuf_id') === (int) $l->lehrberuf_id || $lehrberufe->count() === 1)>{{ $l->name }}</option>
                                @endforeach
                            </select>
                            @error('lehrberuf_id')<p id="vorlage-lehrberuf-fehler" class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <button type="submit" :disabled="loading"
                                    class="inline-flex h-9 items-center gap-2 rounded-lg bg-accent px-3.5 text-sm font-medium text-accent-contrast np-btn-primary disabled:opacity-50">
                                {{ __('Laden') }}
                            </button>
                        </div>
                    </form>
                </x-karte>

                <x-karte :titel="__('Datei importieren')">
                    <form method="POST" action="{{ route('admin.master-data.grade-trees.import') }}" enctype="multipart/form-data" class="flex flex-col gap-4"
                          x-data="{ loading: false }" @submit="loading = true">
                        @csrf
                        <div>
                            <label for="datei" class="text-sm font-medium text-text">{{ __('Datei (JSON)') }} <span class="text-note-ungenuegend">*</span></label>
                            <input id="datei" name="datei" type="file" accept=".json,application/json" required
                                   class="mt-1.5 block w-full text-sm text-text file:mr-3 file:h-9 file:rounded-lg file:border-0 file:bg-surface-2 file:px-3 file:text-sm file:font-medium file:text-text"
                                   @error('datei') aria-invalid="true" aria-describedby="datei-fehler" @enderror>
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
                        <div>
                            <button type="submit" :disabled="loading"
                                    class="inline-flex h-9 items-center gap-2 rounded-lg glass-btn px-3.5 text-sm font-medium text-text disabled:opacity-50">
                                {{ __('Importieren') }}
                            </button>
                        </div>
                    </form>
                </x-karte>
            </div>
        </div>
    </div>
</x-app-layout>
