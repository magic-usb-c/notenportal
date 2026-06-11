<x-app-layout>
    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-text">Notenkategorie bearbeiten</h2>
            <a href="{{ route('admin.stammdaten.kategorien.index') }}"
               class="px-4 py-2 h-10 rounded-xl bg-card text-text border border-border hover:bg-bg text-sm">
                Zurück
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="glass rounded-2xl p-6">

                @if ($errors->any())
                    <div class="mb-4 rounded-xl border border-red-300 bg-red-50 dark:bg-red-900/20 dark:border-red-700 px-4 py-3 text-sm text-red-800 dark:text-red-200">
                        <ul class="list-disc pl-5 space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST"
                      action="{{ route('admin.stammdaten.kategorien.update', $kategorie->kategorie_id) }}"
                      class="space-y-4">
                    @csrf
                    @method('PUT')

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-sm font-medium text-muted">Code *</label>
                            <input type="text" name="code" value="{{ old('code', $kategorie->code) }}" required maxlength="30"
                                   class="mt-1 w-full rounded-xl border border-border bg-input text-text font-mono px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('code') border-red-400 @enderror">
                            @error('code')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="text-sm font-medium text-muted">Name *</label>
                            <input type="text" name="name" value="{{ old('name', $kategorie->name) }}" required maxlength="50"
                                   class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('name') border-red-400 @enderror">
                            @error('name')<p class="mt-1 text-xs text-red-500">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="w-32">
                        <label class="text-sm font-medium text-muted">Sortierung</label>
                        <input type="number" name="sortierung" value="{{ old('sortierung', $kategorie->sortierung) }}" min="0"
                               class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring">
                    </div>

                    <div class="flex items-center gap-3">
                        <input type="hidden" name="aktiv" value="0">
                        <input type="checkbox" name="aktiv" value="1" id="aktiv"
                               @checked(old('aktiv', $kategorie->aktiv))
                               class="rounded border-border text-accent focus:ring-ring">
                        <label for="aktiv" class="text-sm text-text">Aktiv</label>
                    </div>

                    <div class="pt-2 flex gap-3">
                        <button type="submit"
                                class="px-4 py-2 h-10 rounded-xl bg-accent text-white np-btn-primary">
                            Speichern
                        </button>
                        <a href="{{ route('admin.stammdaten.kategorien.index') }}"
                           class="px-4 py-2 h-10 rounded-xl bg-card text-text border border-border hover:bg-bg">
                            Abbrechen
                        </a>
                    </div>
                </form>

            </div>
        </div>
    </div>
</x-app-layout>
