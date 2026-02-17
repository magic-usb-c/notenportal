<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <h2 class="font-semibold text-xl text-gray-900 dark:text-gray-100">
                Noten von
                <span class="text-gray-700 dark:text-gray-200">
                    {{ $selectedLernender->nachname ?? '' }} {{ $selectedLernender->vorname ?? '' }}
                </span>
            </h2>

            <div class="flex items-center gap-2">
                <a href="{{ route('berufsbildner.lernende.index') }}"
                   class="px-4 py-2 rounded-md bg-gray-200 text-gray-900 hover:bg-gray-300 dark:bg-gray-900 dark:text-gray-100 dark:hover:bg-gray-950">
                    Lernenden wechseln
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">

            {{-- Lernenden-Switcher direkt auf der Seite (ohne zurück zum Dashboard) --}}
            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-4 text-gray-900 dark:text-gray-100">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 items-end">
                    <div>
                        <label class="text-sm font-medium">Lernender wechseln</label>
                        <select
                            class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100"
                            onchange="if(this.value) window.location.href=this.value"
                        >
                            @foreach($lernende as $l)
                                <option
                                    value="{{ route('berufsbildner.lernende.noten.index', ['lernender_id' => $l->lernender_id]) }}"
                                    @selected((int)$l->lernender_id === (int)$selectedLernenderId)
                                >
                                    {{ $l->nachname }} {{ $l->vorname }} ({{ $l->email }})
                                </option>
                            @endforeach
                        </select>
                        <div class="mt-1 text-xs text-gray-600 dark:text-gray-300">
                            Wechseln passiert sofort beim Auswählen.
                        </div>
                    </div>

                    <div class="flex gap-2 md:justify-end">
                        <a href="{{ route('berufsbildner.lernende.noten.index', ['lernender_id' => $selectedLernenderId]) }}"
                           class="px-4 py-2 rounded-md bg-gray-200 text-gray-900 hover:bg-gray-300 dark:bg-gray-900 dark:text-gray-100 dark:hover:bg-gray-950">
                            Filter reset
                        </a>
                    </div>
                </div>
            </div>

            {{-- Filter (Kategorie/Semester) --}}
            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-4 text-gray-900 dark:text-gray-100">
                <form method="GET" action="{{ route('berufsbildner.lernende.noten.index', ['lernender_id' => $selectedLernenderId]) }}"
                      class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">

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

                        <a href="{{ route('berufsbildner.lernende.noten.index', ['lernender_id' => $selectedLernenderId]) }}"
                           class="px-4 py-2 rounded-md bg-gray-200 text-gray-900 hover:bg-gray-300 dark:bg-gray-900 dark:text-gray-100 dark:hover:bg-gray-950">
                            Reset
                        </a>
                    </div>
                </form>
            </div>

            {{-- Tabelle (MVP: flach; später gruppieren/accordion nach Fach/Modul + Durchschnitt im Header) --}}
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
                        </tr>
                        </thead>

                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($notes as $n)
                            <tr class="bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700/40">
                                <td class="p-3 whitespace-nowrap">{{ optional($n->pruefungsdatum)->format('d.m.Y') }}</td>
                                <td class="p-3 whitespace-nowrap">{{ $n->kategorie?->name ?? '-' }}</td>

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

                                <td class="p-3">{{ $n->titel ?? '-' }}</td>
                                <td class="p-3 text-right font-semibold whitespace-nowrap">{{ $n->note_wert }}</td>
                                <td class="p-3 text-right whitespace-nowrap">{{ $n->gewichtung_prozent ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="p-4 text-gray-700 dark:text-gray-200" colspan="6">
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
