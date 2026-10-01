{{-- Formular «Lernende» für Erfassen und Bearbeiten. $lernender ist null beim Erfassen; Track und Berufsbildner
     gibt es nur beim Erfassen (danach im Cockpit). Das Startsemester folgt dem Lehrbeginn, bis es von Hand gewählt wird. --}}
@php
    $lehrberufStandard = $lernender?->lehrberuf_id ?? ($lehrberufe->count() === 1 ? $lehrberufe->first()->lehrberuf_id : null);
    $datum = 'np-feld w-44 tabular-nums';
@endphp
<form method="POST" action="{{ $lernender ? route($bereich.'.learners.update', $lernender->lernender_id) : route($bereich.'.learners.store') }}"
      class="flex np-spalte flex-col gap-8" x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
    @csrf
    @if($lernender)
        @method('PUT')
    @endif

    @include('admin.benutzer._person', ['user' => $lernender?->benutzer])

    <section @unless($lernender)
                 x-data="{
                     lehrbeginn: {{ Js::from((string) old('lehrbeginn', '')) }},
                     track: {{ Js::from((string) old('track_typ', '')) }},
                     semesterId: {{ Js::from((string) old('track_semester_id', '')) }},
                     beruehrt: {{ Js::from(old('track_semester_id') !== null) }},
                     zeitraeume: {{ Js::from($semester->map(fn ($s) => [(string) $s->semester_id, $s->start_datum, $s->end_datum])->values()) }},
                     semesterNachLehrbeginn() {
                         if (this.beruehrt || !this.lehrbeginn) return;
                         const treffer = this.zeitraeume.find(([, start, ende]) => start <= this.lehrbeginn && this.lehrbeginn <= ende);
                         if (treffer) this.semesterId = treffer[0];
                     },
                 }"
                 x-init="semesterNachLehrbeginn()"
             @endunless>
        <h2 class="mb-2 px-1 text-sm font-semibold text-text">{{ __('Ausbildung') }}</h2>
        <div class="np-karte np-gruppe">
            <x-einstellung :label="__('Lehrberuf')" fuer="lehrberuf_id" name="lehrberuf_id">
                <select id="lehrberuf_id" name="lehrberuf_id" required class="np-feld w-80"
                        @error('lehrberuf_id') aria-invalid="true" aria-describedby="lehrberuf_id-fehler" @enderror>
                    @if($lehrberufStandard === null)
                        <option value="">{{ __('Bitte wählen…') }}</option>
                    @endif
                    @foreach($lehrberufe as $lb)
                        <option value="{{ $lb->lehrberuf_id }}" @selected((string) old('lehrberuf_id', $lehrberufStandard) === (string) $lb->lehrberuf_id)>{{ $lb->name }}</option>
                    @endforeach
                </select>
            </x-einstellung>
            <x-einstellung :label="__('Lehrbeginn')" fuer="lehrbeginn" name="lehrbeginn">
                <input type="date" id="lehrbeginn" name="lehrbeginn" required class="{{ $datum }}"
                       value="{{ old('lehrbeginn', $lernender?->lehrbeginn?->format('Y-m-d')) }}"
                       @unless($lernender) x-model="lehrbeginn" x-on:change="semesterNachLehrbeginn()" @endunless
                       @error('lehrbeginn') aria-invalid="true" aria-describedby="lehrbeginn-fehler" @enderror>
            </x-einstellung>
            <x-einstellung :label="__('Lehrende')" fuer="lehrende" name="lehrende">
                <input type="date" id="lehrende" name="lehrende" class="{{ $datum }}"
                       value="{{ old('lehrende', $lernender?->lehrende?->format('Y-m-d')) }}"
                       @error('lehrende') aria-invalid="true" aria-describedby="lehrende-fehler" @enderror>
            </x-einstellung>
            @unless($lernender)
                <x-einstellung :label="__('Schul-Track')" name="track_typ">
                    <x-segment-auswahl name="track_typ" :wert="old('track_typ', '')" x-model="track" x-on:change="semesterNachLehrbeginn()"
                                       :optionen="['' => __('Kein Track'), 'BMS' => 'BMS', 'ABU' => 'ABU']" />
                </x-einstellung>
                <x-einstellung :label="__('Startsemester')" fuer="track_semester_id" name="track_semester_id" x-show="track" x-cloak>
                    <select id="track_semester_id" name="track_semester_id" x-model="semesterId" @change="beruehrt = true"
                            :required="track !== ''" :disabled="track === ''" class="np-feld w-56"
                            @error('track_semester_id') aria-invalid="true" aria-describedby="track_semester_id-fehler" @enderror>
                        <option value="">{{ __('Bitte wählen…') }}</option>
                        @foreach($semester as $s)
                            <option value="{{ $s->semester_id }}">{{ $s->bezeichnung }}</option>
                        @endforeach
                    </select>
                </x-einstellung>
                @isset($berufsbildnerListe)
                    <x-einstellung :label="__('Berufsbildner')" fuer="berufsbildner_id" name="berufsbildner_id">
                        <select id="berufsbildner_id" name="berufsbildner_id" class="np-feld w-56"
                                @error('berufsbildner_id') aria-invalid="true" aria-describedby="berufsbildner_id-fehler" @enderror>
                            <option value="">{{ __('Keiner') }}</option>
                            @foreach($berufsbildnerListe as $bb)
                                <option value="{{ $bb->berufsbildner_id }}" @selected((string) old('berufsbildner_id') === (string) $bb->berufsbildner_id)>
                                    {{ $bb->benutzer->nachname }} {{ $bb->benutzer->vorname }}
                                </option>
                            @endforeach
                        </select>
                    </x-einstellung>
                @endisset
            @endunless
        </div>
    </section>

    <section>
        <h2 class="mb-2 px-1 text-sm font-semibold text-text">{{ __('Intern') }}</h2>
        <div class="np-karte np-gruppe">
            <x-einstellung :label="__('Bemerkung')" fuer="bemerkung" name="bemerkung" gestapelt>
                <textarea id="bemerkung" name="bemerkung" rows="4" maxlength="5000" placeholder="{{ __('Optional') }}" class="np-feld w-full"
                          @error('bemerkung') aria-invalid="true" aria-describedby="bemerkung-fehler" @enderror>{{ old('bemerkung', $lernender?->bemerkung) }}</textarea>
            </x-einstellung>
        </div>
        <p class="mt-2 px-1 text-xs text-muted">{{ __('Für Lernende nicht sichtbar.') }}</p>
    </section>

    <x-formular-aktionen :abbrechen="$lernender ? route($bereich.'.learners.show', $lernender->lernender_id) : route($bereich.'.learners.index')">
        {{ $lernender ? __('Änderungen speichern') : __('Lernende erfassen') }}
    </x-formular-aktionen>
</form>
