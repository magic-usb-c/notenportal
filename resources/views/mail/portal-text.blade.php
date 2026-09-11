{{ $c->title ?? $c->subject }}

@if($vorname)
Hallo {{ $vorname }}

@endif
@foreach($c->lines as $zeile)
{{ $zeile }}

@endforeach
@foreach($c->facts as $label => $wert)
{{ $label }}: {{ $wert }}
@endforeach
@foreach($c->sections as $abschnitt)

{{ mb_strtoupper($abschnitt['title']) }}
{{ trim($abschnitt['text']) }}
@endforeach
@if($c->table)

{{ implode(' | ', $c->table['head']) }}
@foreach($c->table['rows'] as $zeile)
{{ implode(' | ', $zeile) }}
@endforeach
@endif
@foreach(collect($c->items)->groupBy(fn ($i) => $i['group'] ?? '') as $gruppe => $eintraege)

@if($gruppe !== ''){{ mb_strtoupper($gruppe) }}
@endif
@foreach($eintraege as $e)
- {{ $e['title'] }}@if(! empty($e['text'])) – {{ $e['text'] }}@endif @if(! empty($e['url']))
  {{ $e['url'] }}
@endif
@endforeach
@endforeach
@if($c->actionUrl)

{{ $c->actionLabel ?? 'Im Notenportal öffnen' }}: {{ $c->actionUrl }}
@endif
@foreach($c->outro as $zeile)

{{ $zeile }}
@endforeach

--
@if($anlass)Du erhältst diese Mail wegen «{{ $anlass }}».
@endif
@if($einstellungenUrl)Benachrichtigungen einstellen: {{ $einstellungenUrl }}
@endif
@if($abmeldenUrl)Abbestellen: {{ $abmeldenUrl }}
@endif
Notenportal{{ $betrieb !== '' ? ' '.$betrieb : '' }} – {{ $portalUrl }}
