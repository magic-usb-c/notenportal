<x-app-layout>
    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-text">
                Noten:
                <span class="text-muted">{{ $selectedLernender->nachname ?? '' }} {{ $selectedLernender->vorname ?? '' }}</span>
            </h2>
            <a href="{{ route('admin.lernende.index') }}"
               class="px-4 py-2 h-10 rounded-xl bg-card text-text border border-border hover:bg-bg whitespace-nowrap">
                Lernenden wechseln
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">

            {{-- Lernenden-Switcher --}}
            <div class="bg-card border border-border rounded-2xl shadow-sm p-4">
                <label class="text-sm font-medium text-muted">Lernenden wechseln</label>
                <select class="mt-1 w-full sm:w-80 rounded-xl border border-border bg-input text-text focus:ring-2 focus:ring-ring focus:border-ring"
                        onchange="if(this.value) window.location.href=this.value">
                    @foreach($lernende as $l)
                        <option
                            value="{{ route('admin.lernende.noten.index', ['lernender_id' => $l->lernender_id]) }}"
                            @selected((int)$l->lernender_id === (int)$selectedLernenderId)
                        >
                            {{ $l->nachname }} {{ $l->vorname }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Filter --}}
            <div class="bg-card border border-border rounded-2xl shadow-sm p-4">
                <form method="GET"
                      action="{{ route('admin.lernende.noten.index', ['lernender_id' => $selectedLernenderId]) }}"
                      class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">

                    <div>
                        <label class="text-sm font-medium text-muted">Kategorie</label>
                        <select name="kategorie_id"
                                class="mt-1 w-full rounded-xl border border-border bg-input text-text focus:ring-2 focus:ring-ring focus:border-ring">
                            <option value="">Alle</option>
                            @foreach($kategorien as $k)
                                <option value="{{ $k->kategorie_id }}" @selected(request('kategorie_id') == $k->kategorie_id)>
                                    {{ $k->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="text-sm font-medium text-muted">Semester</label>
                        <select name="semester_id"
                                class="mt-1 w-full rounded-xl border border-border bg-input text-text focus:ring-2 focus:ring-ring focus:border-ring">
                            <option value="">Alle</option>
                            @foreach($semester as $s)
                                <option value="{{ $s->semester_id }}" @selected(request('semester_id') == $s->semester_id)>
                                    {{ $s->bezeichnung }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex gap-2">
                        <button class="px-4 py-2 h-10 rounded-xl bg-accent text-white hover:opacity-90">
                            Filtern
                        </button>
                        <a href="{{ route('admin.lernende.noten.index', ['lernender_id' => $selectedLernenderId]) }}"
                           class="px-4 py-2 h-10 rounded-xl bg-card text-text border border-border hover:bg-bg">
                            Reset
                        </a>
                    </div>
                </form>
            </div>

            {{-- Noten-Tabelle --}}
            <div class="bg-card border border-border rounded-2xl shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm text-text">
                        <thead class="bg-bg text-muted">
                            <tr>
                                <th class="text-left p-3 whitespace-nowrap">Datum</th>
                                <th class="text-left p-3 whitespace-nowrap">Kategorie</th>
                                <th class="text-left p-3">Fach / Modul</th>
                                <th class="text-left p-3">Titel</th>
                                <th class="text-right p-3 whitespace-nowrap">Note</th>
                                <th class="text-right p-3 whitespace-nowrap">Gew. %</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @forelse($notes as $n)
                                <tr class="hover:bg-bg">
                                    <td class="p-3 whitespace-nowrap">{{ optional($n->pruefungsdatum)->format('d.m.Y') }}</td>
                                    <td class="p-3 whitespace-nowrap">{{ $n->kategorie?->name ?? '-' }}</td>
                                    <td class="p-3">
                                        @if($n->fach)
                                            {{ $n->fach->name }}
                                        @elseif($n->modulBelegung?->modul)
                                            {{ $n->modulBelegung->modul->modul_nummer }} – {{ $n->modulBelegung->modul->titel }}
                                            @if($n->gruppe)
                                                <div class="text-xs text-muted">Gruppe: {{ $n->gruppe->bezeichnung }}</div>
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
                                    <td colspan="6" class="p-5 text-center text-muted">Keine Noten gefunden.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($notes->hasPages())
                    <div class="p-3 border-t border-border">
                        {{ $notes->links() }}
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
