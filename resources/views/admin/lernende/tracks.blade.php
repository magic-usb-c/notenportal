<x-app-layout>
    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-text">
                Tracks (BMS / ABU):
                <span class="text-muted">{{ $lernender->nachname }} {{ $lernender->vorname }}</span>
            </h2>
            <a href="{{ route('admin.lernende.index') }}"
               class="px-4 py-2 h-10 rounded-xl bg-card text-text border border-border hover:bg-bg whitespace-nowrap text-sm">
                Zurück
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if(session('status'))
                <div class="bg-green-100 dark:bg-green-900/30 border border-green-300 dark:border-green-700 text-green-800 dark:text-green-200 rounded-xl px-4 py-3 text-sm" data-autohide>
                    {{ session('status') }}
                </div>
            @endif

            @if($errors->any())
                <div class="bg-red-100 dark:bg-red-900/30 border border-red-300 dark:border-red-700 text-red-800 dark:text-red-200 rounded-xl px-4 py-3 text-sm space-y-1">
                    @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
                </div>
            @endif

            {{-- Bestehende Tracks --}}
            <div class="bg-card border border-border rounded-2xl shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-border font-semibold text-text">Aktive und abgeschlossene Tracks</div>

                @forelse($tracks as $t)
                    <div class="flex items-center justify-between px-5 py-3 border-b border-border last:border-0 hover:bg-bg">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold
                                    {{ $t->track_typ === 'BMS'
                                        ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300'
                                        : 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300' }}">
                                    {{ $t->track_typ }}
                                </span>
                                <span class="text-sm text-text font-medium">
                                    {{ $t->start_semester ?? \Carbon\Carbon::parse($t->start_datum)->format('d.m.Y') }}
                                </span>
                            </div>
                            <div class="text-xs text-muted mt-0.5">
                                {{ \Carbon\Carbon::parse($t->start_datum)->format('d.m.Y') }}
                                –
                                {{ $t->end_datum ? \Carbon\Carbon::parse($t->end_datum)->format('d.m.Y') . ' (' . ($t->end_semester ?? '') . ')' : 'offen' }}
                            </div>
                        </div>
                        @if(!$t->end_datum)
                            <form method="POST"
                                  action="{{ route('admin.tracks.beenden', $t->lernender_track_id) }}"
                                  class="flex items-center gap-2"
                                  onsubmit="return confirm('Track wirklich beenden?');">
                                @csrf
                                <select name="end_semester_id" required
                                        class="rounded-xl border border-border bg-input text-text text-sm px-2 py-1.5 focus:ring-2 focus:ring-ring">
                                    <option value="">Endsemester…</option>
                                    @foreach($semester as $s)
                                        <option value="{{ $s->semester_id }}">{{ $s->bezeichnung }}</option>
                                    @endforeach
                                </select>
                                <button class="px-3 py-1.5 rounded-xl bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300 text-sm hover:opacity-80 whitespace-nowrap">
                                    Beenden
                                </button>
                            </form>
                        @else
                            <span class="text-xs text-muted">Abgeschlossen</span>
                        @endif
                    </div>
                @empty
                    <div class="px-5 py-5 text-sm text-muted text-center">Noch keine Tracks erfasst.</div>
                @endforelse
            </div>

            {{-- Neuen Track hinzufügen --}}
            <div class="bg-card border border-border rounded-2xl shadow-sm p-5">
                <div class="font-semibold text-text mb-4">Neuen Track hinzufügen</div>

                <form method="POST"
                      action="{{ route('admin.lernende.tracks.store', $lernender_id) }}"
                      class="space-y-4">
                    @csrf

                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label class="text-sm font-medium text-muted">Track-Typ</label>
                            <select name="track_typ" required
                                    class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring">
                                <option value="BMS" @selected(old('track_typ') === 'BMS')>BMS</option>
                                <option value="ABU" @selected(old('track_typ') === 'ABU')>ABU</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-muted">Startsemester</label>
                            <select name="start_semester_id" required
                                    class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring">
                                <option value="">Bitte wählen…</option>
                                @foreach($semester as $s)
                                    <option value="{{ $s->semester_id }}" @selected(old('start_semester_id') == $s->semester_id)>
                                        {{ $s->bezeichnung }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-muted">Startdatum</label>
                            <input type="date" name="start_datum" value="{{ old('start_datum', now()->toDateString()) }}" required
                                   class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring">
                        </div>
                    </div>

                    <button type="submit"
                            class="w-full px-4 py-2 rounded-xl bg-accent text-white np-btn-primary font-medium">
                        Track speichern
                    </button>
                </form>
            </div>

        </div>
    </div>
</x-app-layout>
