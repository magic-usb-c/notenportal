<x-app-layout>
    <x-slot name="title">{{ __('Note erfassen') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :zurueck="route($bereich.'.learners.grades.index', $lernender->lernender_id)" :titel="__('Note erfassen')" :untertitel="$lernender->benutzer->vorname.' '.$lernender->benutzer->nachname" schmal>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-8">
            @if(empty($bezugOptionen))
                <x-leer class="np-karte np-spalte" symbol="clipboard-document-check" :titel="__('Keine Fächer oder Module')">
                    <a href="{{ route($bereich.'.learners.show', [$lernender->lernender_id, 'tab' => 'profil']) }}" class="np-knopf np-knopf-sekundaer">{{ __('Profil & Betreuung') }}</a>
                </x-leer>
            @else
                <div class="np-karte np-spalte p-6">
                    @include('noten._formular', [
                        'zurueck' => route("{$bereich}.learners.grades.index", $lernender->lernender_id),
                        'vorschauUrl' => route("{$bereich}.learners.calculator.calculate", $lernender->lernender_id),
                        'action' => route("{$bereich}.learners.grades.store", $lernender->lernender_id),
                    ])
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
