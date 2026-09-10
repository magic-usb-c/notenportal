<x-app-layout>
    <x-slot name="title">Note erfassen</x-slot>
    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-text">
                Note erfassen:
                <span class="text-muted">{{ $lernender->benutzer->nachname }} {{ $lernender->benutzer->vorname }}</span>
            </h2>
            <a href="{{ route("{$bereich}.lernende.noten.index", $lernender->lernender_id) }}"
               class="px-4 py-2 h-10 rounded-xl glass-btn text-text whitespace-nowrap text-sm">Zurück</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="glass rounded-2xl p-6 space-y-5">
                @if($faecher->isEmpty() && $module->isEmpty())
                    <p class="text-sm text-yellow-700 dark:text-yellow-400">Keine Fächer oder Module verfügbar – zuerst einen Track oder Lehrberuf-Module einrichten.</p>
                @endif

                @include('verwaltung.noten._formular', [
                    'action' => route("{$bereich}.lernende.noten.store", $lernender->lernender_id),
                ])
            </div>
        </div>
    </div>
</x-app-layout>
