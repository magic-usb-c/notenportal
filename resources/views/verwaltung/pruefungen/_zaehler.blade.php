{{-- Zähler einer Lernenden-Zeile wie die Ungelesen-Zahl in Apple Mail: fehlende Noten als Marke (Bernstein = überfällig ohne Note, so wie die Gruppenüberschrift), daneben die Anzahl Termine --}}
@if($fehlt > 0)
    <span class="np-marke shrink-0 bg-note-knapp/14 tabular-nums text-note-knapp">{{ __(':anzahl ohne Note', ['anzahl' => $fehlt]) }}</span>
@endif
@if($anzahl > 0)
    <span class="shrink-0 text-xs tabular-nums text-muted">{{ $anzahl }}<span class="sr-only"> {{ __('Prüfungstermine') }}</span></span>
@endif
