<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-900 dark:text-gray-100">Meine Noten</h2>

            <a href="{{ route('noten.create') }}"
               class="inline-flex items-center px-4 py-2 rounded-md bg-blue-600 text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                Neue Note
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

            {{-- Summary --}}
            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-4 text-gray-900 dark:text-gray-100">
                <div class="text-sm space-y-1">
                    <div>Noten total (Filter berücksichtigt): <span class="font-semibold">{{ $count }}</span></div>
                    <div>Durchschnitt (ungewichtet): <span class="font-semibold">{{ $avgUnweighted ?? '-' }}</span></div>
                    <div>Durchschnitt (gewichtet, nur Noten mit Gewichtung): <span class="font-semibold">{{ $avgWeighted ?? '-' }}</span></div>

                    @if(($missingWeights ?? 0) > 0)
                        <div class="mt-2 rounded-md bg-yellow-50 dark:bg-yellow-900/30 text-yellow-800 dark:text-yellow-200 px-3 py-2">
                            Hinweis: {{ $missingWeights }} Note(n) ohne Gewichtung sind nicht im gewichteten Schnitt.
                        </div>
                    @endif
                </div>
            </div>

            {{-- Filter --}}
            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-4 text-gray-900 dark:text-gray-100">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
                    <div>
                        <label class="text-sm font-medium">Kategorie</label>
                        <select name="kategorie_id"
                                class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100">
                            <option value="">Alle</option>
                            @foreach($kategorien as $k)
                                <option value="{{ $k->kategorie_id }}" @selected(request('kategorie_id') == $k->kategorie_id)>
                                    {{ $k->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="text-sm font-medium">Semester</label>
                        <select name="semester_id"
                                class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100">
                            <option value="">Alle</option>
                            @foreach($semester as $s)
                                <option value="{{ $s->semester_id }}" @selected(request('semester_id') == $s->semester_id)>
                                    {{ $s->bezeichnung }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex gap-2">
                        <button class="px-4 py-2 rounded-md bg-gray-800 text-white hover:bg-gray-900 dark:bg-gray-700 dark:hover:bg-gray-600">
                            Filtern
                        </button>

                        <a href="{{ route('noten.index') }}"
                           class="px-4 py-2 rounded-md bg-gray-200 text-gray-900 hover:bg-gray-300 dark:bg-gray-900 dark:text-gray-100 dark:hover:bg-gray-950">
                            Reset
                        </a>
                    </div>
                </form>
            </div>

            {{-- Table --}}
            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm text-gray-900 dark:text-gray-100">
                        <thead class="bg-gray-50 dark:bg-gray-900/60 text-gray-700 dark:text-gray-200">
                            <tr>
                                <th class="text-left p-3 whitespace-nowrap">Datum</th>
                                <th class="text-left p-3 whitespace-nowrap">Kategorie</th>
                                <th class="text-left p-3">Fach / Modul</th>
                                <th class="text-left p-3">Titel</th>
                                <th class="text-right p-3 whitespace-nowrap">Note</th>
                                <th class="text-right p-3 whitespace-nowrap">Gew. %</th>
                                <th class="text-right p-3 whitespace-nowrap">Aktionen</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($notes as $n)
                                <tr class="bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700/40">
                                    <td class="p-3 whitespace-nowrap">
                                        {{ optional($n->pruefungsdatum)->format('d.m.Y') }}
                                    </td>

                                    <td class="p-3 whitespace-nowrap">
                                        {{ $n->kategorie?->name ?? '-' }}
                                    </td>

                                    <td class="p-3">
                                        @if($n->fach)
                                            <div class="font-medium">{{ $n->fach->name }}</div>
                                        @elseif($n->modulBelegung && $n->modulBelegung->modul)
                                            <div class="font-medium">
                                                {{ $n->modulBelegung->modul->modul_nummer }} – {{ $n->modulBelegung->modul->titel }}
                                            </div>
                                            @if($n->gruppe)
                                                <div class="text-xs text-gray-600 dark:text-gray-300">
                                                    Gruppe: {{ $n->gruppe->bezeichnung }}
                                                </div>
                                            @endif
                                        @else
                                            -
                                        @endif
                                    </td>

                                    <td class="p-3">
                                        <span class="text-gray-900 dark:text-gray-100">
                                            {{ $n->titel ?? '-' }}
                                        </span>
                                    </td>

                                    <td class="p-3 text-right font-semibold whitespace-nowrap">
                                        {{ $n->note_wert }}
                                    </td>

                                    <td class="p-3 text-right whitespace-nowrap">
                                        {{ $n->gewichtung_prozent ?? '-' }}
                                    </td>

                                    <td class="p-3 text-right whitespace-nowrap">
                                        <a class="text-blue-600 hover:underline dark:text-blue-400"
                                           href="{{ route('noten.edit', $n->note_id) }}">
                                            Bearbeiten
                                        </a>

                                        <form class="inline"
                                              method="POST"
                                              action="{{ route('noten.destroy', $n->note_id) }}"
                                              onsubmit="return confirm('Note wirklich löschen?');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="ml-3 text-red-600 hover:underline dark:text-red-400">
                                                Löschen
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="p-4 text-gray-700 dark:text-gray-200" colspan="7">
                                        Keine Noten gefunden.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="p-3 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100">
                    {{ $notes->links() }}
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
