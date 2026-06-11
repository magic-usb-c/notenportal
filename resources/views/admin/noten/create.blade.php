<x-app-layout>
    <x-slot name="title">Neue Note</x-slot>
    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-text whitespace-nowrap">Note erfassen</h2>
                <p class="text-sm text-muted mt-0.5">Neue Note für Lernenden anlegen (Admin)</p>
            </div>
            <a href="{{ route('admin.lernende.noten.index', ['lernender_id' => $lernender_id]) }}"
               class="px-4 py-2 h-10 rounded-xl glass-btn text-text whitespace-nowrap text-sm">
                Zurück
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="glass rounded-2xl p-6">

                @if ($errors->any())
                    <div class="mb-4 rounded-xl border border-red-500/30 bg-card px-4 py-3 text-red-500">
                        <div class="font-semibold mb-2">Bitte prüfen:</div>
                        <ul class="list-disc pl-5 space-y-1 text-sm">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if($faecher->isEmpty() && $module->isEmpty())
                    <div class="mb-5 rounded-xl border border-yellow-300 bg-yellow-50 dark:bg-yellow-900/20 dark:border-yellow-700 px-4 py-3 text-sm text-yellow-800 dark:text-yellow-200">
                        Für diesen Lernenden sind noch keine Fächer oder Module konfiguriert.
                        Bitte zuerst Tracks und Module in den Stammdaten einrichten.
                    </div>
                @endif

                <form method="POST"
                      action="{{ route('admin.lernende.noten.store', ['lernender_id' => $lernender_id]) }}"
                      class="space-y-5">
                    @csrf

                    <div>
                        <label class="text-sm font-medium text-muted">Kategorie</label>
                        <select name="kategorie_id" required
                                class="mt-1 w-full rounded-xl border border-border bg-input text-text focus:ring-2 focus:ring-ring focus:border-ring">
                            @foreach($kategorien as $k)
                                <option value="{{ $k->kategorie_id }}" @selected(old('kategorie_id') == $k->kategorie_id)>
                                    {{ $k->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="text-sm font-medium text-muted">Typ</label>
                        <div class="mt-2 flex gap-6">
                            <label class="flex items-center gap-2">
                                <input type="radio" name="typ" value="fach" @checked(old('typ','fach') === 'fach')
                                       class="text-accent focus:ring-ring">
                                <span class="text-text">Fach</span>
                            </label>
                            <label class="flex items-center gap-2">
                                <input type="radio" name="typ" value="modul" @checked(old('typ') === 'modul')
                                       class="text-accent focus:ring-ring">
                                <span class="text-text">Modul</span>
                            </label>
                        </div>
                        <div class="mt-1 text-xs text-muted">
                            Semester wird automatisch anhand Prüfungsdatum gesetzt.
                        </div>
                    </div>

                    <div id="fachBlock">
                        <label class="text-sm font-medium text-muted">Fach</label>
                        <select name="fach_id"
                                class="mt-1 w-full rounded-xl border border-border bg-input text-text focus:ring-2 focus:ring-ring focus:border-ring">
                            <option value="">Bitte wählen</option>
                            @foreach($faecher as $f)
                                <option value="{{ $f->fach_id }}" @selected(old('fach_id') == $f->fach_id)>
                                    {{ $f->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div id="modulBlock" class="hidden">
                        <label class="text-sm font-medium text-muted">Modul</label>
                        <select name="modul_id"
                                class="mt-1 w-full rounded-xl border border-border bg-input text-text focus:ring-2 focus:ring-ring focus:border-ring">
                            <option value="">Bitte wählen</option>
                            @foreach($module as $m)
                                <option value="{{ $m->modul_id }}" @selected(old('modul_id') == $m->modul_id)>
                                    @if((int)($m->has_open_belegung ?? 0) === 1) ★ @endif
                                    {{ $m->modul_nummer }} – {{ $m->titel }}
                                </option>
                            @endforeach
                        </select>
                        <div class="mt-1 text-xs text-muted">★ = Modul hat bereits eine offene Belegung.</div>
                    </div>

                    <div>
                        <label class="text-sm font-medium text-muted">Titel (optional)</label>
                        <input name="titel" maxlength="150" value="{{ old('titel') }}"
                               class="mt-1 w-full rounded-xl border border-border bg-input text-text focus:ring-2 focus:ring-ring focus:border-ring">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="text-sm font-medium text-muted">Prüfungsdatum</label>
                            <input type="date" name="pruefungsdatum" required value="{{ old('pruefungsdatum') }}"
                                   class="mt-1 w-full rounded-xl border border-border bg-input text-text focus:ring-2 focus:ring-ring focus:border-ring">
                        </div>

                        <div>
                            <label class="text-sm font-medium text-muted">Note</label>
                            <input type="number" name="note_wert" step="0.1" min="1" max="6" required value="{{ old('note_wert') }}"
                                   class="mt-1 w-full rounded-xl border border-border bg-input text-text focus:ring-2 focus:ring-ring focus:border-ring">
                        </div>

                        <div>
                            <label class="text-sm font-medium text-muted">Gewichtung %</label>
                            <input type="number" name="gewichtung_prozent" step="0.01" min="0" max="100"
                                   value="{{ old('gewichtung_prozent', 100) }}"
                                   class="mt-1 w-full rounded-xl border border-border bg-input text-text focus:ring-2 focus:ring-ring focus:border-ring">
                            <div class="mt-1.5 flex gap-1.5">
                                @foreach([25, 50, 100] as $g)
                                    <button type="button"
                                            onclick="this.closest('div').parentElement.querySelector('input[name=gewichtung_prozent]').value = {{ $g }}"
                                            class="px-2 py-0.5 rounded-lg border border-border text-[11px] text-muted hover:text-text hover:bg-bg">
                                        {{ $g }}%
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button class="inline-flex items-center px-4 py-2 h-10 rounded-xl bg-accent text-white np-btn-primary">
                            Note speichern
                        </button>
                        <a href="{{ route('admin.lernende.noten.index', ['lernender_id' => $lernender_id]) }}"
                           class="px-4 py-2 h-10 rounded-xl glass-btn text-text">
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
