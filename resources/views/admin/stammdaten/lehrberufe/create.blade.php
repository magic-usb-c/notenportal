<x-app-layout>
    <x-slot name="title">{{ __('Neuer Lehrberuf') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :zurueck="route('admin.master-data.professions.index')" :titel="__('Neuer Lehrberuf')" schmal>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-8">
            <div class="np-karte max-w-3xl p-6">
                <form method="POST" action="{{ route('admin.master-data.professions.store') }}" class="space-y-5" x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                    @csrf

                    <div>
                        <label for="kuerzel" class="text-sm font-medium text-text">{{ __('Kürzel *') }}</label>
                        <input type="text" id="kuerzel" name="kuerzel" value="{{ old('kuerzel') }}" required maxlength="10"
                               class="np-feld mt-1 tabular-nums @error('kuerzel') border-note-ungenuegend @enderror">
                        @error('kuerzel')
                            <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="name" class="text-sm font-medium text-text">{{ __('Name *') }}</label>
                        <input type="text" id="name" name="name" value="{{ old('name') }}" required maxlength="200"
                               class="np-feld mt-1 @error('name') border-note-ungenuegend @enderror">
                        @error('name')
                            <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
                        @enderror
                    </div>

                    <x-formular-aktionen :abbrechen="route('admin.master-data.professions.index')">{{ __('Lehrberuf anlegen') }}</x-formular-aktionen>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
