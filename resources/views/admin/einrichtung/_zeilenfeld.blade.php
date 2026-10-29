{{-- Feld einer Zeile in «Personen» (steht in einer Alpine-Schleife über `zeilen`, Index `i`): Label und Feld hängen je
     Zeile über for/id zusammen, ein Fehler setzt aria-invalid und verweist auf die gesammelte Meldungsliste «{gruppe}-fehler».
     gruppe: personen | lernende · schluessel: Feldname · art: text | email | date | select · optionen: nur bei select. --}}
@php
    $idJs = '`'.$gruppe.'-${i}-'.$schluessel.'`';
    $art = $art ?? 'text';
@endphp
<div class="flex min-w-0 flex-col gap-1 text-sm font-medium text-text {{ $spanne ?? '' }}">
    <label :for="{{ $idJs }}">{{ $text }}</label>
    @if($art === 'select')
        <select :id="{{ $idJs }}" :name="`{{ $gruppe }}[${i}][{{ $schluessel }}]`" x-model="z.{{ $schluessel }}" class="{{ $klasse }}"
                :aria-invalid="f(i, '{{ $schluessel }}') ? 'true' : null" :aria-describedby="f(i, '{{ $schluessel }}') ? '{{ $gruppe }}-fehler' : null">
            @foreach($optionen as $wert => $beschriftung)<option value="{{ $wert }}">{{ $beschriftung }}</option>@endforeach
        </select>
    @else
        <input type="{{ $art }}" :id="{{ $idJs }}" :name="`{{ $gruppe }}[${i}][{{ $schluessel }}]`" x-model="z.{{ $schluessel }}" class="{{ $klasse }}"
               :aria-invalid="f(i, '{{ $schluessel }}') ? 'true' : null" :aria-describedby="f(i, '{{ $schluessel }}') ? '{{ $gruppe }}-fehler' : null"
               {!! $attr ?? '' !!}>
    @endif
</div>
