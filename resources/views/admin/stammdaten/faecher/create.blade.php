<x-app-layout>
    <x-slot name="title">{{ __('Neues Fach') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :titel="__('Neues Fach')" schmal>
            <x-slot:aktionen>
                <a href="{{ route('admin.master-data.subjects.index') }}"
                   class="inline-flex h-9 items-center gap-2 rounded-lg glass-btn px-3.5 text-sm font-medium text-text">
                    {{ __('Zurück') }}
                </a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl rounded-xl border border-border bg-card p-6">
                <form method="POST" action="{{ route('admin.master-data.subjects.store') }}" class="space-y-5" x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                    @csrf

                    <div>
                        <label for="name" class="text-sm font-medium text-text">{{ __('Name *') }}</label>
                        <input type="text" id="name" name="name" value="{{ old('name') }}" required maxlength="200"
                               class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('name') border-note-ungenuegend @enderror">
                        @error('name')
                            <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="kurzname" class="text-sm font-medium text-text">{{ __('Kürzel *') }}</label>
                        <input type="text" id="kurzname" name="kurzname" value="{{ old('kurzname') }}" required maxlength="50"
                               class="mt-1 w-full rounded-xl border border-border bg-input text-text font-mono px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('kurzname') border-note-ungenuegend @enderror">
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
                                    class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('track_typ') border-note-ungenuegend @enderror">
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
                                    class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('kategorie_id') border-note-ungenuegend @enderror">
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

                    <div class="pt-2">
                        <button type="submit" :disabled="loading"
                                class="w-full px-4 py-2 h-10 rounded-xl bg-accent text-accent-contrast np-btn-primary font-medium disabled:opacity-60 disabled:cursor-not-allowed">
                            {{ __('Fach anlegen') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
