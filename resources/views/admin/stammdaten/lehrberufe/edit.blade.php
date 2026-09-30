<x-app-layout>
    <x-slot name="title">{{ __('Lehrberuf bearbeiten') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :zurueck="route('admin.master-data.professions.index')" :titel="__('Lehrberuf bearbeiten')" schmal>
            <x-slot:aktionen>
                <a href="{{ route('admin.master-data.professions.show', $lehrberuf->lehrberuf_id) }}"
                   class="np-knopf np-knopf-sekundaer">
                    {{ __('Module/Fächer') }}
                </a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-4 sm:px-6 lg:px-8">
            <div class="np-karte max-w-3xl p-6">
                <form method="POST" action="{{ route('admin.master-data.professions.update', $lehrberuf->lehrberuf_id) }}" class="space-y-5" x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                    @csrf
                    @method('PUT')

                    <div>
                        <label for="kuerzel" class="text-sm font-medium text-text">{{ __('Kürzel *') }}</label>
                        <input type="text" id="kuerzel" name="kuerzel" value="{{ old('kuerzel', $lehrberuf->kuerzel) }}" required maxlength="10"
                               class="np-feld mt-1 tabular-nums @error('kuerzel') border-note-ungenuegend @enderror">
                        @error('kuerzel')
                            <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="name" class="text-sm font-medium text-text">{{ __('Bezeichnung *') }}</label>
                        <input type="text" id="name" name="name" value="{{ old('name', $lehrberuf->name) }}" required maxlength="200"
                               class="np-feld mt-1 @error('name') border-note-ungenuegend @enderror">
                        @error('name')
                            <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex items-center gap-3">
                        <input type="hidden" name="aktiv" value="0">
                        <input type="checkbox" role="switch" id="aktiv" name="aktiv" value="1" @checked(old('aktiv', $lehrberuf->aktiv))
                               class="np-schalter">
                        <label for="aktiv" class="text-sm text-text">{{ __('Lehrberuf aktiv') }}</label>
                    </div>

                    <x-formular-aktionen :abbrechen="route('admin.master-data.professions.index')">{{ __('Änderungen speichern') }}</x-formular-aktionen>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
