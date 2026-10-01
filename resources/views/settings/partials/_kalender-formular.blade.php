{{-- Formular für einen Kalender (neu oder bearbeiten) als Zeilen der gruppierten Liste. $feed ist null bei einem neuen
     Kalender; $abbrechbar zeigt «Abbrechen» (schliesst das umgebende «bearbeiten»-Panel).
     feed_id wird IMMER mitgeschickt – sonst legt feedStore() bei jedem Speichern einen neuen Kalender an.
     Fehler zeigt nur das Formular, das abgeschickt wurde (sonst stünden sie in jedem Kalender). --}}
@php
    $idSuffix = $feed?->id ?? 'neu';
    // Leeres feed_id kommt als null zurück (ConvertEmptyStringsToNull) – darum auf den Schlüssel prüfen.
    $alt = session()->getOldInput();
    $abgeschickt = array_key_exists('feed_id', $alt) && (string) $alt['feed_id'] === (string) ($feed?->id ?? '');
    $fehlerFuer = fn (string $feld) => $abgeschickt && $errors->has($feld);
@endphp
<form method="POST" action="{{ route('learner.calendar.feed.store') }}" class="divide-y divide-border" x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
    @csrf
    <input type="hidden" name="feed_id" value="{{ $feed?->id }}">
    <x-einstellung :label="__('Bezeichnung')" :fuer="'feed_label_'.$idSuffix" :fehler="$abgeschickt ? 'label' : []">
        <input id="feed_label_{{ $idSuffix }}" name="label" maxlength="80" value="{{ $abgeschickt ? old('label') : $feed?->label }}" placeholder="{{ __('z. B. Schulnetz') }}" class="np-feld w-96"
               @if($fehlerFuer('label')) aria-invalid="true" aria-describedby="label-fehler" @endif>
    </x-einstellung>
    <x-einstellung :label="__('iCal-Adresse')" :fuer="'feed_url_'.$idSuffix" :fehler="$abgeschickt ? 'url' : []">
        {{-- Die bestehende Adresse ist ein Geheimnis und wird nie in dieses Feld eingesetzt. --}}
        <input id="feed_url_{{ $idSuffix }}" name="url" type="text" inputmode="url" @unless($feed) required @endunless maxlength="2000" spellcheck="false"
               value="{{ $abgeschickt ? old('url') : '' }}"
               placeholder="{{ $feed ? __('unverändert lassen oder neue Adresse eintragen') : 'https://…/kalender.ics' }}" class="np-feld w-96"
               @if($fehlerFuer('url')) aria-invalid="true" aria-describedby="url-fehler" @endif>
    </x-einstellung>
    @foreach(['import_exams' => __('Prüfungen übernehmen'), 'import_appointments' => __('Termine übernehmen'), 'import_lessons' => __('Lektionen (Stundenplan) übernehmen')] as $feld => $bezeichnung)
        <x-einstellung :label="$bezeichnung" :fuer="$feld.'_'.$idSuffix">
            <input type="checkbox" role="switch" id="{{ $feld }}_{{ $idSuffix }}" name="{{ $feld }}" value="1" class="np-schalter"
                   @checked($abgeschickt ? old($feld) : ($feed?->$feld ?? true))>
        </x-einstellung>
    @endforeach
    <div class="flex justify-end gap-2 px-4 py-3">
        @if($abbrechbar ?? false)
            <button type="button" class="np-knopf np-knopf-sekundaer" @click="bearbeiten = false">{{ __('Abbrechen') }}</button>
        @endif
        <button :disabled="loading" class="np-knopf np-knopf-primaer min-w-24">{{ $feed ? __('Speichern') : __('Hinzufügen') }}</button>
    </div>
</form>
