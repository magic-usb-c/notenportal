{{-- resources/views/lernender/noten/edit.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-text whitespace-nowrap">Note bearbeiten</h2>

            <a href="{{ route('lernender.noten.index') }}"
               class="px-4 py-2 h-10 rounded-xl bg-card text-text border border-border hover:bg-card/60 whitespace-nowrap">
                Zur Übersicht
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-card border border-border rounded-3xl shadow-sm p-6 sm:p-8">

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

                @php
                    $typDefault = $note->fach_id ? 'fach' : 'modul';
                    $typ = old('typ', $typDefault);
                    $currentModulId = $note->modulBelegung?->modul_id;
                @endphp

                <form method="POST" action="{{ route('lernender.noten.update', $note->note_id) }}" class="space-y-5"
                      x-data="{ loading: false }" @submit="loading = true">
                    @csrf
                    @method('PUT')

                    {{-- Note (Hero, zentral, mit Live-Farbwechsel) --}}
                    <div x-data="{
                            wert: '{{ old('note_wert', $note->note_wert) }}',
                            get color() {
                                const v = parseFloat(this.wert);
                                if (!Number.isFinite(v)) return '';
                                if (v >= 5.0) return 'text-green-600 dark:text-green-400';
                                if (v >= 4.0) return 'text-emerald-600 dark:text-emerald-400';
                                if (v >= 3.5) return 'text-yellow-600 dark:text-yellow-400';
                                return 'text-red-600 dark:text-red-400';
                            }
                         }"
                         class="flex flex-col items-center gap-2 py-2">
                        <label class="text-xs uppercase tracking-widest text-muted font-medium">Note (1.0 – 6.0)</label>
                        <input type="number" name="note_wert" step="0.1" min="1" max="6" required
                               x-model="wert" :class="color"
                               value="{{ old('note_wert', $note->note_wert) }}" autofocus
                               class="w-32 h-20 text-4xl font-extrabold text-center tabular-nums rounded-2xl border-2 border-border bg-input text-text
                                      focus:border-accent focus:outline-none focus:ring-0 transition-colors duration-200">
                    </div>

                    {{-- Gewichtung + Datum --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs uppercase tracking-wide text-muted mb-1">Gewichtung %</label>
                            <input type="number" name="gewichtung_prozent" step="0.01" min="0" max="100"
                                   value="{{ old('gewichtung_prozent', $note->gewichtung_prozent ?? 100) }}"
                                   class="w-full rounded-xl border border-border bg-input text-text py-2.5 px-3
                                          focus:outline-none focus:ring-2 focus:ring-accent/50 focus:border-accent">
                        </div>
                        <div>
                            <label class="block text-xs uppercase tracking-wide text-muted mb-1">Prüfungsdatum</label>
                            <input type="date" name="pruefungsdatum" required
                                   value="{{ old('pruefungsdatum', \Illuminate\Support\Carbon::parse($note->pruefungsdatum)->format('Y-m-d')) }}"
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
                                    <option value="{{ $k->kategorie_id }}" @selected(old('kategorie_id', $note->kategorie_id) == $k->kategorie_id)>
                                        {{ $k->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs uppercase tracking-wide text-muted mb-1">Typ</label>
                            <div class="flex gap-6 h-[42px] items-center px-1">
                                <label class="flex items-center gap-2">
                                    <input type="radio" name="typ" value="fach" @checked($typ === 'fach')
                                           class="text-accent focus:ring-accent/50">
                                    <span class="text-text">Fach</span>
                                </label>
                                <label class="flex items-center gap-2">
                                    <input type="radio" name="typ" value="modul" @checked($typ === 'modul')
                                           class="text-accent focus:ring-accent/50">
                                    <span class="text-text">Modul</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    {{-- Fach/Modul --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div id="fachBlock">
                            <label class="block text-xs uppercase tracking-wide text-muted mb-1">Fach</label>
                            <select name="fach_id"
                                    class="w-full rounded-xl border border-border bg-input text-text py-2.5 px-3
                                           focus:outline-none focus:ring-2 focus:ring-accent/50 focus:border-accent">
                                <option value="">Bitte wählen</option>
                                @foreach($faecher as $f)
                                    <option value="{{ $f->fach_id }}" @selected(old('fach_id', $note->fach_id) == $f->fach_id)>
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
                                    @php $selected = old('modul_id', $currentModulId); @endphp
                                    <option value="{{ $m->modul_id }}" @selected((int)$selected === (int)$m->modul_id)>
                                        @if((int)($m->has_open_belegung ?? 0) === 1) ★ @endif
                                        {{ $m->modul_nummer }} – {{ $m->titel }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- Titel --}}
                    <div>
                        <label class="block text-xs uppercase tracking-wide text-muted mb-1">Titel (optional)</label>
                        <input name="titel" maxlength="150" value="{{ old('titel', $note->titel) }}"
                               class="w-full rounded-xl border border-border bg-input text-text py-2.5 px-3
                                      focus:outline-none focus:ring-2 focus:ring-accent/50 focus:border-accent">
                    </div>

                    {{-- Submit --}}
                    <div class="pt-2 space-y-2">
                        <button type="submit" :disabled="loading"
                                class="w-full h-12 rounded-xl bg-accent text-white text-base font-semibold hover:opacity-90
                                       disabled:opacity-60 disabled:cursor-not-allowed
                                       focus:outline-none focus:ring-2 focus:ring-accent/50 inline-flex items-center justify-center gap-2">
                            <svg x-show="loading" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                            </svg>
                            <span x-text="loading ? 'Wird gespeichert…' : 'Speichern'"></span>
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
