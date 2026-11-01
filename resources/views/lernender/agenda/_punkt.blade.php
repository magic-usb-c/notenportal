{{-- Kennpunkt der Eintragsart wie die Kalenderfarbe in Apple Kalender: gefüllt = eigener Eintrag, Ring = nur eingelesen; die Prüfung ist ein Quadrat mit Radius, alles andere ein Kreis – die Art steht nie nur in der Farbe. --}}
<span class="size-2 shrink-0 {{ $art === 'pruefung' ? 'rounded-xs' : 'rounded-full' }} {{ \App\Support\AgendaArt::punkt($art) }}" aria-hidden="true"></span>
