<x-app-layout>
    <x-slot name="title">Lehrberuf bearbeiten</x-slot>
    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-text">Lehrberuf bearbeiten</h2>
            <div class="flex gap-2">
                <a href="{{ route('admin.stammdaten.lehrberufe.show', $lehrberuf->lehrberuf_id) }}"
                   class="px-4 py-2 h-10 rounded-xl glass-btn text-text text-sm">
                    Module/Fächer
                </a>
                <a href="{{ route('admin.stammdaten.lehrberufe.index') }}"
                   class="px-4 py-2 h-10 rounded-xl glass-btn text-text text-sm">
                    Zurück
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="glass rounded-2xl p-6">
                <form method="POST" action="{{ route('admin.stammdaten.lehrberufe.update', $lehrberuf->lehrberuf_id) }}" class="space-y-5" x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                    @csrf
                    @method('PUT')

                    <div>
                        <label for="kuerzel" class="text-sm font-medium text-muted">Kürzel *</label>
                        <input type="text" id="kuerzel" name="kuerzel" value="{{ old('kuerzel', $lehrberuf->kuerzel) }}" required maxlength="10"
                               class="mt-1 w-full rounded-xl border border-border bg-input text-text font-mono px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('kuerzel') border-red-400 @enderror">
                        @error('kuerzel')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="name" class="text-sm font-medium text-muted">Bezeichnung *</label>
                        <input type="text" id="name" name="name" value="{{ old('name', $lehrberuf->name) }}" required maxlength="200"
                               class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('name') border-red-400 @enderror">
                        @error('name')
                            <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center gap-3">
                        <input type="hidden" name="aktiv" value="0">
                        <input type="checkbox" id="aktiv" name="aktiv" value="1" @checked(old('aktiv', $lehrberuf->aktiv))
                               class="rounded-sm border-border text-accent focus:ring-ring">
                        <label for="aktiv" class="text-sm text-text">Lehrberuf aktiv</label>
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
