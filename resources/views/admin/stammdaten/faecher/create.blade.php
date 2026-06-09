<x-app-layout>
    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-text">Neues Fach</h2>
            <a href="{{ route('admin.stammdaten.faecher.index') }}"
               class="px-4 py-2 h-10 rounded-xl bg-card text-text border border-border hover:bg-bg text-sm">
                Zurück
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-card border border-border rounded-2xl shadow-sm p-6">
                <form method="POST" action="{{ route('admin.stammdaten.faecher.store') }}" class="space-y-5">
                    @csrf

                    <div>
                        <label class="text-sm font-medium text-muted">Name *</label>
                        <input type="text" name="name" value="{{ old('name') }}" required maxlength="200"
                               class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('name') border-red-400 @enderror">
                        @error('name')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="text-sm font-medium text-muted">Kürzel * <span class="text-xs font-normal">(wird gross gespeichert)</span></label>
                        <input type="text" name="kurzname" value="{{ old('kurzname') }}" required maxlength="50"
                               class="mt-1 w-full rounded-xl border border-border bg-input text-text font-mono px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('kurzname') border-red-400 @enderror">
                        @error('kurzname')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="text-sm font-medium text-muted">Track *</label>
                        <select name="track_typ" required
                                class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('track_typ') border-red-400 @enderror">
                            <option value="">Bitte wählen…</option>
                            <option value="BMS" @selected(old('track_typ') === 'BMS')>BMS</option>
                            <option value="ABU" @selected(old('track_typ') === 'ABU')>ABU</option>
                        </select>
                        @error('track_typ')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="pt-2">
                        <button type="submit"
                                class="w-full px-4 py-2 h-10 rounded-xl bg-accent text-white hover:opacity-90 font-medium">
                            Fach anlegen
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
