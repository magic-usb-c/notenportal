{{-- Status-Etiketten einer Lernenden-Zeile. Erwartet $l, $z, $tagSeit, $grenze, $frist, $bereich.
     Farbe nur mit Bedeutung: Hinweise gelb, Notenschnitt rot, ungelesene Noten wie in Mail mit blauem Punkt. --}}
@if($z->ungelesen > 0)
    <span class="inline-flex items-center gap-1.5 whitespace-nowrap text-xs font-medium text-accent-text"><span class="size-2 shrink-0 rounded-full bg-accent" aria-hidden="true"></span>{{ __(':anzahl neu', ['anzahl' => $z->ungelesen]) }}</span>
@endif
@if($z->avg !== null && $z->avg < $grenze)
    <span class="np-marke bg-note-ungenuegend/10 text-note-ungenuegend">{{ __('Ø unter :grenze', ['grenze' => \App\Support\NotenSkala::format($grenze)]) }}</span>
@endif
@if($tagSeit === null || $tagSeit > $frist)
    <span class="np-marke bg-note-knapp/14 text-note-knapp">{{ $tagSeit === null ? __('Keine Noten') : __('Seit :tage Tagen keine Note', ['tage' => $tagSeit]) }}</span>
@endif
@if($bereich === 'admin' && ! $z->betreuer && $l->benutzer->aktiv)
    <span class="np-marke bg-note-knapp/14 text-note-knapp">{{ __('Ohne BB') }}</span>
@endif
@if($z->bms)
    <span class="np-marke text-muted">BMS</span>
@endif
@if(! $l->benutzer->aktiv)
    <span class="np-marke text-muted">{{ __('Inaktiv') }}</span>
@endif
