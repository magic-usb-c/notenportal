@php
    $heute = \Carbon\CarbonImmutable::today();
    $tage = (int) $heute->diffInDays($e['datum'], false);
    $wann = match (true) {
        $tage === 0 => __('heute'),
        $tage === 1 => __('morgen'),
        $tage === -1 => __('gestern'),
        $tage > 1 => __('in :anzahl Tagen', ['anzahl' => $tage]),
        default => __('vor :anzahl Tagen', ['anzahl' => abs($tage)]),
    };
    $p = $e['pruefung'];
@endphp
{{-- Eine Zeile der Agenda: Datum wie das Kalendersymbol (Monat über Tag), Titel mit Kennpunkt, Aktionen rechts. --}}
<div class="flex items-center gap-4 px-4 py-2.5">
    <div class="w-10 shrink-0 text-center leading-none">
        <div class="text-2xs font-medium text-muted">{{ \App\Support\Format::datum($e['datum'], 'M') }}</div>
        <div @class(['mt-0.5 text-lg font-semibold tabular-nums', 'text-accent-text' => $tage === 0, 'text-text' => $tage !== 0])>{{ $e['datum']->format('j') }}</div>
    </div>
    <div class="min-w-0 flex-1">
        <div class="flex min-w-0 items-center gap-2">
            @include('lernender.agenda._punkt', ['art' => $e['art']])
            <span class="truncate text-sm font-medium text-text">{{ $e['titel'] }}</span>
            @if($p && $p->quelle === \App\Models\Pruefung::ICAL && filled($p->lokal_gesperrt))
                <span class="np-marke shrink-0 text-muted" title="{{ __('Lokal angepasst') }}">{{ __('lokal angepasst') }}</span>
            @endif
        </div>
        <div class="truncate pl-4 text-xs text-muted">
            {{ \App\Support\Format::datum($e['datum'], 'D') }}@if($e['zeit']), {{ __(':zeit Uhr', ['zeit' => $e['zeit']]) }}@endif
            · <span @class(['font-medium', 'text-note-knapp' => $faellig, 'text-accent-text' => ! $faellig && $tage >= 0 && $tage <= 7])>{{ $wann }}</span>
            · {{ $e['nebentext'] }}
        </div>
    </div>
    <div class="flex shrink-0 items-center gap-1">
        @if($e['art'] === 'pruefung')
            @if($p->note)
                <a href="{{ route('learner.grades.index') }}" class="mr-1 inline-flex" title="{{ __('Noten') }}">
                    <x-note :wert="$p->note->note_wert" :stufe="$p->note->note_stufe" variante="badge" />
                </a>
            @else
                <a href="{{ route('learner.grades.create', ['pruefung' => $p->pruefung_id]) }}"
                   class="np-knopf np-knopf-klein mr-1 {{ $faellig ? 'np-knopf-sekundaer' : 'np-knopf-schlicht' }}">{{ __('Note eintragen') }}</a>
            @endif
            <a href="{{ route('learner.exams.index', ['bearbeiten' => $p->pruefung_id]) }}" class="np-knopf np-knopf-symbol"
               aria-label="{{ __('Bearbeiten') }}" title="{{ __('Bearbeiten') }}"><x-symbol name="pencil-square" /></a>
            <form method="POST" action="{{ route('learner.exams.destroy', $p->pruefung_id) }}" data-bestaetigen="{{ __('Prüfung entfernen?') }}" data-bestaetigen-knopf="{{ __('Entfernen') }}"
                  x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                @csrf
                @method('DELETE')
                <button :disabled="loading" class="np-knopf np-knopf-symbol np-knopf-symbol-gefahr" aria-label="{{ __('Entfernen') }}" title="{{ __('Entfernen') }}"><x-symbol name="trash" /></button>
            </form>
        @elseif($e['art'] === 'erkannt')
            <form method="POST" action="{{ route('learner.exams.adopt', $e['event']->id) }}" class="flex items-center gap-2"
                  x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                @csrf
                <label for="bezug-{{ $e['event']->id }}" class="sr-only">{{ __('Fach oder Modul') }}</label>
                <select id="bezug-{{ $e['event']->id }}" name="bezug" required class="np-feld np-feld-klein w-48">
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
