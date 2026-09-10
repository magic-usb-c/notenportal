<x-app-layout>
    <x-slot name="title">Lehrberuf</x-slot>
    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-text">{{ $lehrberuf->name }}</h2>
                <span class="text-sm text-muted font-mono">{{ $lehrberuf->kuerzel }}</span>
            </div>
            <a href="{{ route('admin.stammdaten.lehrberufe.index') }}"
               class="px-4 py-2 h-10 rounded-xl glass-btn text-text text-sm">
                Zurück
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">


            {{-- ========== MODULE ========== --}}
            <div class="glass rounded-2xl p-5 space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="font-semibold text-text">Module</h3>
                    <span class="text-xs text-muted">{{ $zugewieseneModule->count() }} zugewiesen</span>
                </div>

                {{-- Zugewiesene Module --}}
                @if($zugewieseneModule->isNotEmpty())
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="text-muted border-b border-border">
                                <tr>
                                    <th class="py-2 pr-4 text-left font-medium">Nummer</th>
                                    <th class="py-2 pr-4 text-left font-medium">Titel</th>
                                    <th class="py-2 pr-4 text-left font-medium">Lernort</th>
                                    <th class="py-2 pr-4 text-center font-medium">Pflicht</th>
                                    <th class="py-2 pr-4 text-left font-medium">Semester</th>
                                    <th class="py-2 pr-4 text-center font-medium">Aktiv</th>
                                    <th class="py-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                @foreach($zugewieseneModule as $m)
                                    @php $formular = 'lbm-'.$m->modul_id; @endphp
                                    <tr @class(['opacity-60' => ! $m->aktiv])>
                                        <td class="py-2 pr-4 font-mono text-text">{{ $m->modul_nummer }}</td>
                                        <td class="py-2 pr-4 text-text">{{ $m->titel }}</td>
                                        <td class="py-2 pr-4">
                                            <label for="lernort_{{ $m->modul_id }}" class="sr-only">Lernort für {{ $m->titel }}</label>
                                            <select id="lernort_{{ $m->modul_id }}" name="kategorie_id" form="{{ $formular }}"
                                                    onchange="this.form.dataset.sendet || (this.form.dataset.sendet = 1, this.form.requestSubmit())"
                                                    class="h-9 min-w-40 rounded-lg border border-border bg-input text-text pl-2 pr-8 text-xs focus:ring-2 focus:ring-ring">
                                                @foreach($kategorien as $k)
                                                    <option value="{{ $k->kategorie_id }}" @selected($m->kategorie_id == $k->kategorie_id)>{{ $k->name }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="py-2 pr-4 text-center">
                                            <input type="hidden" name="pflicht" value="0" form="{{ $formular }}">
                                            <label class="inline-flex items-center justify-center min-w-9 min-h-9 cursor-pointer"><input type="checkbox" name="pflicht" value="1" form="{{ $formular }}" @checked($m->pflicht) onchange="this.form.dataset.sendet || (this.form.dataset.sendet = 1, this.form.requestSubmit())"
                                                   aria-label="Pflichtmodul {{ $m->modul_nummer }}" class="w-5 h-5 rounded border-border text-accent focus:ring-ring"></label>
                                        </td>
                                        <td class="py-2 pr-4">
                                            <input type="number" name="empfohlenes_lehrsemester_nr" min="1" max="12" value="{{ $m->empfohlenes_lehrsemester_nr }}" form="{{ $formular }}"
                                                   onchange="this.form.dataset.sendet || (this.form.dataset.sendet = 1, this.form.requestSubmit())" placeholder="–" aria-label="Empfohlenes Semester {{ $m->modul_nummer }}"
                                                   class="h-9 w-16 rounded-lg border border-border bg-input text-text px-2 text-xs tabular-nums focus:ring-2 focus:ring-ring">
                                        </td>
                                        <td class="py-2 pr-4 text-center">
                                            <input type="hidden" name="aktiv" value="0" form="{{ $formular }}">
                                            <label class="inline-flex items-center justify-center min-w-9 min-h-9 cursor-pointer"><input type="checkbox" name="aktiv" value="1" form="{{ $formular }}" @checked($m->aktiv) onchange="this.form.dataset.sendet || (this.form.dataset.sendet = 1, this.form.requestSubmit())"
                                                   aria-label="Modul {{ $m->modul_nummer }} aktiv" class="w-5 h-5 rounded border-border text-accent focus:ring-ring"></label>
                                        </td>
                                        <td class="py-2 text-right">
                                            <form id="{{ $formular }}" method="POST" class="hidden"
                                                  action="{{ route('admin.stammdaten.lehrberufe.module.update', [$lehrberuf->lehrberuf_id, $m->modul_id]) }}">
                                                @csrf @method('PATCH')
                                            </form>
                                            <form method="POST"
                                                  action="{{ route('admin.stammdaten.lehrberufe.module.remove', [$lehrberuf->lehrberuf_id, $m->modul_id]) }}"
                                                  onsubmit="return confirm('Modul {{ $m->modul_nummer }} entfernen?')"
                                                  x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                                                @csrf @method('DELETE')
                                                <button :disabled="loading" class="inline-flex items-center px-3 min-h-9 rounded-lg text-xs text-red-600 dark:text-red-400 hover:bg-red-500/10 disabled:opacity-60 disabled:cursor-not-allowed">Entfernen</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-sm text-muted">Noch keine Module zugewiesen.</p>
                @endif

                {{-- Modul zuweisen --}}
                @if($verfuegbareModule->isNotEmpty())
                    <form method="POST"
                          action="{{ route('admin.stammdaten.lehrberufe.module.assign', $lehrberuf->lehrberuf_id) }}"
                          class="border-t border-border pt-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-3 items-end"
                          x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                        @csrf
                        <div class="lg:col-span-2">
                            <label for="modul_id" class="text-xs uppercase tracking-widest text-muted font-medium">Modul hinzufügen</label>
                            <select id="modul_id" name="modul_id" required
                                    class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 text-sm focus:ring-2 focus:ring-ring">
                                <option value="">Bitte wählen…</option>
                                @foreach($verfuegbareModule as $m)
                                    <option value="{{ $m->modul_id }}">{{ $m->modul_nummer }} – {{ $m->titel }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="kategorie_id" class="text-xs uppercase tracking-widest text-muted font-medium">Lernort *</label>
                            <select id="kategorie_id" name="kategorie_id" required
                                    class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 text-sm focus:ring-2 focus:ring-ring">
                                @foreach($kategorien as $k)
                                    <option value="{{ $k->kategorie_id }}" @selected($k->code === 'FACH')>{{ $k->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="empfohlenes_lehrsemester_nr" class="text-xs uppercase tracking-widest text-muted font-medium">Empfohlenes Semester</label>
                            <input type="number" id="empfohlenes_lehrsemester_nr" name="empfohlenes_lehrsemester_nr" min="1" max="12"
                                   class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 text-sm focus:ring-2 focus:ring-ring">
                        </div>
                        <div class="flex items-end gap-2">
                            <label class="flex items-center gap-2 text-sm text-text">
                                <input type="checkbox" name="pflicht" value="1" checked
                                       class="rounded-sm border-border text-accent focus:ring-ring">
                                Pflichtmodul
                            </label>
                        </div>
                        <div>
                            <button type="submit" :disabled="loading"
                                    class="w-full px-4 py-2 h-10 rounded-xl bg-accent text-white np-btn-primary text-sm disabled:opacity-60 disabled:cursor-not-allowed">
                                Zuweisen
                            </button>
                        </div>
                    </form>
                @endif
            </div>

            {{-- ========== FÄCHER ========== --}}
            <div class="glass rounded-2xl p-5 space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="font-semibold text-text">Fächer (berufsspezifisch)</h3>
                    <span class="text-xs text-muted">{{ $zugewieseneFaecher->count() }} zugewiesen</span>
                </div>

                @if($zugewieseneFaecher->isNotEmpty())
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="text-muted border-b border-border">
                                <tr>
                                    <th class="py-2 pr-4 text-left font-medium">Kürzel</th>
                                    <th class="py-2 pr-4 text-left font-medium">Name</th>
                                    <th class="py-2 pr-4 text-left font-medium">Track</th>
                                    <th class="py-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                @foreach($zugewieseneFaecher as $f)
                                    <tr>
                                        <td class="py-2 pr-4 font-mono text-text">{{ $f->kurzname }}</td>
                                        <td class="py-2 pr-4 text-text">{{ $f->name }}</td>
                                        <td class="py-2 pr-4">
                                            @if($f->track_typ)
                                                <span class="px-2 py-0.5 rounded-full text-xs
                                                    bg-accent/10 text-accent">
                                                    {{ $f->track_typ }}
                                                </span>
                                            @else
                                                <span class="text-xs text-muted">–</span>
                                            @endif
                                        </td>
                                        <td class="py-2 text-right">
                                            <form method="POST"
                                                  action="{{ route('admin.stammdaten.lehrberufe.faecher.remove', [$lehrberuf->lehrberuf_id, $f->fach_id]) }}"
                                                  onsubmit="return confirm('Fach {{ $f->name }} entfernen?')"
                                                  x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                                                @csrf @method('DELETE')
                                                <button :disabled="loading" class="inline-flex items-center px-3 min-h-9 rounded-lg text-xs text-red-600 dark:text-red-400 hover:bg-red-500/10 disabled:opacity-60 disabled:cursor-not-allowed">Entfernen</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-sm text-muted">Noch keine Fächer zugewiesen.</p>
                @endif

                {{-- Fach zuweisen --}}
                @if($verfuegbareFaecher->isNotEmpty())
                    <form method="POST"
                          action="{{ route('admin.stammdaten.lehrberufe.faecher.assign', $lehrberuf->lehrberuf_id) }}"
                          class="border-t border-border pt-4 flex gap-3 items-end"
                          x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                        @csrf
                        <div class="flex-1">
                            <label for="fach_id" class="text-xs uppercase tracking-widest text-muted font-medium">Fach hinzufügen</label>
                            <select id="fach_id" name="fach_id" required
                                    class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 text-sm focus:ring-2 focus:ring-ring">
                                <option value="">Bitte wählen…</option>
                                @foreach($verfuegbareFaecher as $f)
                                    <option value="{{ $f->fach_id }}">{{ $f->kurzname }} – {{ $f->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" :disabled="loading"
                                class="px-4 py-2 h-10 rounded-xl bg-accent text-white np-btn-primary text-sm whitespace-nowrap disabled:opacity-60 disabled:cursor-not-allowed">
                            Zuweisen
                        </button>
                    </form>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
