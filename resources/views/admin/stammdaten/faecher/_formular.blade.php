{{-- Formular «Fach» für Anlegen und Bearbeiten. $fach ist null beim Anlegen.
     Beim Anlegen folgt die Kategorie dem Track (BMS → BMS, ABU → ABU, sonst Fachunterricht), bis sie von Hand gewählt wird. --}}
@php($kategorieStandard = $fach?->kategorie_id ?? $kategorien->firstWhere('code', 'FACH')?->kategorie_id)
<form method="POST" action="{{ $fach ? route('admin.master-data.subjects.update', $fach->fach_id) : route('admin.master-data.subjects.store') }}"
      class="flex np-spalte flex-col gap-8" x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
    @csrf
    @if($fach)
        @method('PUT')
    @endif

    <section>
        <h2 class="mb-2 px-1 text-sm font-semibold text-text">{{ __('Fach') }}</h2>
        <div class="np-karte np-gruppe">
            <x-einstellung :label="__('Name')" fuer="name" name="name">
                <input type="text" id="name" name="name" value="{{ old('name', $fach?->name) }}" required maxlength="200"
                       class="np-feld w-72" @error('name') aria-invalid="true" aria-describedby="name-fehler" @enderror>
            </x-einstellung>
            <x-einstellung :label="__('Kürzel')" fuer="kurzname" name="kurzname">
                <input type="text" id="kurzname" name="kurzname" value="{{ old('kurzname', $fach?->kurzname) }}" required maxlength="50"
                       spellcheck="false" class="np-feld w-32" @error('kurzname') aria-invalid="true" aria-describedby="kurzname-fehler" @enderror>
            </x-einstellung>
        </div>
    </section>

    <section>
        <h2 class="mb-2 px-1 text-sm font-semibold text-text">{{ __('Einordnung') }}</h2>
        <div class="np-karte np-gruppe"
             x-data="{
                 track: {{ Js::from((string) old('track_typ', $fach?->track_typ ?? '')) }},
                 kategorieId: {{ Js::from((string) old('kategorie_id', $kategorieStandard)) }},
                 beruehrt: {{ Js::from($fach !== null || old('kategorie_id') !== null) }},
                 karte: {{ Js::from($kategorien->pluck('kategorie_id', 'code')) }},
                 aufTrackWechsel() {
                     if (this.beruehrt) return;
                     const code = this.track === 'BMS' ? 'BMS' : (this.track === 'ABU' ? 'ABU' : 'FACH');
                     if (this.karte[code]) this.kategorieId = String(this.karte[code]);
                 },
             }">
            <x-einstellung :label="__('Track')" name="track_typ">
                <x-segment-auswahl name="track_typ" :wert="old('track_typ', $fach?->track_typ ?? '')"
                                   :optionen="['' => __('Kein Track'), 'BMS' => 'BMS', 'ABU' => 'ABU']"
                                   x-model="track" x-on:change="aufTrackWechsel()" />
            </x-einstellung>
            <x-einstellung :label="__('Kategorie')" fuer="kategorie_id" name="kategorie_id">
                <select id="kategorie_id" name="kategorie_id" required x-model="kategorieId" @change="beruehrt = true"
                        class="np-feld w-56" @error('kategorie_id') aria-invalid="true" aria-describedby="kategorie_id-fehler" @enderror>
                    @unless($kategorieStandard)
                        <option value="">{{ __('Bitte wählen…') }}</option>
                    @endunless
                    @foreach($kategorien as $k)
                        <option value="{{ $k->kategorie_id }}" @selected((string) old('kategorie_id', $kategorieStandard) === (string) $k->kategorie_id)>{{ $k->name }}</option>
                    @endforeach
                </select>
            </x-einstellung>
        </div>
    </section>

    <section>
        <h2 class="mb-2 px-1 text-sm font-semibold text-text">{{ __('Bewertung') }}</h2>
        <div class="np-karte np-gruppe">
            <x-einstellung :label="__('Skala')" name="skala">
                <x-segment-auswahl name="skala" :wert="old('skala', $fach?->skala ?? 'note')"
                                   :optionen="['note' => __('Note 1–6'), 'stufe' => __('Stufe A/B/C')]" />
            </x-einstellung>
            <x-einstellung :label="__('Zählt in Schnitt und Promotion')" fuer="zaehlt" name="zaehlt">
                <input type="hidden" name="zaehlt" value="0">
                <input type="checkbox" role="switch" id="zaehlt" name="zaehlt" value="1" @checked(old('zaehlt', $fach?->zaehlt ?? 1)) class="np-schalter">
            </x-einstellung>
            @if($fach)
                <x-einstellung :label="__('Fach aktiv')" fuer="aktiv" name="aktiv">
                    <input type="hidden" name="aktiv" value="0">
                    <input type="checkbox" role="switch" id="aktiv" name="aktiv" value="1" @checked(old('aktiv', $fach->aktiv)) class="np-schalter">
                </x-einstellung>
            @endif
        </div>
    </section>

    <x-formular-aktionen :abbrechen="route('admin.master-data.subjects.index')">{{ $fach ? __('Änderungen speichern') : __('Fach anlegen') }}</x-formular-aktionen>
</form>
