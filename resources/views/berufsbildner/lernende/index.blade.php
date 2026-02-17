<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-900 dark:text-gray-100">
                Lernende (meine Betreuung)
            </h2>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">

            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-4 text-gray-900 dark:text-gray-100">
                <div class="text-sm text-gray-700 dark:text-gray-200">
                    Wähle einen Lernenden aus, um dessen Noten zu öffnen.
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm text-gray-900 dark:text-gray-100">
                        <thead class="bg-gray-50 dark:bg-gray-900/60 text-gray-700 dark:text-gray-200">
                            <tr>
                                <th class="text-left p-3">Name</th>
                                <th class="text-left p-3">E-Mail</th>
                                <th class="text-right p-3">Aktion</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($lernende as $l)
                                <tr class="bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700/40">
                                    <td class="p-3 whitespace-nowrap">
                                        <span class="font-medium">{{ $l->nachname }}</span> {{ $l->vorname }}
                                    </td>
                                    <td class="p-3 whitespace-nowrap">
                                        {{ $l->email }}
                                    </td>
                                    <td class="p-3 text-right whitespace-nowrap">
                                        <a href="{{ route('berufsbildner.lernende.noten.index', ['lernender_id' => $l->lernender_id]) }}"
                                           class="inline-flex items-center px-3 py-1.5 rounded-md bg-blue-600 text-white hover:bg-blue-700">
                                            Noten öffnen
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="p-4 text-gray-700 dark:text-gray-200" colspan="3">
                                        Keine Lernenden gefunden (noch keine Betreuung erfasst oder nicht aktiv).
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
