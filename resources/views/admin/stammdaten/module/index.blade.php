<x-app-layout>
    <x-slot name="title">Module</x-slot>
    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-text">Module</h2>
            <a href="{{ route('admin.master-data.modules.create') }}"
               class="inline-flex items-center gap-2 px-4 py-2 h-10 rounded-xl bg-accent text-white np-btn-primary">
                <span class="text-lg leading-none">+</span> Neues Modul
            </a>
        </div>
    </x-slot>

    @php
        $feld = 'mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 text-sm focus:ring-2 focus:ring-ring focus:border-ring';
        $aktiveFilter = collect([$suche, $lehrberufId, $kategorieId])->filter()->count();

        $zeile = function ($m) {
            return '<tr class="hover:bg-bg/50">
                <td class="px-4 py-3 font-mono font-semibold text-text">'.e($m->modul_nummer).'</td>
                <td class="px-4 py-3 text-text">'.e($m->titel).'</td>
                <td class="px-4 py-3 text-muted">'.e((string) $m->lehrberuf_count).'</td>
                <td class="px-4 py-3">'.($m->aktiv
                    ? '<span class="px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300">aktiv</span>'
                    : '<span class="px-2 py-0.5 rounded-full text-xs bg-bg text-muted border border-border">inaktiv</span>').'</td>
                <td class="px-4 py-3 text-right">
                    <a href="'.e(route('admin.master-data.modules.edit', $m->modul_id)).'" class="inline-flex items-center px-3 min-h-9 rounded-lg text-sm text-accent hover:bg-accent/10">Bearbeiten</a>
                </td>
            </tr>';
        };
    @endphp

    <div class="py-6">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">

            {{-- Suche + Filter --}}
            <div class="glass rounded-2xl p-4">
                <form method="GET" action="{{ route('admin.master-data.modules.index') }}"
                      class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end">
                    <div>
                        <label for="suche" class="text-xs uppercase tracking-widest text-muted font-medium">Suche</label>
                        <input type="search" name="suche" id="suche" value="{{ $suche }}" placeholder="Nummer oder Titel" class="{{ $feld }}">
                    </div>
                    <div>
                        <label for="lehrberuf_id" class="text-xs uppercase tracking-widest text-muted font-medium">Lehrberuf</label>
                        <select name="lehrberuf_id" id="lehrberuf_id" onchange="this.form.submit()" class="{{ $feld }}">
                            <option value="">Alle</option>
                            @foreach($lehrberufe as $lb)
                                <option value="{{ $lb->lehrberuf_id }}" @selected($lehrberufId === (int) $lb->lehrberuf_id)>{{ $lb->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="kategorie_id" class="text-xs uppercase tracking-widest text-muted font-medium">Lernort</label>
                        <select name="kategorie_id" id="kategorie_id" onchange="this.form.submit()" class="{{ $feld }}">
                            <option value="">Alle</option>
                            @foreach($kategorien as $k)
                                <option value="{{ $k->kategorie_id }}" @selected($kategorieId === (int) $k->kategorie_id)>{{ $k->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="gruppieren" class="text-xs uppercase tracking-widest text-muted font-medium">Gruppieren nach</label>
                        <select name="gruppieren" id="gruppieren" onchange="this.form.submit()" class="{{ $feld }}">
                            <option value="" @selected($gruppieren === '')>Keine Gruppierung</option>
                            <option value="lehrberuf" @selected($gruppieren === 'lehrberuf')>Lehrberuf</option>
                            <option value="lernort" @selected($gruppieren === 'lernort')>Lernort</option>
                        </select>
                    </div>
                    <div class="flex gap-2 lg:col-span-4">
                        <button type="submit" class="px-4 py-2 h-10 rounded-xl bg-accent text-white np-btn-primary text-sm">Filtern</button>
                        @if($aktiveFilter > 0 || $gruppieren !== '')
                            <a href="{{ route('admin.master-data.modules.index') }}" class="inline-flex items-center px-4 py-2 h-10 rounded-xl glass-btn text-text text-sm">Zurücksetzen</a>
                        @endif
                    </div>
                </form>
            </div>

            @if($gruppieren !== '')
                @forelse($gruppen as $name => $zeilen)
                    <div class="glass rounded-2xl overflow-hidden">
                        <div class="px-4 py-2.5 border-b border-border bg-bg/40">
                            <h3 class="text-sm font-semibold text-text">{{ $name }}</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-bg border-b border-border text-muted">
                                    <tr>
                                        <th class="px-4 py-3 text-left font-medium">Nummer</th>
                                        <th class="px-4 py-3 text-left font-medium">Titel</th>
                                        <th class="px-4 py-3 text-left font-medium">Lehrberufe</th>
                                        <th class="px-4 py-3 text-left font-medium">Status</th>
                                        <th class="px-4 py-3"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    @foreach($zeilen as $m)
                                        {!! $zeile($m) !!}
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @empty
                    <div class="glass rounded-2xl p-6 text-center text-muted">Keine Module gefunden.</div>
                @endforelse
            @else
                <div class="glass rounded-2xl overflow-hidden">
                    <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-bg border-b border-border text-muted">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium">Nummer</th>
                                <th class="px-4 py-3 text-left font-medium">Titel</th>
                                <th class="px-4 py-3 text-left font-medium">Lehrberufe</th>
                                <th class="px-4 py-3 text-left font-medium">Status</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @forelse($module as $m)
                                {!! $zeile($m) !!}
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-muted">
                                        @if($aktiveFilter > 0)
                                            Keine Module für diese Filtereinstellungen gefunden.
                                        @else
                                            Noch keine Module erfasst.
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                </div>
            @endif

            <p class="text-xs text-muted px-1">
                Module werden über die
                <a href="{{ route('admin.master-data.professions.index') }}" class="text-accent hover:underline">Lehrberuf-Detailseite</a>
                einem Lehrberuf zugewiesen.
            </p>

        </div>
    </div>
</x-app-layout>
