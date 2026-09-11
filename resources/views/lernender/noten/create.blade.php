<x-app-layout>
    <x-slot name="title">Neue Note</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-text">Neue Note</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="glass rounded-3xl p-6 sm:p-8">
                @if(empty($bezugOptionen))
                    <p class="mb-5 text-sm text-yellow-700 dark:text-yellow-400">Für dich sind noch keine Fächer oder Module freigegeben.</p>
                @endif

                @include('noten._formular', [
                    'action' => route('learner.grades.store'),
                    'zurueck' => $pruefung ? route('learner.exams.index') : route('learner.grades.index'),
                    'vorschauUrl' => route('learner.grades.calculator.calculate'),
                ])
            </div>
        </div>
    </div>
</x-app-layout>
