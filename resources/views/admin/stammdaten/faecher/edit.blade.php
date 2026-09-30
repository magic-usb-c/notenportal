<x-app-layout>
    <x-slot name="title">{{ __('Fach bearbeiten') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :zurueck="route('admin.master-data.subjects.index')" :titel="__('Fach bearbeiten')" schmal>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-4 sm:px-6 lg:px-8">
            <div class="np-karte max-w-3xl p-6">
                <form method="POST" action="{{ route('admin.master-data.subjects.update', $fach->fach_id) }}" class="space-y-5" x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                    @csrf
                    @method('PUT')

                    <div>
                        <label for="name" class="text-sm font-medium text-text">{{ __('Name *') }}</label>
                        <input type="text" id="name" name="name" value="{{ old('name', $fach->name) }}" required maxlength="200"
                               class="np-feld mt-1 @error('name') border-note-ungenuegend @enderror">
                        @error('name')
                            <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="kurzname" class="text-sm font-medium text-text">{{ __('Kürzel *') }}</label>
                        <input type="text" id="kurzname" name="kurzname" value="{{ old('kurzname', $fach->kurzname) }}" required maxlength="50"
                               class="np-feld mt-1 tabular-nums @error('kurzname') border-note-ungenuegend @enderror">
                        @error('kurzname')
                            <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="track_typ" class="text-sm font-medium text-text">{{ __('Track') }}</label>
                            <select id="track_typ" name="track_typ"
                                    class="np-feld mt-1 @error('track_typ') border-note-ungenuegend @enderror">
                                <option value="" @selected(old('track_typ', $fach->track_typ) === null || old('track_typ', $fach->track_typ) === '')>{{ __('Kein Track') }}</option>
                                <option value="BMS" @selected(old('track_typ', $fach->track_typ) === 'BMS')>BMS</option>
                                <option value="ABU" @selected(old('track_typ', $fach->track_typ) === 'ABU')>ABU</option>
                            </select>
                            @error('track_typ')
                                <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="kategorie_id" class="text-sm font-medium text-text">{{ __('Kategorie *') }}</label>
                            <select id="kategorie_id" name="kategorie_id" required
                                    class="np-feld mt-1 @error('kategorie_id') border-note-ungenuegend @enderror">
                                @foreach($kategorien as $k)
                                    <option value="{{ $k->kategorie_id }}" @selected((int) old('kategorie_id', $fach->kategorie_id) === (int) $k->kategorie_id)>{{ $k->name }}</option>
                                @endforeach
                            </select>
                            @error('kategorie_id')
                                <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>


                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label for="skala" class="text-sm font-medium text-text">{{ __('Bewertung') }}</label>
                            <select id="skala" name="skala"
                                    class="np-feld mt-1 @error('skala') border-note-ungenuegend @enderror">
                                <option value="note" @selected(old('skala', $fach?->skala ?? 'note') === 'note')>{{ __('Note 1–6') }}</option>
                                <option value="stufe" @selected(old('skala', $fach?->skala ?? 'note') === 'stufe')>{{ __('Stufe A/B/C') }}</option>
                            </select>
                            @error('skala')
                                <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="flex items-end gap-3 pb-2">
                            <input type="hidden" name="zaehlt" value="0">
                            <input type="checkbox" role="switch" id="zaehlt" name="zaehlt" value="1" @checked(old('zaehlt', $fach?->zaehlt ?? 1))
                                   class="np-schalter">
                            <label for="zaehlt" class="text-sm text-text">{{ __('Zählt in Schnitt und Promotion') }}</label>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <input type="hidden" name="aktiv" value="0">
                        <input type="checkbox" role="switch" id="aktiv" name="aktiv" value="1" @checked(old('aktiv', $fach->aktiv))
                               class="np-schalter">
                        <label for="aktiv" class="text-sm text-text">{{ __('Fach aktiv') }}</label>
                    </div>

                    <x-formular-aktionen :abbrechen="route('admin.master-data.subjects.index')">{{ __('Änderungen speichern') }}</x-formular-aktionen>
                </form>
            </div>

            <div class="np-karte mt-6 max-w-3xl p-6">
                @if($notenAnzahl > 0)
                    <p class="text-sm text-muted">{{ __('Das Fach hat bereits Noten oder Prüfungen. Deaktiviere es stattdessen.') }}</p>
                @else
                    <form method="POST" action="{{ route('admin.master-data.subjects.destroy', $fach->fach_id) }}"
                          onsubmit="return confirm(@js(__('Fach «:name» endgültig löschen?', ['name' => $fach->name])));">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="np-knopf np-knopf-gefahr">
                            {{ __('Fach löschen') }}
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
