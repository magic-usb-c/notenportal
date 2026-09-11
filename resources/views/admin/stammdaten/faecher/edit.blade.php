<x-app-layout>
    <x-slot name="title">Fach bearbeiten</x-slot>
    <x-slot name="header">
        <x-seitenkopf titel="Fach bearbeiten" schmal>
            <x-slot:aktionen>
                <a href="{{ route('admin.master-data.subjects.index') }}"
                   class="inline-flex h-9 items-center gap-2 rounded-lg glass-btn px-3.5 text-sm font-medium text-text">
                    Zurück
                </a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl rounded-xl border border-border bg-card p-6">
                <form method="POST" action="{{ route('admin.master-data.subjects.update', $fach->fach_id) }}" class="space-y-5" x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                    @csrf
                    @method('PUT')

                    <div>
                        <label for="name" class="text-sm font-medium text-text">Name *</label>
                        <input type="text" id="name" name="name" value="{{ old('name', $fach->name) }}" required maxlength="200"
                               class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('name') border-note-ungenuegend @enderror">
                        @error('name')
                            <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="kurzname" class="text-sm font-medium text-text">Kürzel *</label>
                        <input type="text" id="kurzname" name="kurzname" value="{{ old('kurzname', $fach->kurzname) }}" required maxlength="50"
                               class="mt-1 w-full rounded-xl border border-border bg-input text-text font-mono px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('kurzname') border-note-ungenuegend @enderror">
                        @error('kurzname')
                            <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="track_typ" class="text-sm font-medium text-text">Track</label>
                            <select id="track_typ" name="track_typ"
                                    class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('track_typ') border-note-ungenuegend @enderror">
                                <option value="" @selected(old('track_typ', $fach->track_typ) === null || old('track_typ', $fach->track_typ) === '')>Kein Track</option>
                                <option value="BMS" @selected(old('track_typ', $fach->track_typ) === 'BMS')>BMS</option>
                                <option value="ABU" @selected(old('track_typ', $fach->track_typ) === 'ABU')>ABU</option>
                            </select>
                            @error('track_typ')
                                <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="kategorie_id" class="text-sm font-medium text-text">Kategorie *</label>
                            <select id="kategorie_id" name="kategorie_id" required
                                    class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('kategorie_id') border-note-ungenuegend @enderror">
                                @foreach($kategorien as $k)
                                    <option value="{{ $k->kategorie_id }}" @selected((int) old('kategorie_id', $fach->kategorie_id) === (int) $k->kategorie_id)>{{ $k->name }}</option>
                                @endforeach
                            </select>
                            @error('kategorie_id')
                                <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
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
                                class="w-full px-4 py-2 h-10 rounded-xl bg-accent text-accent-contrast np-btn-primary font-medium disabled:opacity-60 disabled:cursor-not-allowed">
                            Änderungen speichern
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
