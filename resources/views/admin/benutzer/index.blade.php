<x-app-layout>
    <x-slot name="title">Systembenutzer</x-slot>
    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <h2 class="font-semibold text-xl text-text">Benutzerverwaltung</h2>
                <div class="text-sm text-muted pl-4 border-l border-border">
                    <span class="text-lg font-bold text-text tabular-nums">{{ $benutzer->count() }}</span>
                    Benutzer
                </div>
            </div>
            <a href="{{ route('admin.benutzer.create') }}"
               class="px-4 py-2 h-10 rounded-xl bg-accent text-white np-btn-primary whitespace-nowrap text-sm">
                + Benutzer anlegen
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">



            {{-- Filter --}}
            <div class="glass rounded-2xl p-4">
                <form method="GET" action="{{ route('admin.benutzer.index') }}"
                      class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">

                    <div class="sm:col-span-2">
                        <label class="text-sm font-medium text-muted">Suche</label>
                        <input type="text" name="suche" id="suche" value="{{ $suche }}"
                               placeholder="Name, E-Mail oder Benutzername…"
                               class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring text-sm">
                    </div>

                    <div>
                        <label class="text-sm font-medium text-muted">Rolle</label>
                        <select name="rolle_id" onchange="this.form.submit()"
                                class="mt-1 w-full rounded-xl border border-border bg-input text-text focus:ring-2 focus:ring-ring focus:border-ring text-sm">
                            <option value="">Alle Rollen</option>
                            @foreach($rollen as $r)
                                <option value="{{ $r->rolle_id }}" @selected($rolleId == $r->rolle_id)>{{ $r->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="text-sm font-medium text-muted">Status</label>
                        <div class="flex gap-2 mt-1">
                            <select name="status" onchange="this.form.submit()"
                                    class="flex-1 rounded-xl border border-border bg-input text-text focus:ring-2 focus:ring-ring focus:border-ring text-sm">
                                <option value="" @selected($status === '')>Alle</option>
                                <option value="aktiv" @selected($status === 'aktiv')>Aktiv</option>
                                <option value="inaktiv" @selected($status === 'inaktiv')>Inaktiv</option>
                            </select>
                            <button type="submit"
                                    class="px-4 py-2 h-10 rounded-xl bg-accent text-white np-btn-primary whitespace-nowrap text-sm shrink-0">
                                Suchen
                            </button>
                            @if($suche || $rolleId || $status)
                                <a href="{{ route('admin.benutzer.index') }}"
                                   class="px-3 py-2 h-10 rounded-xl glass-btn text-text whitespace-nowrap text-sm shrink-0 flex items-center">
                                    ×
                                </a>
                            @endif
                        </div>
                    </div>

                </form>
            </div>

            {{-- Tabelle --}}
            <div class="glass rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm text-text">
                        <thead class="sticky top-0 z-10 bg-bg text-muted shadow-sm">
                            <tr>
                                <th class="text-left p-3">Benutzer</th>
                                <th class="text-left p-3 whitespace-nowrap">Benutzername</th>
                                <th class="text-left p-3">Rollen</th>
                                <th class="text-center p-3 whitespace-nowrap">Status</th>
                                <th class="text-right p-3">Aktion</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @forelse($benutzer as $b)
                                @php
                                    $initials = strtoupper(mb_substr($b->vorname ?? '', 0, 1) . mb_substr($b->nachname ?? '', 0, 1));
                                @endphp
                                <tr class="even:bg-bg/30 hover:bg-accent/5 transition-colors duration-100">
                                    <td class="p-3">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <div class="w-9 h-9 rounded-full bg-accent/10 text-accent text-xs font-bold flex items-center justify-center shrink-0">
                                                {{ $initials ?: '?' }}
                                            </div>
                                            <div class="min-w-0">
                                                <div class="font-medium text-text truncate">{{ $b->nachname }} {{ $b->vorname }}</div>
                                                <div class="text-xs text-muted truncate">{{ $b->email }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="p-3 font-mono text-xs">{{ $b->benutzername }}</td>
                                    <td class="p-3">
                                        <span class="text-xs bg-bg border border-border rounded-lg px-2 py-0.5">
                                            {{ $b->rollen ?? '–' }}
                                        </span>
                                    </td>
                                    <td class="p-3 text-center">
                                        @if($b->aktiv)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300">
                                                Aktiv
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300">
                                                Inaktiv
                                            </span>
                                        @endif
                                    </td>
                                    <td class="p-3 text-right">
                                        <div class="flex items-center justify-end gap-3 flex-wrap">
                                            @if($b->lernender_id)
                                                <a href="{{ route('admin.lernende.show', $b->lernender_id) }}"
                                                   class="text-xs text-muted hover:text-accent whitespace-nowrap">Lernenden-Profil</a>
                                            @endif
                                            <a href="{{ route('admin.benutzer.edit', $b->benutzer_id) }}"
                                               class="text-sm text-accent hover:underline">Bearbeiten</a>
                                            <form method="POST"
                                                  action="{{ route('admin.benutzer.toggle-aktiv', $b->benutzer_id) }}"
                                                  class="inline"
                                                  x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true"
                                                  onsubmit="return confirm('Status wirklich ändern?');">
                                                @csrf
                                                <button :disabled="loading" class="text-sm {{ $b->aktiv ? 'text-red-500 hover:underline' : 'text-green-600 hover:underline' }} disabled:opacity-60 disabled:cursor-not-allowed">
                                                    {{ $b->aktiv ? 'Deaktivieren' : 'Aktivieren' }}
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="p-5 text-center text-muted">
                                        @if($suche || $rolleId || $status)
                                            Keine Benutzer für diese Filtereinstellungen gefunden.
                                        @else
                                            Keine Benutzer gefunden.
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($benutzer->isNotEmpty())
                    <div class="px-4 py-2 border-t border-border text-xs text-muted">
                        {{ $benutzer->count() }} Benutzer
                    </div>
                @endif
            </div>

        </div>
    </div>

    <script>
        // Schnellsuche (Ctrl+K aus der Nav): #suche-Hash fokussiert das Suchfeld
        document.addEventListener('DOMContentLoaded', () => {
            if (window.location.hash === '#suche') {
                document.getElementById('suche')?.focus();
            }
        });
    </script>
</x-app-layout>
