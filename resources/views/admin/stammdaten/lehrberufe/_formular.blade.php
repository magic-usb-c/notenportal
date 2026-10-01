{{-- Formular «Lehrberuf» für Anlegen und Bearbeiten. $lehrberuf ist null beim Anlegen. --}}
@php($wert = fn (string $k) => old($k, $lehrberuf?->{$k}))
<form method="POST" action="{{ $lehrberuf ? route('admin.master-data.professions.update', $lehrberuf->lehrberuf_id) : route('admin.master-data.professions.store') }}"
      class="flex np-spalte flex-col gap-8" x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
    @csrf
    @if($lehrberuf)
        @method('PUT')
    @endif

    <section>
        <h2 class="mb-2 px-1 text-sm font-semibold text-text">{{ __('Lehrberuf') }}</h2>
        <div class="np-karte np-gruppe">
            <x-einstellung :label="__('Bezeichnung')" fuer="name" name="name">
                <input type="text" id="name" name="name" value="{{ $wert('name') }}" required maxlength="200"
                       class="np-feld w-80" @error('name') aria-invalid="true" aria-describedby="name-fehler" @enderror>
            </x-einstellung>
            <x-einstellung :label="__('Kürzel')" fuer="kuerzel" name="kuerzel">
                <input type="text" id="kuerzel" name="kuerzel" value="{{ $wert('kuerzel') }}" required maxlength="10" spellcheck="false"
                       class="np-feld w-32" @error('kuerzel') aria-invalid="true" aria-describedby="kuerzel-fehler" @enderror>
            </x-einstellung>
            @if($lehrberuf)
                <x-einstellung :label="__('Lehrberuf aktiv')" fuer="aktiv" name="aktiv">
                    <input type="hidden" name="aktiv" value="0">
                    <input type="checkbox" role="switch" id="aktiv" name="aktiv" value="1" @checked($wert('aktiv')) class="np-schalter">
                </x-einstellung>
            @endif
        </div>
    </section>

    <x-formular-aktionen :abbrechen="route('admin.master-data.professions.index')">{{ $lehrberuf ? __('Änderungen speichern') : __('Lehrberuf anlegen') }}</x-formular-aktionen>
</form>
