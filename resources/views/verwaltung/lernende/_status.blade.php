{{-- Status-Badges einer Lernenden-Zeile, gleich in Karte und Tabelle. Erwartet $l, $z, $tagSeit, $grenze, $bereich. --}}
@if(! $l->benutzer->aktiv)
    <span class="inline-flex whitespace-nowrap px-1.5 py-0.5 rounded-full text-xs bg-surface-2 text-muted border border-border">{{ __('Inaktiv') }}</span>
@endif
@if($z->bms)
    <span class="inline-flex whitespace-nowrap px-1.5 py-0.5 rounded-full text-xs bg-accent/10 text-accent-text">BMS</span>
@endif
@if($bereich === 'admin' && ! $z->betreuer && $l->benutzer->aktiv)
    <span class="inline-flex whitespace-nowrap px-1.5 py-0.5 rounded-full text-xs bg-note-knapp/14 text-note-knapp">{{ __('Ohne BB') }}</span>
@endif
@if($tagSeit === null || $tagSeit > 30)
    <span class="inline-flex whitespace-nowrap px-1.5 py-0.5 rounded-full text-xs bg-note-knapp/14 text-note-knapp">
        {{ $tagSeit === null ? __('Keine Noten') : __(':tage kein Eintrag', ['tage' => $tagSeit.'d']) }}
    </span>
@endif
@if($z->avg !== null && $z->avg < $grenze)
    <span class="inline-flex whitespace-nowrap px-1.5 py-0.5 rounded-full text-xs bg-note-ungenuegend/10 text-note-ungenuegend">{{ __('Ø unter :grenze', ['grenze' => \App\Support\NotenSkala::format($grenze)]) }}</span>
@endif
@if($z->ungelesen > 0)
    <span class="inline-flex whitespace-nowrap px-1.5 py-0.5 rounded-full text-xs font-semibold bg-accent text-accent-contrast">{{ __(':anzahl neu', ['anzahl' => $z->ungelesen]) }}</span>
@endif
