<x-app-layout>
    <x-slot name="title">Fach bearbeiten</x-slot>
    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-text">Fach bearbeiten</h2>
            <a href="{{ route('admin.stammdaten.faecher.index') }}"
               class="px-4 py-2 h-10 rounded-xl glass-btn text-text text-sm">
                Zurück
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="glass rounded-2xl p-6">
                <form method="POST" action="{{ route('admin.stammdaten.faecher.update', $fach->fach_id) }}" class="space-y-5" x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                    @csrf
                    @method('PUT')

                    <div>
                        <label for="name" class="text-xs uppercase tracking-widest text-muted font-medium">Name *</label>
                        <input type="text" id="name" name="name" value="{{ old('name', $fach->name) }}" required maxlength="200"
                               class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('name') border-red-400 @enderror">
                        @error('name')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="kurzname" class="text-xs uppercase tracking-widest text-muted font-medium">Kürzel *</label>
                        <input type="text" id="kurzname" name="kurzname" value="{{ old('kurzname', $fach->kurzname) }}" required maxlength="50"
                               class="mt-1 w-full rounded-xl border border-border bg-input text-text font-mono px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('kurzname') border-red-400 @enderror">
                        @error('kurzname')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="track_typ" class="text-xs uppercase tracking-widest text-muted font-medium">Track</label>
                            <select id="track_typ" name="track_typ"
                                    class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('track_typ') border-red-400 @enderror">
                                <option value="" @selected(old('track_typ', $fach->track_typ) === null || old('track_typ', $fach->track_typ) === '')>Kein Track</option>
                                <option value="BMS" @selected(old('track_typ', $fach->track_typ) === 'BMS')>BMS</option>
                                <option value="ABU" @selected(old('track_typ', $fach->track_typ) === 'ABU')>ABU</option>
                            </select>
                            @error('track_typ')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="kategorie_id" class="text-xs uppercase tracking-widest text-muted font-medium">Kategorie *</label>
                            <select id="kategorie_id" name="kategorie_id" required
                                    class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('kategorie_id') border-red-400 @enderror">
                                @foreach($kategorien as $k)
                                    <option value="{{ $k->kategorie_id }}" @selected((int) old('kategorie_id', $fach->kategorie_id) === (int) $k->kategorie_id)>{{ $k->name }}</option>
                                @endforeach
                            </select>
                            @error('kategorie_id')
                                <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <input type="hidden" name="aktiv" value="0">
                        <input type="checkbox" id="aktiv" name="aktiv" value="1" @checked(old('aktiv', $fach->aktiv))
                               class="rounded-sm border-border text-accent focus:ring-ring">
                        <label for="aktiv" class="text-sm text-text">Fach aktiv</label>
                    </div>

                    <div class="pt-2">
                        <button type="submit" :disabled="loading"
                                class="w-full px-4 py-2 h-10 rounded-xl bg-accent text-white np-btn-primary font-medium disabled:opacity-60 disabled:cursor-not-allowed">
                            Änderungen speichern
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
