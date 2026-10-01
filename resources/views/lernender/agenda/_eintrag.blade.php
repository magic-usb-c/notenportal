@php
    $heute = \Carbon\CarbonImmutable::today();
    $tage = (int) $heute->diffInDays($e['datum'], false);
    $wann = \App\Support\Format::wann($e['datum']);
    $p = $e['pruefung'];
    // Zweite Zeile: Wochentag und Zeit, bei Prüfungen Art und Dauer, sonst Ort bzw. Herkunft
    $details = array_filter($p ? [
        $p->pruefungsart,
        $p->dauer_minuten ? __(':anzahl Minuten', ['anzahl' => $p->dauer_minuten]) : null,
    ] : [$e['nebentext']]);
@endphp
{{-- Eine Zeile der Agenda wie in Apple Erinnerungen: Datum wie das Kalendersymbol (Monat über Tag), Titel mit
     Kennpunkt, darunter Wochentag, Art, Gewicht und der Zeitpunkt in seiner Farbe; rechts nur die Aktionen. --}}
<div class="grid min-h-15 grid-cols-[2.5rem_minmax(0,1fr)_auto] items-center gap-x-5 px-4 py-2">
    <div class="text-center leading-none">
        <div class="text-2xs font-medium text-muted">{{ \App\Support\Format::datum($e['datum'], 'M') }}</div>
        <div @class(['mt-0.5 text-lg font-semibold tabular-nums', 'text-accent-text' => $tage === 0, 'text-text' => $tage !== 0])>{{ $e['datum']->format('j') }}</div>
    </div>

    <div class="min-w-0">
        <div class="flex min-w-0 items-center gap-2">
            @include('lernender.agenda._punkt', ['art' => $e['art']])
            <span class="truncate text-sm font-medium text-text">{{ $e['titel'] }}</span>
            @if($p && $p->quelle === \App\Models\Pruefung::ICAL && filled($p->lokal_gesperrt))
                <span class="np-marke shrink-0 text-muted" title="{{ __('Lokal angepasst') }}">{{ __('lokal angepasst') }}</span>
            @endif
        </div>
        <div class="truncate pl-4 text-xs text-muted">
            {{ \App\Support\Format::datum($e['datum'], 'l') }}@if($e['zeit']), {{ __(':zeit Uhr', ['zeit' => $e['zeit']]) }}@endif
            @foreach($details as $d) · {{ $d }}@endforeach
            @if($p) · <span class="sr-only">{{ __('Gewichtung') }}</span><span class="tabular-nums">{{ $e['nebentext'] }}</span>@endif
            · <span @class(['font-medium text-text' => $faellig, 'text-text' => ! $faellig && $tage >= 0 && $tage <= 7])>{{ $wann }}</span>
        </div>
    </div>

    <div class="flex items-center justify-end gap-1">
        @if($e['art'] === 'pruefung')
            @if($p->note)
                <a href="{{ route('learner.grades.index', ['_open' => $p->note->note_id]) }}" class="mr-1 inline-flex" title="{{ __('Note ansehen') }}">
                    <x-note :wert="$p->note->note_wert" :stufe="$p->note->note_stufe" variante="badge" />
                </a>
            @elseif($tage <= 0)
                <a href="{{ route('learner.grades.create', ['pruefung' => $p->pruefung_id]) }}" class="np-knopf np-knopf-sekundaer np-knopf-klein mr-1">{{ __('Note eintragen') }}</a>
            @elseif($ziel = $rechnerZiel($p))
                <a href="{{ route('learner.grades.calculator', ['ziel' => $ziel]) }}" class="np-knopf np-knopf-schlicht np-knopf-klein mr-1">{{ __('Was brauche ich?') }}</a>
            @endif
            <a href="{{ route('learner.exams.index', ['bearbeiten' => $p->pruefung_id]) }}" class="np-knopf np-knopf-symbol"
               aria-label="{{ __(':titel bearbeiten', ['titel' => $e['titel']]) }}" title="{{ __('Bearbeiten') }}"><x-symbol name="pencil-square" /></a>
            <form method="POST" action="{{ route('learner.exams.destroy', $p->pruefung_id) }}" data-bestaetigen="{{ __('Prüfung entfernen?') }}" data-bestaetigen-knopf="{{ __('Entfernen') }}"
                  x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                @csrf
                @method('DELETE')
                <button :disabled="loading" class="np-knopf np-knopf-symbol np-knopf-symbol-gefahr" aria-label="{{ __(':titel entfernen', ['titel' => $e['titel']]) }}" title="{{ __('Entfernen') }}"><x-symbol name="trash" /></button>
            </form>
        @elseif($e['art'] === 'erkannt')
            <form method="POST" action="{{ route('learner.exams.adopt', $e['event']->id) }}" class="flex items-center gap-2"
                  x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                @csrf
                <label for="bezug-{{ $e['event']->id }}" class="sr-only">{{ __('Fach oder Modul') }}</label>
                <select id="bezug-{{ $e['event']->id }}" name="bezug" required class="np-feld np-feld-klein w-44">
                    <option value="">{{ __('Zuordnen') }}</option>
                    @foreach($bezugOptionen as $gruppe => $optionen)
                        <optgroup label="{{ $gruppe }}">
                            @foreach($optionen as $o)
                                <option value="{{ $o['wert'] }}">{{ $o['label'] }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                <button :disabled="loading" class="np-knopf np-knopf-sekundaer np-knopf-klein">{{ __('Übernehmen') }}</button>
            </form>
        @endif
    </div>
</div>
