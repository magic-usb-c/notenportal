<x-app-layout>
    <x-slot name="title">Neue Notenkategorie</x-slot>
    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-text">Neue Notenkategorie</h2>
            <a href="{{ route('admin.stammdaten.kategorien.index') }}"
               class="px-4 py-2 h-10 rounded-xl glass-btn text-text text-sm">
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

                <form method="POST" action="{{ route('admin.stammdaten.kategorien.store') }}" class="space-y-4" x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                    @csrf

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="text-sm font-medium text-muted">Code * <span class="text-xs font-normal">(max. 30 Zeichen)</span></label>
                            <input type="text" name="code" value="{{ old('code') }}" required maxlength="30"
                                   class="mt-1 w-full rounded-xl border border-border bg-input text-text font-mono px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('code') border-red-400 @enderror">
                            @error('code')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="text-sm font-medium text-muted">Name * <span class="text-xs font-normal">(max. 50 Zeichen)</span></label>
                            <input type="text" name="name" value="{{ old('name') }}" required maxlength="50"
                                   class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('name') border-red-400 @enderror">
                            @error('name')<p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="w-32">
                        <label class="text-sm font-medium text-muted">Sortierung</label>
                        <input type="number" name="sortierung" value="{{ old('sortierung') }}" min="0"
                               class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring">
                        <p class="mt-1 text-xs text-muted">Leer = automatisch</p>
                    </div>

                    <div class="pt-2 flex gap-3">
                        <button type="submit" :disabled="loading"
                                class="px-4 py-2 h-10 rounded-xl bg-accent text-white np-btn-primary disabled:opacity-60 disabled:cursor-not-allowed">
                            Speichern
                        </button>
                        <a href="{{ route('admin.stammdaten.kategorien.index') }}"
                           class="px-4 py-2 h-10 rounded-xl glass-btn text-text">
                            Abbrechen
                        </a>
                    </div>
                </form>

            </div>
        </div>
    </div>
</x-app-layout>
