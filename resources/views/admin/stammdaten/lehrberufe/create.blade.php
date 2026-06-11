<x-app-layout>
    <x-slot name="title">Neuer Lehrberuf</x-slot>
    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-text">Neuer Lehrberuf</h2>
            <a href="{{ route('admin.stammdaten.lehrberufe.index') }}"
               class="px-4 py-2 h-10 rounded-xl glass-btn text-text text-sm">
                Zurück
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="glass rounded-2xl p-6">
                <form method="POST" action="{{ route('admin.stammdaten.lehrberufe.store') }}" class="space-y-5">
                    @csrf

                    <div>
                        <label class="text-sm font-medium text-muted">Kürzel * <span class="text-xs font-normal">(max. 10 Zeichen, wird gross gespeichert)</span></label>
                        <input type="text" name="kuerzel" value="{{ old('kuerzel') }}" required maxlength="10"
                               class="mt-1 w-full rounded-xl border border-border bg-input text-text font-mono px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('kuerzel') border-red-400 @enderror">
                        @error('kuerzel')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="text-sm font-medium text-muted">Name *</label>
                        <input type="text" name="name" value="{{ old('name') }}" required maxlength="200"
                               class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('name') border-red-400 @enderror">
                        @error('name')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="pt-2">
                        <button type="submit"
                                class="w-full px-4 py-2 h-10 rounded-xl bg-accent text-white np-btn-primary font-medium">
                            Lehrberuf anlegen
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
