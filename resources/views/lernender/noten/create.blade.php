<x-app-layout>
    <x-slot name="title">{{ __('Neue Note') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf titel="{{ __('Neue Note') }}" schmal />
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-8">
            @if(empty($bezugOptionen))
                <x-leer class="np-karte np-spalte" symbol="clipboard-document-check" :titel="__('Keine Fächer oder Module')">
                    <a href="{{ route('modules.index') }}" class="np-knopf np-knopf-sekundaer">{{ __('Module ansehen') }}</a>
                </x-leer>
            @else
                <div class="np-karte np-spalte p-6">
                    @include('noten._formular', [
                        'action' => route('learner.grades.store'),
                        'zurueck' => $rueckziel ? url($rueckziel) : ($pruefung ? route('learner.exams.index') : route('learner.grades.index')),
                        'vorschauUrl' => route('learner.grades.calculator.calculate'),
                    ])
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
