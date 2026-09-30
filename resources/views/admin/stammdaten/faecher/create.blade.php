<x-app-layout>
    <x-slot name="title">{{ __('Neues Fach') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :zurueck="route('admin.master-data.subjects.index')" :titel="__('Neues Fach')" schmal>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-4 sm:px-6 lg:px-8">
            <div class="np-karte max-w-3xl p-6">
                <form method="POST" action="{{ route('admin.master-data.subjects.store') }}" class="space-y-5" x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                    @csrf

                    <div>
                        <label for="name" class="text-sm font-medium text-text">{{ __('Name *') }}</label>
                        <input type="text" id="name" name="name" value="{{ old('name') }}" required maxlength="200"
                               class="np-feld mt-1 @error('name') border-note-ungenuegend @enderror">
                        @error('name')
                            <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="kurzname" class="text-sm font-medium text-text">{{ __('Kürzel *') }}</label>
                        <input type="text" id="kurzname" name="kurzname" value="{{ old('kurzname') }}" required maxlength="50"
                               class="np-feld mt-1 tabular-nums @error('kurzname') border-note-ungenuegend @enderror">
                        @error('kurzname')
                            <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
                        @enderror
                    </div>

                    <div x-data="{
                            track: '{{ old('track_typ', '') }}',
                            kategorieId: '{{ old('kategorie_id', optional($kategorien->firstWhere('code', 'FACH'))->kategorie_id) }}',
                            beruehrt: false,
                            karte: {{ Js::from($kategorien->pluck('kategorie_id', 'code')) }},
                            aufTrackWechsel() {
                                if (this.beruehrt) return;
                                const code = this.track === 'BMS' ? 'BMS' : (this.track === 'ABU' ? 'ABU' : 'FACH');
                                if (this.karte[code]) this.kategorieId = String(this.karte[code]);
                            },
                        }" class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="track_typ" class="text-sm font-medium text-text">{{ __('Track') }}</label>
                            <select id="track_typ" name="track_typ" x-model="track" @change="aufTrackWechsel()"
                                    class="np-feld mt-1 @error('track_typ') border-note-ungenuegend @enderror">
                                <option value="">{{ __('Kein Track') }}</option>
                                <option value="BMS">BMS</option>
                                <option value="ABU">ABU</option>
                            </select>
                            @error('track_typ')
                                <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="kategorie_id" class="text-sm font-medium text-text">{{ __('Kategorie *') }}</label>
                            <select id="kategorie_id" name="kategorie_id" required x-model="kategorieId" @change="beruehrt = true"
                                    class="np-feld mt-1 @error('kategorie_id') border-note-ungenuegend @enderror">
                                <option value="">{{ __('Bitte wählen…') }}</option>
                                @foreach($kategorien as $k)
                                    <option value="{{ $k->kategorie_id }}">{{ $k->name }}</option>
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
                                <option value="note" @selected(old('skala', 'note') === 'note')>{{ __('Note 1–6') }}</option>
                                <option value="stufe" @selected(old('skala', 'note') === 'stufe')>{{ __('Stufe A/B/C') }}</option>
                            </select>
                            @error('skala')
                                <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
                            @enderror
                        </div>
                        <div class="flex items-end gap-3 pb-2">
                            <input type="hidden" name="zaehlt" value="0">
                            <input type="checkbox" role="switch" id="zaehlt" name="zaehlt" value="1" @checked(old('zaehlt', 1))
                                   class="np-schalter">
                            <label for="zaehlt" class="text-sm text-text">{{ __('Zählt in Schnitt und Promotion') }}</label>
                        </div>
                    </div>
                    <x-formular-aktionen :abbrechen="route('admin.master-data.subjects.index')">{{ __('Fach anlegen') }}</x-formular-aktionen>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
