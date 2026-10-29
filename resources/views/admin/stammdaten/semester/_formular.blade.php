{{-- Formular «Semester» für Anlegen und Bearbeiten. $semester ist null beim Anlegen (Sortierung leer = ans Ende). --}}
@php($wert = fn (string $k) => old($k, $semester?->{$k}))
<form method="POST" action="{{ $semester ? route('admin.master-data.semesters.update', $semester->semester_id) : route('admin.master-data.semesters.store') }}"
      class="flex np-spalte flex-col gap-8" x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
    @csrf
    @if($semester)
        @method('PUT')
    @endif

    <section>
        <h2 class="mb-2 px-1 text-sm font-semibold text-text">{{ __('Semester') }}</h2>
        <div class="np-karte np-gruppe">
            <x-einstellung :label="__('Bezeichnung')" fuer="bezeichnung" name="bezeichnung">
                <input type="text" id="bezeichnung" name="bezeichnung" value="{{ $wert('bezeichnung') }}" required maxlength="20"
                       @unless($semester) placeholder="26/27-1" @endunless
                       class="np-feld w-40 tabular-nums" @error('bezeichnung') aria-invalid="true" aria-describedby="bezeichnung-fehler" @enderror>
            </x-einstellung>
            <x-einstellung :label="__('Von')" fuer="start_datum" name="start_datum">
                <input type="date" id="start_datum" name="start_datum" value="{{ $wert('start_datum') }}" required
                       class="np-feld w-44 tabular-nums" @error('start_datum') aria-invalid="true" aria-describedby="start_datum-fehler" @enderror>
            </x-einstellung>
            <x-einstellung :label="__('Bis')" fuer="end_datum" name="end_datum">
                <input type="date" id="end_datum" name="end_datum" value="{{ $wert('end_datum') }}" required
                       class="np-feld w-44 tabular-nums" @error('end_datum') aria-invalid="true" aria-describedby="end_datum-fehler" @enderror>
            </x-einstellung>
            <x-einstellung :label="__('Sortierung')" fuer="sortierung" name="sortierung">
                <input type="number" id="sortierung" name="sortierung" value="{{ $wert('sortierung') }}" min="0"
                       @if($semester) required @else placeholder="{{ __('Automatisch') }}" @endif
                       class="np-feld w-28 text-right tabular-nums" @error('sortierung') aria-invalid="true" aria-describedby="sortierung-fehler" @enderror>
            </x-einstellung>
        </div>
    </section>

    <x-formular-aktionen :abbrechen="route('admin.master-data.semesters.index')">{{ $semester ? __('Änderungen speichern') : __('Semester anlegen') }}</x-formular-aktionen>
</form>
