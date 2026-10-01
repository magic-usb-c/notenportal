<x-app-layout>
    <x-slot name="title">{{ __('Semester bearbeiten') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :zurueck="route('admin.master-data.semesters.index')" :titel="__('Semester bearbeiten')" schmal />
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-8">
            @include('admin.stammdaten.semester._formular')

            <section class="mt-10 np-spalte">
                <div class="np-karte np-gruppe">
                    <x-einstellung :label="__('Semester löschen')"
                                   :hinweis="$belegt ? __('Das Semester enthält Noten, Tracks oder Dokumente und kann nicht gelöscht werden.') : null">
                        @if($belegt)
                            <button type="button" disabled class="np-knopf np-knopf-gefahr">{{ __('Löschen') }}</button>
                        @else
                            <form method="POST" action="{{ route('admin.master-data.semesters.destroy', $semester->semester_id) }}"
                                  data-bestaetigen="{{ __('Semester :bezeichnung löschen?', ['bezeichnung' => $semester->bezeichnung]) }}" data-bestaetigen-knopf="{{ __('Löschen') }}"
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
