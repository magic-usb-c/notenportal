<x-app-layout>
    <x-slot name="title">{{ __('Neuer Lehrberuf') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :titel="__('Neuer Lehrberuf')" schmal>
            <x-slot:aktionen>
                <a href="{{ route('admin.master-data.professions.index') }}"
                   class="inline-flex h-9 items-center gap-2 rounded-lg glass-btn px-3.5 text-sm font-medium text-text">
                    {{ __('Zurück') }}
                </a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl rounded-xl border border-border bg-card p-6">
                <form method="POST" action="{{ route('admin.master-data.professions.store') }}" class="space-y-5" x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                    @csrf

                    <div>
                        <label for="kuerzel" class="text-sm font-medium text-text">{{ __('Kürzel *') }}</label>
                        <input type="text" id="kuerzel" name="kuerzel" value="{{ old('kuerzel') }}" required maxlength="10"
                               class="mt-1 w-full rounded-xl border border-border bg-input text-text font-mono px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('kuerzel') border-note-ungenuegend @enderror">
                        @error('kuerzel')
                            <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="name" class="text-sm font-medium text-text">{{ __('Name *') }}</label>
                        <input type="text" id="name" name="name" value="{{ old('name') }}" required maxlength="200"
                               class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring @error('name') border-note-ungenuegend @enderror">
                        @error('name')
                            <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="pt-2">
                        <button type="submit" :disabled="loading"
                                class="w-full px-4 py-2 h-10 rounded-xl bg-accent text-accent-contrast np-btn-primary font-medium disabled:opacity-60 disabled:cursor-not-allowed">
                            {{ __('Lehrberuf anlegen') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
