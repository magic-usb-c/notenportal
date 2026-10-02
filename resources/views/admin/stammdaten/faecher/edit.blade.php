<x-app-layout>
    <x-slot name="title">{{ __('Fach bearbeiten') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :zurueck="route('admin.master-data.subjects.index')" :titel="__('Fach bearbeiten')" schmal />
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-8">
            @include('admin.stammdaten.faecher._formular')

            <section class="mt-10 np-spalte">
                <div class="np-karte np-gruppe">
                    <x-einstellung :label="__('Fach löschen')"
                                   :hinweis="$notenAnzahl > 0 ? __('Das Fach enthält Noten oder Prüfungen und kann nicht gelöscht werden.') : null">
                        @if($notenAnzahl > 0)
                            <button type="button" disabled class="np-knopf np-knopf-gefahr">{{ __('Löschen') }}</button>
                        @else
                            <form method="POST" action="{{ route('admin.master-data.subjects.destroy', $fach->fach_id) }}"
                                  data-bestaetigen="{{ __('Fach «:name» endgültig löschen?', ['name' => $fach->name]) }}" data-bestaetigen-knopf="{{ __('Löschen') }}"
                                  x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                                @csrf
                                @method('DELETE')
                                <button type="submit" :disabled="loading" class="np-knopf np-knopf-gefahr">{{ __('Löschen') }}</button>
                            </form>
                        @endif
                    </x-einstellung>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
