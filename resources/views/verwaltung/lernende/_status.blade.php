{{-- Status-Etiketten einer Lernenden-Zeile. Erwartet $l, $z, $tagSeit, $grenze, $frist, $bereich.
     Farbe nur mit Bedeutung: Warnungen gelb, Notenschnitt rot, ungelesene Noten wie in Mail nur mit Akzentpunkt.
     «Keine Noten» ist zu Beginn einer Lehre der Normalfall und darum grau; gelb wird erst die verstrichene Frist.
     Vorne die Ampel wie in den Übersichten (Gründe im Tooltip). Inaktive Konten tragen nur «Inaktiv», abgeschlossene
     Lehren nur die Ampel «Abgeschlossen»: Warnungen dazu lösen nichts mehr aus. --}}
@php
    $aktiv = (bool) $l->benutzer->aktiv;
    $laufend = $aktiv && $z->stand->status !== \App\Services\Auswertung\Lernstand::ABGESCHLOSSEN;
@endphp
@if($aktiv)
    <x-status :status="$z->stand->status" :title="implode(' · ', $z->stand->gruende) ?: null" class="mr-1" />
@endif
@if($aktiv && $z->ungelesen > 0)
    <span class="inline-flex items-center gap-1.5 whitespace-nowrap text-xs font-medium text-text"><span class="size-2 shrink-0 rounded-full bg-accent" aria-hidden="true"></span>{{ __(':anzahl neu', ['anzahl' => $z->ungelesen]) }}</span>
@endif
@if($laufend && $z->avg !== null && $z->avg < $grenze)
    <span class="np-marke bg-note-ungenuegend/14 text-note-ungenuegend">{{ __('Ø unter :grenze', ['grenze' => \App\Support\NotenSkala::format($grenze)]) }}</span>
@endif
@if($laufend && $tagSeit === null)
    <span class="np-marke bg-fill text-muted">{{ __('Keine Noten') }}</span>
@elseif($laufend && $tagSeit > $frist)
    <span class="np-marke bg-note-knapp/14 text-note-knapp">{{ __('Seit :tage Tagen keine Note', ['tage' => $tagSeit]) }}</span>
@endif
@if($laufend && $bereich === 'admin' && ! $z->betreuer)
    <span class="np-marke bg-note-knapp/14 text-note-knapp">{{ __('Ohne BB') }}</span>
@endif
@if($z->bms)
    <span class="np-marke text-muted">BMS</span>
@endif
@unless($aktiv)
    <span class="np-marke text-muted">{{ __('Inaktiv') }}</span>
@endunless
