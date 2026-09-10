<x-app-layout>
    <x-slot name="title">Neues Fach</x-slot>
    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-text">Neues Fach</h2>
            <a href="{{ route('admin.stammdaten.faecher.index') }}"
               class="px-4 py-2 h-10 rounded-xl glass-btn text-text text-sm">
                Zurück
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="glass rounded-2xl p-6">
                <form method="POST" action="{{ route('admin.stammdaten.faecher.store') }}" class="space-y-5" x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                    @csrf

                    <div>
                        <label for="name" class="text-xs uppercase tracking-widest text-muted font-medium">Name *</label>
                        <input type="text" id="name" name="name" value="{{ old('name') }}" required maxlength="200"
                               class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('name') border-red-400 @enderror">
                        @error('name')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="kurzname" class="text-xs uppercase tracking-widest text-muted font-medium">Kürzel *</label>
                        <input type="text" id="kurzname" name="kurzname" value="{{ old('kurzname') }}" required maxlength="50"
                               class="mt-1 w-full rounded-xl border border-border bg-input text-text font-mono px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('kurzname') border-red-400 @enderror">
                        @error('kurzname')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
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
                            <label for="track_typ" class="text-xs uppercase tracking-widest text-muted font-medium">Track</label>
                            <select id="track_typ" name="track_typ" x-model="track" @change="aufTrackWechsel()"
                                    class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('track_typ') border-red-400 @enderror">
                                <option value="">Kein Track</option>
                                <option value="BMS">BMS</option>
                                <option value="ABU">ABU</option>
                            </select>
                            @error('track_typ')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="kategorie_id" class="text-xs uppercase tracking-widest text-muted font-medium">Kategorie *</label>
                            <select id="kategorie_id" name="kategorie_id" required x-model="kategorieId" @change="beruehrt = true"
                                    class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('kategorie_id') border-red-400 @enderror">
                                <option value="">Bitte wählen…</option>
                                @foreach($kategorien as $k)
                                    <option value="{{ $k->kategorie_id }}">{{ $k->name }}</option>
                                @endforeach
                            </select>
                            @error('kategorie_id')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="pt-2">
                        <button type="submit" :disabled="loading"
                                class="w-full px-4 py-2 h-10 rounded-xl bg-accent text-white np-btn-primary font-medium disabled:opacity-60 disabled:cursor-not-allowed">
                            Fach anlegen
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
