{{-- Profil & Betreuung: Stammdaten, Konto, Betreuung, Schul-Tracks – Formulare, Speichern am Formularende. --}}
@php
    $benutzer = $lernender->benutzer;
    $datum = fn ($d) => $d ? \Carbon\Carbon::parse($d)->format('d.m.Y') : '–';
    $label = 'text-xs font-medium text-muted';
    $feld = 'np-feld mt-1';
    $pflicht = '<span class="text-note-ungenuegend">*</span>';
    $heute = today();
    $konfiguration = \App\Services\Auswertung\Konfiguration::ausDb();
    // Semester aus Sicht dieser Person («3. Semester»), vor Lehrbeginn neutral
    $semesterName = fn (?int $id) => $konfiguration->semesterName($id, (int) $lernender->lernender_id);
    $hatOffeneBetreuung = $lernender->betreuungen->contains(fn ($bt) => ! $bt->gueltig_bis || $bt->gueltig_bis->gte($heute));
@endphp
<div class="grid grid-cols-12 gap-5">

    {{-- Stammdaten --}}
    <x-karte :titel="__('Profil')" class="col-span-8">
        <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
            <div><dt class="{{ $label }}">{{ __('E-Mail') }}</dt><dd class="text-text break-all"><a href="mailto:{{ $benutzer->email }}" class="hover:text-accent-text">{{ $benutzer->email }}</a></dd></div>
            <div><dt class="{{ $label }}">{{ __('Benutzername') }}</dt><dd class="text-text tabular-nums">{{ $benutzer->benutzername }}</dd></div>
            <div><dt class="{{ $label }}">{{ __('Lehrbeginn') }}</dt><dd class="text-text">{{ $datum($lernender->lehrbeginn) }}</dd></div>
            <div><dt class="{{ $label }}">{{ __('Lehrende') }}</dt><dd class="text-text">{{ $datum($lernender->lehrende) }}</dd></div>
            <div><dt class="{{ $label }}">{{ __('Klasse Schule') }}</dt><dd class="text-text">{{ $lernender->klasse_schule ?: '–' }}</dd></div>
            <div><dt class="{{ $label }}">{{ __('Klasse BMS') }}</dt><dd class="text-text">{{ $lernender->klasse_bms ?: '–' }}</dd></div>
            <div class="col-span-2">
                <dt class="{{ $label }}">{{ __('Bemerkung (intern)') }}</dt>
                <dd class="text-text whitespace-pre-line">{{ $lernender->bemerkung ?: '–' }}</dd>
            </div>
        </dl>
    </x-karte>

    {{-- Konto --}}
    <x-karte :titel="__('Konto')" class="col-span-4">
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
    <x-karte :titel="__('Betreuung')" :polster="false" class="col-span-6">
        <div class="flex h-full flex-col">
        <div class="mt-2 divide-y divide-border border-t border-border">
            @forelse($lernender->betreuungen as $bt)
                @php $offen = ! $bt->gueltig_bis || $bt->gueltig_bis->gte($heute); @endphp
                <div class="flex min-h-14 items-center justify-between gap-3 px-5 py-2.5">
                    <div class="min-w-0">
                        <div class="text-sm font-medium text-text">
                            {{ $bt->berufsbildner?->benutzer?->vorname }} {{ $bt->berufsbildner?->benutzer?->nachname }}
                        </div>
                        <div class="text-xs text-muted">{{ $bt->gueltig_von->format('d.m.Y') }} – {{ $bt->gueltig_bis?->format('d.m.Y') ?? __('offen') }}</div>
                    </div>
                    @if($offen)
                        @can('betreuungVerwalten', $lernender)
                            <form method="POST" action="{{ route("{$bereich}.supervisions.end", [$lernender->lernender_id, $bt->betreuung_id]) }}"
                                  x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true"
                                  data-bestaetigen="{{ __('Betreuung beenden?') }}" data-bestaetigen-knopf="{{ __('Beenden') }}"
                                  data-bestaetigen-text="{{ __('Die Betreuung durch :name endet ab heute.', ['name' => $bt->berufsbildner?->benutzer?->vorname.' '.$bt->berufsbildner?->benutzer?->nachname]) }}">
                                @csrf
                                <button type="submit" :disabled="loading" class="np-knopf np-knopf-gefahr np-knopf-klein">{{ __('Beenden') }}</button>
                            </form>
                        @endcan
                    @endif
                </div>
            @empty
                <p class="px-5 py-4 text-sm text-muted">{{ __('Keine Betreuung.') }}</p>
            @endforelse
        </div>
        @can('betreuungVerwalten', $lernender)
            <form method="POST" action="{{ route("{$bereich}.learners.supervision.store", $lernender->lernender_id) }}"
                  class="mt-auto grid grid-cols-[minmax(0,1fr)_10rem_auto] items-end gap-3 border-t border-border bg-bg/40 px-5 py-4"
                  x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true"
                  @if($hatOffeneBetreuung) data-bestaetigen="{{ __('Betreuung zuweisen?') }}" data-bestaetigen-text="{{ __('Die bisherige Betreuung endet am Vortag.') }}" data-bestaetigen-knopf="{{ __('Zuweisen') }}" data-bestaetigen-art="normal" @endif>
                @csrf
                <div>
                    <label for="berufsbildner_id" class="{{ $label }}">{{ __('Berufsbildner') }} {!! $pflicht !!}</label>
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
                    <label for="gueltig_von" class="{{ $label }}">{{ __('Ab') }} {!! $pflicht !!}</label>
                    <input id="gueltig_von" type="date" name="gueltig_von" required value="{{ old('gueltig_von', now()->toDateString()) }}" class="{{ $feld }}">
                    @error('gueltig_von')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                </div>
                <button type="submit" :disabled="loading" class="np-knopf np-knopf-primaer">{{ __('Zuweisen') }}</button>
            </form>
        @endcan
        </div>
    </x-karte>

    {{-- Tracks --}}
    <x-karte :titel="__('Schul-Tracks')" :polster="false" class="col-span-6">
        <div class="flex h-full flex-col">
        <div class="mt-2 divide-y divide-border border-t border-border">
            @forelse($lernender->tracks as $t)
                <div class="flex min-h-14 items-center gap-3 px-5 py-2.5">
                    <span @class(['np-marke shrink-0', 'bg-accent/10 text-accent-text' => ! $t->end_datum, 'text-muted' => $t->end_datum])>{{ $t->track_typ }}</span>
                    <div class="min-w-0 flex-1 text-sm text-text">
                        {{ __('ab :datum', ['datum' => $t->start_datum->format('d.m.Y')]) }}
                        @if($t->startSemester)<span class="text-muted">({{ $semesterName($t->startSemester->semester_id) }})</span>@endif
                        @if($t->end_datum)
                            <span class="text-muted">– {{ $t->end_datum->format('d.m.Y') }}{{ $t->endSemester ? ' ('.$semesterName($t->endSemester->semester_id).')' : '' }}</span>
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
                                        <option value="{{ $s->semester_id }}">{{ $semesterName($s->semester_id) }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" :disabled="loading"
                                        class="np-knopf np-knopf-gefahr np-knopf-klein">{{ __('Beenden') }}</button>
                            </form>
                        @endcan
                    @endif
                </div>
            @empty
                <p class="px-5 py-4 text-sm text-muted">{{ __('Kein Track.') }}</p>
            @endforelse
        </div>
        @can('verwalten', $lernender)
            <form method="POST" action="{{ route("{$bereich}.learners.tracks.store", $lernender->lernender_id) }}"
                  class="mt-auto grid grid-cols-[6rem_10rem_minmax(0,1fr)_auto] items-end gap-3 border-t border-border bg-bg/40 px-5 py-4"
                  x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                @csrf
                <div>
                    <label for="track_typ" class="{{ $label }}">{{ __('Track') }} {!! $pflicht !!}</label>
                    <select id="track_typ" name="track_typ" required class="{{ $feld }}">
                        <option value="BMS">BMS</option>
                        <option value="ABU">ABU</option>
                    </select>
                    @error('track_typ')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="start_datum" class="{{ $label }}">{{ __('Start') }} {!! $pflicht !!}</label>
                    <input id="start_datum" type="date" name="start_datum" required value="{{ old('start_datum', now()->toDateString()) }}" class="{{ $feld }}">
                    @error('start_datum')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="start_semester_id" class="{{ $label }}">{{ __('Semester') }} {!! $pflicht !!}</label>
                    <select id="start_semester_id" name="start_semester_id" required class="{{ $feld }}">
                        @foreach($semesterListe as $s)
                            <option value="{{ $s->semester_id }}">{{ $semesterName($s->semester_id) }}</option>
                        @endforeach
                    </select>
                    @error('start_semester_id')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                </div>
                <button type="submit" :disabled="loading" class="np-knopf np-knopf-primaer">{{ __('Track starten') }}</button>
            </form>
        @endcan
        </div>
    </x-karte>
</div>
