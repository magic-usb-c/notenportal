@php
    // Freitext: Zeilenende ohne Leerzeichen, höchstens eine Leerzeile hintereinander. Sonderzeichen bleiben unverändert.
    $frei = fn ($t) => preg_replace(['/[ \t]+(?=\r?\n|$)/u', '/(\r?\n){3,}/u'], ['', "\n\n"], trim((string) $t));
@endphp
{!! $frei($c->title ?? $c->subject) !!}
@if($vorname)

{!! __('Hallo :vorname', ['vorname' => $vorname]) !!}
@endif
@foreach($c->lines as $zeile)

{!! $frei($zeile) !!}
@endforeach
@if($c->facts)

@foreach($c->facts as $label => $wert)
{!! $label !!}: {!! $frei($wert) !!}
@endforeach
@endif
@foreach($c->sections as $abschnitt)

{!! mb_strtoupper($abschnitt['title']) !!}
{!! $frei($abschnitt['text']) !!}
@endforeach
@if($c->table)

{!! implode(' | ', $c->table['head']) !!}
@foreach($c->table['rows'] as $zeile)
{!! implode(' | ', $zeile) !!}
@endforeach
@endif
@foreach(collect($c->items)->groupBy(fn ($i) => $i['group'] ?? '') as $gruppe => $eintraege)

@if($gruppe !== ''){!! mb_strtoupper($gruppe) !!}
@endif
@foreach($eintraege as $e)
- {!! $frei($e['title']) !!}{!! ! empty($e['text']) ? ' – '.$frei($e['text']) : '' !!}
@if(! empty($e['url']))
  {!! $e['url'] !!}
@endif
@endforeach
@endforeach
@if($c->actionUrl)

{!! $c->actionLabel ?? __('Im Notenportal öffnen') !!}: {!! $c->actionUrl !!}
@endif
@foreach($c->outro as $zeile)

{!! $frei($zeile) !!}
@endforeach

--
@if($anlass){!! __('Du erhältst diese Mail wegen «:anlass».', ['anlass' => $anlass]) !!}
@endif
@if($einstellungenUrl){!! __('Benachrichtigungen einstellen') !!}: {!! $einstellungenUrl !!}
@endif
@if($abmeldenUrl){!! __('Abbestellen') !!}: {!! $abmeldenUrl !!}
@endif
Notenportal{!! $betrieb !== '' ? ' '.$betrieb : '' !!} – {!! $portalUrl !!}
