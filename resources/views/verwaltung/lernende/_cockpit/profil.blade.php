{{-- Profil & Betreuung: Stammdaten, Konto, Betreuung, Schul-Tracks – Formulare, Speichern am Formularende. --}}
@php
    $benutzer = $lernender->benutzer;
    $datum = fn ($d) => $d ? \Carbon\Carbon::parse($d)->format('d.m.Y') : '–';
    $label = 'text-xs font-medium text-muted';
    $feld = 'np-feld mt-1';
    $heute = today();
@endphp
<div class="grid grid-cols-1 lg:grid-cols-12 gap-5">

    {{-- Stammdaten --}}
    <x-karte :titel="__('Profil')" class="lg:col-span-8">
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
            <div><dt class="{{ $label }}">{{ __('E-Mail') }}</dt><dd class="text-text break-all"><a href="mailto:{{ $benutzer->email }}" class="hover:text-accent-text">{{ $benutzer->email }}</a></dd></div>
            <div><dt class="{{ $label }}">{{ __('Benutzername') }}</dt><dd class="text-text tabular-nums">{{ $benutzer->benutzername }}</dd></div>
            <div><dt class="{{ $label }}">{{ __('Lehrbeginn') }}</dt><dd class="text-text">{{ $datum($lernender->lehrbeginn) }}</dd></div>
            <div><dt class="{{ $label }}">{{ __('Lehrende') }}</dt><dd class="text-text">{{ $datum($lernender->lehrende) }}</dd></div>
            <div><dt class="{{ $label }}">{{ __('Klasse Schule') }}</dt><dd class="text-text">{{ $lernender->klasse_schule ?: '–' }}</dd></div>
            <div><dt class="{{ $label }}">{{ __('Klasse BMS') }}</dt><dd class="text-text">{{ $lernender->klasse_bms ?: '–' }}</dd></div>
            <div class="sm:col-span-2">
                <dt class="{{ $label }}">{{ __('Bemerkung (intern)') }}</dt>
                <dd class="text-text whitespace-pre-line">{{ $lernender->bemerkung ?: '–' }}</dd>
            </div>
        </dl>
    </x-karte>

    {{-- Konto --}}
    <x-karte :titel="__('Konto')" class="lg:col-span-4">
        <div class="flex flex-col gap-3">
            <div class="flex items-center gap-2 flex-wrap">
                @if($benutzer->aktiv)
                    <x-status status="gut" :text="__('Aktiv')" />
                @else
                    <x-status status="neutral" :text="__('Inaktiv')" />
                @endif
                @if($benutzer->passwort_wechsel_noetig)
                    <span class="np-marke text-muted">{{ __('Passwortwechsel ausstehend') }}</span>
                @endif
            </div>
            @can('verwalten', $lernender)
                <div class="flex items-center gap-2 flex-wrap">
                    <form method="POST" action="{{ route("{$bereich}.learners.account.password", $lernender->lernender_id) }}"
                          x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true"
                          data-bestaetigen="{{ __('Neues Startpasswort erzeugen?') }}" data-bestaetigen-text="{{ __('Das bisherige Passwort wird ungültig.') }}" data-bestaetigen-knopf="{{ __('Zurücksetzen') }}">
                        @csrf
                        <button type="submit" :disabled="loading" class="np-knopf np-knopf-sekundaer">{{ __('Passwort zurücksetzen') }}</button>
                    </form>
                    <form method="POST" action="{{ route("{$bereich}.learners.account.active", $lernender->lernender_id) }}"
                          x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true"
                          @if($benutzer->aktiv) data-bestaetigen="{{ __('Konto deaktivieren?') }}" data-bestaetigen-text="{{ __('Anmelden ist danach nicht mehr möglich.') }}" data-bestaetigen-knopf="{{ __('Deaktivieren') }}" @endif>
                        @csrf
                        <button type="submit" :disabled="loading" class="np-knopf {{ $benutzer->aktiv ? 'np-knopf-gefahr' : 'np-knopf-sekundaer' }}">
                            {{ $benutzer->aktiv ? __('Deaktivieren') : __('Aktivieren') }}
                        </button>
                    </form>
                </div>
            @endcan
        </div>
    </x-karte>

    {{-- Betreuungen --}}
    <div class="np-karte lg:col-span-6 overflow-hidden">
        <div class="px-5 py-4 border-b border-border"><h3 class="font-semibold text-text text-sm">{{ __('Betreuung') }}</h3></div>
        <div class="divide-y divide-border">
            @forelse($lernender->betreuungen as $bt)
                @php $offen = ! $bt->gueltig_bis || $bt->gueltig_bis->gte($heute); @endphp
                <div class="px-5 py-3 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <div class="text-sm font-medium text-text">
                            {{ $bt->berufsbildner?->benutzer?->vorname }} {{ $bt->berufsbildner?->benutzer?->nachname }}
                        </div>
                        <div class="text-xs text-muted">{{ $bt->gueltig_von->format('d.m.Y') }} – {{ $bt->gueltig_bis?->format('d.m.Y') ?? __('offen') }}</div>
                    </div>
                    @if($offen)
                        @can('betreuungVerwalten', $lernender)
                            @php $betreuungBeendenModal = 'betreuung-beenden-'.$bt->betreuung_id; @endphp
                            <form id="{{ $betreuungBeendenModal }}-form" method="POST" action="{{ route("{$bereich}.supervisions.end", [$lernender->lernender_id, $bt->betreuung_id]) }}"
                                  x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                                @csrf
                                <button type="button" @click="$dispatch('open-modal', '{{ $betreuungBeendenModal }}')" :disabled="loading"
                                        class="np-knopf np-knopf-gefahr np-knopf-klein">{{ __('Beenden') }}</button>
                            </form>
                            <x-modal :name="$betreuungBeendenModal" maxWidth="sm">
                                <div class="p-6" role="dialog" aria-modal="true" aria-labelledby="{{ $betreuungBeendenModal }}-titel">
                                    <h3 id="{{ $betreuungBeendenModal }}-titel" class="font-semibold text-text">{{ __('Betreuung beenden?') }}</h3>
                                    <p class="mt-2 text-sm text-muted">{{ __('Die Betreuung durch :name endet ab heute.', ['name' => $bt->berufsbildner?->benutzer?->vorname.' '.$bt->berufsbildner?->benutzer?->nachname]) }}</p>
                                    <div class="mt-5 flex justify-end gap-2">
                                        <button type="button" @click="$dispatch('close-modal', '{{ $betreuungBeendenModal }}')"
                                                class="np-knopf np-knopf-sekundaer">{{ __('Abbrechen') }}</button>
                                        <button type="button"
                                                @click="document.getElementById('{{ $betreuungBeendenModal }}-form').requestSubmit(); $dispatch('close-modal', '{{ $betreuungBeendenModal }}')"
                                                class="inline-flex h-9 items-center rounded-lg bg-note-ungenuegend px-3.5 text-sm font-medium text-accent-contrast">{{ __('Beenden') }}</button>
                                    </div>
                                </div>
                            </x-modal>
                        @endcan
                    @endif
                </div>
            @empty
                <div class="px-5 py-5 text-sm text-muted text-center">{{ __('Keine Betreuung.') }}</div>
            @endforelse
        </div>
        @can('betreuungVerwalten', $lernender)
            <form id="betreuung-zuweisen-form" method="POST" action="{{ route("{$bereich}.learners.supervision.store", $lernender->lernender_id) }}"
                  class="px-5 py-4 border-t border-border bg-bg/40 grid grid-cols-1 sm:grid-cols-3 gap-3 items-end"
                  x-data="{ loading: false }"
                  @submit="if ($event.defaultPrevented) return; if (!$el.dataset.bestaetigt) { $event.preventDefault(); $dispatch('open-modal', 'betreuung-zuweisen'); } else { loading = true; }">
                @csrf
                <div class="sm:col-span-2">
                    <label for="berufsbildner_id" class="{{ $label }}">{{ __('Berufsbildner *') }}</label>
                    <select id="berufsbildner_id" name="berufsbildner_id" required class="{{ $feld }}">
                        <option value="">{{ __('Bitte wählen') }}</option>
                        @foreach($berufsbildnerListe as $bb)
                            <option value="{{ $bb->berufsbildner_id }}" @selected(old('berufsbildner_id') == $bb->berufsbildner_id)>
                                {{ $bb->benutzer->nachname }} {{ $bb->benutzer->vorname }}
                            </option>
                        @endforeach
                    </select>
                    @error('berufsbildner_id')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="gueltig_von" class="{{ $label }}">{{ __('Ab *') }}</label>
                    <input id="gueltig_von" type="date" name="gueltig_von" required value="{{ old('gueltig_von', now()->toDateString()) }}" class="{{ $feld }}">
                    @error('gueltig_von')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                </div>
                <button type="submit" :disabled="loading"
                        class="np-knopf np-knopf-primaer sm:col-span-3">{{ __('Zuweisen') }}</button>
            </form>
            <x-modal name="betreuung-zuweisen" maxWidth="sm">
                <div class="p-6" role="dialog" aria-modal="true" aria-labelledby="betreuung-zuweisen-titel">
                    <h3 id="betreuung-zuweisen-titel" class="font-semibold text-text">{{ __('Betreuung zuweisen?') }}</h3>
                    <p class="mt-2 text-sm text-muted">{{ __('Die bisherige Betreuung endet am Vortag.') }}</p>
                    <div class="mt-5 flex justify-end gap-2">
                        <button type="button" @click="$dispatch('close-modal', 'betreuung-zuweisen')"
                                class="np-knopf np-knopf-sekundaer">{{ __('Abbrechen') }}</button>
                        <button type="button"
                                @click="const f = document.getElementById('betreuung-zuweisen-form'); f.dataset.bestaetigt = '1'; f.requestSubmit(); $dispatch('close-modal', 'betreuung-zuweisen')"
                                class="np-knopf np-knopf-primaer">{{ __('Zuweisen') }}</button>
                    </div>
                </div>
            </x-modal>
        @endcan
    </div>

    {{-- Tracks --}}
    <div class="np-karte lg:col-span-6 overflow-hidden">
        <div class="px-5 py-4 border-b border-border"><h3 class="font-semibold text-text text-sm">{{ __('Schul-Tracks') }}</h3></div>
        <div class="divide-y divide-border">
            @forelse($lernender->tracks as $t)
                <div class="px-5 py-3 flex flex-wrap items-center gap-3">
                    <span class="inline-flex px-2.5 py-1 rounded-lg text-xs font-bold {{ $t->end_datum ? 'bg-bg text-muted border border-border' : 'bg-accent/10 text-accent-text' }}">{{ $t->track_typ }}</span>
                    <div class="flex-1 min-w-0 text-sm text-text">
                        {{ __('ab :datum', ['datum' => $t->start_datum->format('d.m.Y')]) }}
                        @if($t->startSemester)<span class="text-muted">({{ $t->startSemester->bezeichnung }})</span>@endif
                        @if($t->end_datum)
                            <span class="text-muted">– {{ $t->end_datum->format('d.m.Y') }}{{ $t->endSemester ? ' ('.$t->endSemester->bezeichnung.')' : '' }}</span>
                        @endif
                    </div>
                    @if(! $t->end_datum)
                        @can('verwalten', $lernender)
                            <form method="POST" action="{{ route("{$bereich}.tracks.end", [$lernender->lernender_id, $t->lernender_track_id]) }}"
                                  class="flex items-center gap-2"
                                  x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true"
                                  data-bestaetigen="{{ __('Track :typ beenden?', ['typ' => $t->track_typ]) }}" data-bestaetigen-knopf="{{ __('Beenden') }}">
                                @csrf
                                <label for="end_semester_{{ $t->lernender_track_id }}" class="sr-only">{{ __('Endsemester') }}</label>
                                <select id="end_semester_{{ $t->lernender_track_id }}" name="end_semester_id" required
                                        class="np-feld np-feld-klein w-auto shrink-0">
                                    @foreach($semesterListe as $s)
                                        <option value="{{ $s->semester_id }}">{{ $s->bezeichnung }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" :disabled="loading"
                                        class="np-knopf np-knopf-gefahr np-knopf-klein">{{ __('Beenden') }}</button>
                            </form>
                        @endcan
                    @endif
                </div>
            @empty
                <div class="px-5 py-5 text-sm text-muted text-center">{{ __('Kein Track.') }}</div>
            @endforelse
        </div>
        @can('verwalten', $lernender)
            <form method="POST" action="{{ route("{$bereich}.learners.tracks.store", $lernender->lernender_id) }}"
                  class="px-5 py-4 border-t border-border bg-bg/40 grid grid-cols-1 sm:grid-cols-3 gap-3 items-end"
                  x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                @csrf
                <div>
                    <label for="track_typ" class="{{ $label }}">{{ __('Track *') }}</label>
                    <select id="track_typ" name="track_typ" required class="{{ $feld }}">
                        <option value="BMS">BMS</option>
                        <option value="ABU">ABU</option>
                    </select>
                    @error('track_typ')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="start_datum" class="{{ $label }}">{{ __('Start *') }}</label>
                    <input id="start_datum" type="date" name="start_datum" required value="{{ old('start_datum', now()->toDateString()) }}" class="{{ $feld }}">
                    @error('start_datum')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="start_semester_id" class="{{ $label }}">{{ __('Semester *') }}</label>
                    <select id="start_semester_id" name="start_semester_id" required class="{{ $feld }}">
                        @foreach($semesterListe as $s)
                            <option value="{{ $s->semester_id }}">{{ $s->bezeichnung }}</option>
                        @endforeach
                    </select>
                    @error('start_semester_id')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                </div>
                <button type="submit" :disabled="loading"
                        class="np-knopf np-knopf-primaer sm:col-span-3">{{ __('Track starten') }}</button>
            </form>
        @endcan
    </div>
</div>
