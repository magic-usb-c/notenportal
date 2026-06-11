<x-app-layout>
    <x-slot name="title">Note bearbeiten</x-slot>
    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-text whitespace-nowrap">Note bearbeiten</h2>
                <p class="text-sm text-muted mt-0.5">Datenkorrektion (Admin)</p>
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
                    <div class="mb-4 rounded-xl border border-red-500/30 bg-card px-4 py-3 text-red-600 dark:text-red-400">
                        <div class="font-semibold mb-2">Bitte prüfen:</div>
                        <ul class="list-disc pl-5 space-y-1 text-sm">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @php
                    $typDefault = $note->fach_id ? 'fach' : 'modul';
                    $typ = old('typ', $typDefault);
                    $currentModulId = $note->modulBelegung?->modul_id;
                @endphp

                <form method="POST"
                      action="{{ route('admin.lernende.noten.update', [$lernender_id, $note->note_id]) }}"
                      class="space-y-5"
                      x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                    @csrf
                    @method('PUT')

                    <div>
                        <label for="kategorie_id" class="text-sm font-medium text-muted">Kategorie</label>
                        <select name="kategorie_id" id="kategorie_id" required
                                class="mt-1 w-full rounded-xl border border-border bg-input text-text focus:ring-2 focus:ring-ring focus:border-ring">
                            @foreach($kategorien as $k)
                                <option value="{{ $k->kategorie_id }}" @selected(old('kategorie_id', $note->kategorie_id) == $k->kategorie_id)>
                                    {{ $k->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="text-sm font-medium text-muted">Typ</label>
                        <div class="mt-2 flex gap-6">
                            <label class="flex items-center gap-2">
                                <input type="radio" name="typ" value="fach" @checked($typ === 'fach')
                                       class="text-accent focus:ring-ring">
                                <span class="text-text">Fach</span>
                            </label>
                            <label class="flex items-center gap-2">
                                <input type="radio" name="typ" value="modul" @checked($typ === 'modul')
                                       class="text-accent focus:ring-ring">
                                <span class="text-text">Modul</span>
                            </label>
                        </div>
                        <div class="mt-1 text-xs text-muted">
                            Semester wird automatisch anhand Prüfungsdatum gesetzt.
                        </div>
                    </div>

                    <div id="fachBlock">
                        <label for="fach_id" class="text-sm font-medium text-muted">Fach</label>
                        <select name="fach_id" id="fach_id"
                                class="mt-1 w-full rounded-xl border border-border bg-input text-text focus:ring-2 focus:ring-ring focus:border-ring">
                            <option value="">Bitte wählen</option>
                            @foreach($faecher as $f)
                                <option value="{{ $f->fach_id }}" @selected(old('fach_id', $note->fach_id) == $f->fach_id)>
                                    {{ $f->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div id="modulBlock" class="hidden">
                        <label for="modul_id" class="text-sm font-medium text-muted">Modul</label>
                        <select name="modul_id" id="modul_id"
                                class="mt-1 w-full rounded-xl border border-border bg-input text-text focus:ring-2 focus:ring-ring focus:border-ring">
                            <option value="">Bitte wählen</option>
                            @foreach($module as $m)
                                @php $selected = old('modul_id', $currentModulId); @endphp
                                <option value="{{ $m->modul_id }}" @selected((int)$selected === (int)$m->modul_id)>
                                    @if((int)($m->has_open_belegung ?? 0) === 1) ★ @endif
                                    {{ $m->modul_nummer }} – {{ $m->titel }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="titel" class="text-sm font-medium text-muted">Titel (optional)</label>
                        <input name="titel" id="titel" maxlength="150" value="{{ old('titel', $note->titel) }}"
                               class="mt-1 w-full rounded-xl border border-border bg-input text-text focus:ring-2 focus:ring-ring focus:border-ring">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label for="pruefungsdatum" class="text-sm font-medium text-muted">Prüfungsdatum</label>
                            <input type="date" name="pruefungsdatum" id="pruefungsdatum" required
                                   value="{{ old('pruefungsdatum', \Carbon\Carbon::parse($note->pruefungsdatum)->format('Y-m-d')) }}"
                                   class="mt-1 w-full rounded-xl border border-border bg-input text-text focus:ring-2 focus:ring-ring focus:border-ring">
                        </div>

                        <div>
                            <label for="note_wert" class="text-sm font-medium text-muted">Note</label>
                            <input type="number" name="note_wert" id="note_wert" step="0.1" min="1" max="6" required
                                   value="{{ old('note_wert', $note->note_wert) }}"
                                   class="mt-1 w-full rounded-xl border border-border bg-input text-text focus:ring-2 focus:ring-ring focus:border-ring">
                        </div>

                        <div>
                            <label for="gewichtung_prozent" class="text-sm font-medium text-muted">Gewichtung %</label>
                            <input type="number" name="gewichtung_prozent" id="gewichtung_prozent" step="0.01" min="0" max="100"
                                   value="{{ old('gewichtung_prozent', $note->gewichtung_prozent ?? 100) }}"
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
                        <button :disabled="loading" class="inline-flex items-center px-4 py-2 h-10 rounded-xl bg-accent text-white np-btn-primary disabled:opacity-60 disabled:cursor-not-allowed">
                            Speichern
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
