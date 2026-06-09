{{-- resources/views/lernender/noten/create.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-text whitespace-nowrap">Neue Note</h2>

            <a href="{{ route('lernender.noten.index') }}"
               class="px-4 py-2 h-10 rounded-xl bg-card text-text border border-border hover:bg-card/60 whitespace-nowrap">
                Zur Übersicht
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-card border border-border rounded-2xl shadow-sm p-6">

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
                        Noch kein Track (BMS/ABU) und kein Lehrberuf mit Modulen konfiguriert.
                        Bitte den Admin bitten, Tracks und Module einzurichten, bevor eine Note erfasst werden kann.
                    </div>
                @endif

                <form method="POST" action="{{ route('lernender.noten.store') }}" class="space-y-5">
                    @csrf

                    {{-- Note + Gewichtung (Hero) --}}
                    <div class="grid grid-cols-1 sm:grid-cols-[auto_1fr] gap-4 items-end">
                        <div>
                            <label class="block text-xs uppercase tracking-wide text-muted mb-1">Note</label>
                            <input type="number" name="note_wert" step="0.1" min="1" max="6" required
                                   value="{{ old('note_wert') }}" autofocus
                                   class="w-[100px] max-w-[100px] text-2xl text-center font-bold rounded-xl border border-border bg-input text-text py-2.5
                                          focus:outline-none focus:ring-2 focus:ring-accent/50 focus:border-accent">
                        </div>
                        <div>
                            <label class="block text-xs uppercase tracking-wide text-muted mb-1">Gewichtung %</label>
                            <input type="number" name="gewichtung_prozent" step="0.01" min="0" max="100"
                                   value="{{ old('gewichtung_prozent', 100) }}"
                                   class="w-full rounded-xl border border-border bg-input text-text py-2.5 px-3
                                          focus:outline-none focus:ring-2 focus:ring-accent/50 focus:border-accent">
                        </div>
                    </div>

                    {{-- Kategorie + Typ --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs uppercase tracking-wide text-muted mb-1">Kategorie</label>
                            <select name="kategorie_id" required
                                    class="w-full rounded-xl border border-border bg-input text-text py-2.5 px-3
                                           focus:outline-none focus:ring-2 focus:ring-accent/50 focus:border-accent">
                                @foreach($kategorien as $k)
                                    <option value="{{ $k->kategorie_id }}" @selected(old('kategorie_id') == $k->kategorie_id)>
                                        {{ $k->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs uppercase tracking-wide text-muted mb-1">Typ</label>
                            <div class="flex gap-6 h-[42px] items-center px-1">
                                <label class="flex items-center gap-2">
                                    <input type="radio" name="typ" value="fach" @checked(old('typ','fach') === 'fach')
                                           class="text-accent focus:ring-accent/50">
                                    <span class="text-text">Fach</span>
                                </label>
                                <label class="flex items-center gap-2">
                                    <input type="radio" name="typ" value="modul" @checked(old('typ') === 'modul')
                                           class="text-accent focus:ring-accent/50">
                                    <span class="text-text">Modul</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    {{-- Datum + Fach/Modul --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs uppercase tracking-wide text-muted mb-1">Prüfungsdatum</label>
                            <input type="date" name="pruefungsdatum" required value="{{ old('pruefungsdatum') }}"
                                   class="w-full rounded-xl border border-border bg-input text-text py-2.5 px-3
                                          focus:outline-none focus:ring-2 focus:ring-accent/50 focus:border-accent">
                        </div>

                        <div id="fachBlock">
                            <label class="block text-xs uppercase tracking-wide text-muted mb-1">Fach</label>
                            <select name="fach_id"
                                    class="w-full rounded-xl border border-border bg-input text-text py-2.5 px-3
                                           focus:outline-none focus:ring-2 focus:ring-accent/50 focus:border-accent">
                                <option value="">Bitte wählen</option>
                                @foreach($faecher as $f)
                                    <option value="{{ $f->fach_id }}" @selected(old('fach_id') == $f->fach_id)>
                                        {{ $f->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div id="modulBlock" class="hidden">
                            <label class="block text-xs uppercase tracking-wide text-muted mb-1">Modul</label>
                            <select name="modul_id"
                                    class="w-full rounded-xl border border-border bg-input text-text py-2.5 px-3
                                           focus:outline-none focus:ring-2 focus:ring-accent/50 focus:border-accent">
                                <option value="">Bitte wählen</option>
                                @foreach($module as $m)
                                    <option value="{{ $m->modul_id }}" @selected(old('modul_id') == $m->modul_id)>
                                        @if((int)($m->has_open_belegung ?? 0) === 1) ★ @endif
                                        {{ $m->modul_nummer }} – {{ $m->titel }}
                                    </option>
                                @endforeach
                            </select>
                            <div class="mt-1 text-xs text-muted">
                                ★ = Modul hat bereits eine offene Belegung.
                            </div>
                        </div>
                    </div>

                    {{-- Titel --}}
                    <div>
                        <label class="block text-xs uppercase tracking-wide text-muted mb-1">Titel (optional)</label>
                        <input name="titel" maxlength="150" value="{{ old('titel') }}"
                               class="w-full rounded-xl border border-border bg-input text-text py-2.5 px-3
                                      focus:outline-none focus:ring-2 focus:ring-accent/50 focus:border-accent">
                    </div>

                    {{-- Submit --}}
                    <div class="pt-2 space-y-2">
                        <button type="submit"
                                class="w-full h-12 rounded-xl bg-accent text-white text-base font-semibold hover:opacity-90
                                       focus:outline-none focus:ring-2 focus:ring-accent/50">
                            Speichern
                        </button>
                        <a href="{{ route('lernender.noten.index') }}"
                           class="block w-full text-center px-4 py-2 rounded-xl text-muted hover:text-text text-sm">
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
