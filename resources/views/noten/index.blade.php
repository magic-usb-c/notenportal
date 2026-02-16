<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-900 dark:text-gray-100">
                @if(($mode ?? 'lernender') === 'admin')
                    Alle Noten
                @elseif(($mode ?? 'lernender') === 'berufsbildner')
                    Noten meiner Lernenden
                @else
                    Meine Noten
                @endif
            </h2>

            @if(($mode ?? 'lernender') === 'lernender')
                <a href="{{ route('noten.create') }}"
                   class="inline-flex items-center px-4 py-2 rounded-md bg-blue-600 text-white hover:bg-blue-700">
                    Neue Note
                </a>
            @endif
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if (session('status'))
                <div class="bg-green-50 dark:bg-green-900/30 text-green-800 dark:text-green-200 px-4 py-3 rounded-md">
                    {{ session('status') }}
                </div>
            @endif

            {{-- Summary nur für Lernender --}}
            @if(($mode ?? 'lernender') === 'lernender')
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
            @endif

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

                                @if(($mode ?? 'lernender') !== 'lernender')
                                    <th class="text-left p-3 whitespace-nowrap">Lernender</th>
                                @endif

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

                                    @if(($mode ?? 'lernender') !== 'lernender')
                                        <td class="p-3 whitespace-nowrap">
                                            <div class="font-medium">
                                                {{ $n->lernender_vorname }} {{ $n->lernender_nachname }}
                                            </div>
                                            <div class="text-xs text-gray-600 dark:text-gray-300">
                                                {{ $n->lernender_email }}
                                            </div>
                                        </td>
                                    @endif

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
                                        {{ $n->titel ?? '-' }}
                                    </td>

                                    <td class="p-3 text-right font-semibold whitespace-nowrap">
                                        {{ $n->note_wert }}
                                    </td>

                                    <td class="p-3 text-right whitespace-nowrap">
                                        {{ $n->gewichtung_prozent ?? '-' }}
                                    </td>

                                    <td class="p-3 text-right whitespace-nowrap">
                                        {{-- Lernender: bearbeiten/löschen --}}
                                        @if(($mode ?? 'lernender') === 'lernender')
                                            <a class="text-blue-600 hover:underline" href="{{ route('noten.edit', $n->note_id) }}">Bearbeiten</a>

                                            <form method="POST" action="{{ route('noten.destroy', $n->note_id) }}" class="inline"
                                                  onsubmit="return confirm('Note wirklich löschen?');">
                                                @csrf
                                                @method('DELETE')
                                                <button class="text-red-600 hover:underline ms-3">Löschen</button>
                                            </form>
                                        @endif

                                        {{-- Berufsbildner: gesehen --}}
                                        @if(($mode ?? 'lernender') === 'berufsbildner')
                                            @if(!empty($n->gesehen_am))
                                                <span class="inline-flex items-center px-2 py-1 rounded-md bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-200">
                                                    Gesehen
                                                </span>
                                            @else
                                                <form method="POST" action="{{ route('noten.gesehen', $n->note_id) }}" class="inline">
                                                    @csrf
                                                    <button class="inline-flex items-center px-3 py-1 rounded-md bg-gray-200 text-gray-900 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-100 dark:hover:bg-gray-600">
                                                        Gesehen
                                                    </button>
                                                </form>
                                            @endif
                                        @endif

                                        {{-- Admin: vorerst keine Aktionen --}}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="p-4 text-gray-700 dark:text-gray-200" colspan="8">
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
