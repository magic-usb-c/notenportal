@php
    [$p, $datum, $zustand] = [$z['pruefung'], $z['datum'], $z['zustand']];
    $titel = trim($p->bezeichnung().($p->titel ? ' – '.$p->titel : ''));
    $bald = $zustand === 'offen' && $datum->lte(today()->addDays(7));
    $details = array_filter([$p->pruefungsart, $p->raum ? __('Raum :raum', ['raum' => $p->raum]) : null]);
@endphp
{{-- Eine Zeile wie in der Agenda der Lernenden (Apple Erinnerungen): Datum wie das Kalendersymbol, Titel, darunter
     Lernende/r, Wochentag, Art, Raum und der Zeitpunkt (fehlende Note kräftiger); rechts Note und Aktionen.
     Die Warnung trägt die Gruppenüberschrift mit ihrem Zähler, nicht jede Zeile. --}}
{{-- Weitere Termine desselben Tages lassen das Datum weg und trennen eingerückt wie die Listenansicht in Apple Kalender --}}
<div @class(['relative grid min-h-15 grid-cols-[2.5rem_minmax(0,1fr)_auto] items-center gap-x-5 px-4 py-2',
             'before:absolute before:right-0 before:top-0 before:border-t before:border-border' => ! $ersteZeile,
             'before:left-0' => ! $ersteZeile && $neuerTag, 'before:left-[4.75rem]' => ! $neuerTag])>
    <div class="text-center leading-none">
        <div @class(['sr-only' => ! $neuerTag])>
            <div class="text-2xs font-medium text-muted">{{ \App\Support\Format::datum($datum, 'M') }}</div>
            <div class="mt-0.5 text-lg font-semibold tabular-nums text-text">{{ $datum->format('j') }}</div>
        </div>
    </div>

    <div class="min-w-0">
        <div class="flex min-w-0 items-center gap-2">
            <span @class(['truncate text-sm font-medium', 'text-muted line-through' => $zustand === 'abgesagt', 'text-text' => $zustand !== 'abgesagt'])>{{ $titel }}</span>
            @if($p->istAbgabe())
                <span class="np-marke shrink-0 bg-fill text-muted">{{ __('Abgabetermin') }}</span>
            @endif
            @if($zustand === 'abgesagt')
                <span class="np-marke shrink-0 bg-surface-2 text-muted">{{ __('Abgesagt') }}</span>
            @endif
        </div>
        <div class="truncate text-xs text-muted">
            @unless($einLernender)
                <a href="{{ route($bereich.'.learners.show', $p->lernender_id) }}" class="text-text hover:text-accent-text">{{ $p->lernender->benutzer->vorname }} {{ $p->lernender->benutzer->nachname }}</a> ·
            @endunless
            {{ \App\Support\Format::datum($datum, 'l') }}@if($p->uhrzeit), {{ __(':zeit Uhr', ['zeit' => substr((string) $p->uhrzeit, 0, 5)]) }}@endif
            @foreach($details as $d) · {{ $d }}@endforeach
            · <span @class(['font-medium' => $zustand === 'fehlt', 'text-text' => $zustand === 'fehlt' || $bald])>{{ \App\Support\Format::wann($datum) }}</span>
        </div>
    </div>

    <div class="flex items-center justify-end gap-1">
        @if($zustand === 'benotet')
            <a href="{{ route($bereich.'.learners.grades.index', [$p->lernender_id, '_open' => $p->note->note_id]) }}" class="mr-1 inline-flex" title="{{ __('Note ansehen') }}">
                <x-note :wert="$p->note->note_wert" :stufe="$p->note->note_stufe" variante="badge" />
            </a>
        @endif
        @if($abgabeMoeglich && $p->istAbgabe())
            <a href="{{ route($bereich.'.exams.index', array_merge($filter, ['bearbeiten' => $p->pruefung_id])) }}" class="np-knopf np-knopf-symbol"
               aria-label="{{ __(':titel bearbeiten', ['titel' => $titel]) }}" title="{{ __('Bearbeiten') }}"><x-symbol name="pencil-square" /></a>
            <form method="POST" action="{{ route($bereich.'.exams.destroy', $p->pruefung_id) }}"
                  data-bestaetigen="{{ __('Abgabetermin löschen?') }}" data-bestaetigen-knopf="{{ __('Löschen') }}" x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                @csrf @method('DELETE')
                <input type="hidden" name="lernender_id" value="{{ $p->lernender_id }}">
                <button :disabled="loading" class="np-knopf np-knopf-symbol np-knopf-symbol-gefahr"
                        aria-label="{{ __(':titel löschen', ['titel' => $titel]) }}" title="{{ __('Löschen') }}"><x-symbol name="trash" /></button>
            </form>
        @endif
    </div>
</div>
