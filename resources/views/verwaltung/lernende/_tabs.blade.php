{{-- Cockpit-Navigation eines Lernenden (Verwaltung): Übersicht · Noten · Dokumente · Rechner · Profil & Betreuung.
     Parameter: $lernender, $bereich ('admin'|'trainer'), $aktiv ('overview'|'grades'|'documents'|'calculator'|'profil'),
     $alpine (nur im Cockpit selbst: Übersicht und Profil sind dort Alpine-Tabs mit wechsleTab()/tab, der Startwert
     steht schon im HTML; sonst Links auf das Cockpit). --}}
@php
    $alpine ??= false;
    $klasse ??= ''; // z. B. -mb-1 in Containern mit gap-5, damit unter der Leiste überall 16 px bleiben
    $lernenderId = $lernender->lernender_id;
    $eintraege = [
        'overview' => [__('Übersicht'), route("{$bereich}.learners.show", $lernenderId)],
        'grades' => [__('Noten'), route("{$bereich}.learners.grades.index", $lernenderId)],
        'documents' => [__('Dokumente'), route("{$bereich}.learners.documents.index", $lernenderId)],
        'calculator' => [__('Rechner'), route("{$bereich}.learners.calculator", $lernenderId)],
        'profil' => [__('Profil & Betreuung'), route("{$bereich}.learners.show", [$lernenderId, 'tab' => 'profil'])],
    ];
@endphp
<nav aria-label="{{ __('Bereiche') }}" class="np-segment self-start {{ $klasse ?? '' }}">
    @foreach($eintraege as $schluessel => $eintrag)
        @if($alpine && in_array($schluessel, ['overview', 'profil'], true))
            <button type="button" @if($aktiv === $schluessel) aria-current="page" @endif :aria-current="tab === '{{ $schluessel }}' ? 'page' : null" @click="wechsleTab('{{ $schluessel }}')">{{ $eintrag[0] }}</button>
        @else
            <a href="{{ $eintrag[1] }}" @if($aktiv === $schluessel) aria-current="page" @endif>{{ $eintrag[0] }}</a>
        @endif
    @endforeach
</nav>
