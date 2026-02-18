<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-900 dark:text-gray-100">Neue Note</h2>

            <a href="{{ route('lernender.noten.index') }}"
               class="px-4 py-2 rounded-md bg-gray-200 text-gray-900 hover:bg-gray-300 dark:bg-gray-900 dark:text-gray-100 dark:hover:bg-gray-950">
                Zur Übersicht
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow sm:rounded-lg p-6 text-gray-900 dark:text-gray-100">

                @if ($errors->any())
                    <div class="mb-4 rounded-md bg-red-50 dark:bg-red-900/30 text-red-800 dark:text-red-200 px-4 py-3">
                        <div class="font-semibold mb-2">Bitte prüfen:</div>
                        <ul class="list-disc pl-5 space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('lernender.noten.store') }}" class="space-y-5">
                    @csrf

                    <div>
                        <label class="text-sm font-medium">Kategorie</label>
                        <select name="kategorie_id" required
                                class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100">
                            @foreach($kategorien as $k)
                                <option value="{{ $k->kategorie_id }}" @selected(old('kategorie_id') == $k->kategorie_id)>
                                    {{ $k->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="text-sm font-medium">Typ</label>
                        <div class="mt-2 flex gap-6">
                            <label class="flex items-center gap-2">
                                <input type="radio" name="typ" value="fach" @checked(old('typ','fach') === 'fach')>
                                <span>Fach</span>
                            </label>
                            <label class="flex items-center gap-2">
                                <input type="radio" name="typ" value="modul" @checked(old('typ') === 'modul')>
                                <span>Modul</span>
                            </label>
                        </div>
                        <div class="mt-1 text-xs text-gray-600 dark:text-gray-300">
                            Semester wird automatisch anhand Prüfungsdatum gesetzt.
                        </div>
                    </div>

                    <div id="fachBlock">
                        <label class="text-sm font-medium">Fach</label>
                        <select name="fach_id"
                                class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100">
                            <option value="">Bitte wählen</option>
                            @foreach($faecher as $f)
                                <option value="{{ $f->fach_id }}" @selected(old('fach_id') == $f->fach_id)>
                                    {{ $f->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div id="modulBlock" class="hidden">
                        <label class="text-sm font-medium">Modul</label>
                        <select name="modul_id"
                                class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100">
                            <option value="">Bitte wählen</option>
                            @foreach($module as $m)
                                <option value="{{ $m->modul_id }}" @selected(old('modul_id') == $m->modul_id)>
                                    @if((int)($m->has_open_belegung ?? 0) === 1)
                                        ★
                                    @endif
                                    {{ $m->modul_nummer }} – {{ $m->titel }}
                                </option>
                            @endforeach
                        </select>
                        <div class="mt-1 text-xs text-gray-600 dark:text-gray-300">
                            ★ = Modul hat bereits eine offene Belegung (läuft schon).
                        </div>
                    </div>

                    <div>
                        <label class="text-sm font-medium">Titel (optional)</label>
                        <input name="titel" maxlength="150" value="{{ old('titel') }}"
                               class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="text-sm font-medium">Prüfungsdatum</label>
                            <input type="date" name="pruefungsdatum" required value="{{ old('pruefungsdatum') }}"
                                   class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100">
                        </div>

                        <div>
                            <label class="text-sm font-medium">Note</label>
                            <input type="number" name="note_wert" step="0.1" min="1" max="6" required value="{{ old('note_wert') }}"
                                   class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100">
                        </div>

                        <div>
                            <label class="text-sm font-medium">Gewichtung %</label>
                            <input type="number" name="gewichtung_prozent" step="0.01" min="0" max="100"
                                   value="{{ old('gewichtung_prozent', 100) }}"
                                   class="mt-1 w-full rounded-md border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100">
                        </div>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button class="inline-flex items-center px-4 py-2 rounded-md bg-blue-600 text-white hover:bg-blue-700">
                            Speichern
                        </button>

                        <a href="{{ route('lernender.noten.index') }}"
                           class="px-4 py-2 rounded-md bg-gray-200 text-gray-900 hover:bg-gray-300 dark:bg-gray-900 dark:text-gray-100 dark:hover:bg-gray-950">
                            Abbrechen
                        </a>
                    </div>
                </form>

                <script>
                    const fachBlock = document.getElementById('fachBlock');
                    const modulBlock = document.getElementById('modulBlock');
                    const typRadios = document.querySelectorAll('input[name="typ"]');

                    function syncBlocks() {
                        const typ = document.querySelector('input[name="typ"]:checked')?.value;
                        if (typ === 'modul') {
                            fachBlock.classList.add('hidden');
                            modulBlock.classList.remove('hidden');
                        } else {
                            modulBlock.classList.add('hidden');
                            fachBlock.classList.remove('hidden');
                        }
                    }

                    typRadios.forEach(r => r.addEventListener('change', syncBlocks));
                    syncBlocks();
                </script>

            </div>
        </div>
    </div>
</x-app-layout>
