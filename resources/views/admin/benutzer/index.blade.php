<x-app-layout>
    <x-slot name="title">Systembenutzer</x-slot>
    <x-slot name="header">
        <x-seitenkopf titel="Benutzerverwaltung" :zaehler="$benutzer->count()">
            <x-slot:aktionen>
                <a href="{{ route('admin.users.create') }}"
                   class="inline-flex h-9 items-center gap-2 rounded-lg bg-accent px-3.5 text-sm font-medium text-accent-contrast np-btn-primary whitespace-nowrap">
                    Benutzer anlegen
                </a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-4">

            {{-- Filter --}}
            <div class="rounded-xl border border-border bg-card p-4">
                <form method="GET" action="{{ route('admin.users.index') }}"
                      class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end">

                    <div class="sm:col-span-2">
                        <label for="suche" class="text-xs uppercase tracking-widest text-muted font-medium">Suche</label>
                        <input type="text" name="suche" id="suche" value="{{ $suche }}"
                               placeholder="Name, E-Mail oder Benutzername…"
                               class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring text-sm">
                    </div>

                    <div>
                        <label for="rolle_id" class="text-xs uppercase tracking-widest text-muted font-medium">Rolle</label>
                        <select name="rolle_id" id="rolle_id" onchange="this.form.submit()"
                                class="mt-1 w-full rounded-xl border border-border bg-input text-text focus:ring-2 focus:ring-ring focus:border-ring text-sm">
                            <option value="">Alle Rollen</option>
                            @foreach($rollen as $r)
                                <option value="{{ $r->rolle_id }}" @selected($rolleId == $r->rolle_id)>{{ $r->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="status" class="text-xs uppercase tracking-widest text-muted font-medium">Status</label>
                        <div class="flex gap-2 mt-1">
                            <select name="status" id="status" onchange="this.form.submit()"
                                    class="flex-1 rounded-xl border border-border bg-input text-text focus:ring-2 focus:ring-ring focus:border-ring text-sm">
                                <option value="" @selected($status === '')>Alle</option>
                                <option value="aktiv" @selected($status === 'aktiv')>Aktiv</option>
                                <option value="inaktiv" @selected($status === 'inaktiv')>Inaktiv</option>
                            </select>
                            <button type="submit"
                                    class="px-4 py-2 h-10 rounded-xl bg-accent text-accent-contrast np-btn-primary whitespace-nowrap text-sm shrink-0">
                                Suchen
                            </button>
                            @if($suche || $rolleId || $status)
                                <a href="{{ route('admin.users.index') }}"
                                   class="px-3 py-2 h-10 rounded-xl glass-btn text-text whitespace-nowrap text-sm shrink-0 flex items-center">
                                    ×
                                </a>
                            @endif
                        </div>
                    </div>

                </form>
            </div>

            {{-- Kartenansicht mobil: Aktionen bleiben ohne seitliches Scrollen erreichbar --}}
            <div class="md:hidden divide-y divide-border rounded-xl border border-border bg-card overflow-hidden">
                @forelse($benutzer as $b)
                    @php
                        $initials = strtoupper(mb_substr($b->vorname ?? '', 0, 1) . mb_substr($b->nachname ?? '', 0, 1));
                    @endphp
                    <div class="p-4">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-9 h-9 rounded-full bg-accent/10 text-accent text-xs font-bold flex items-center justify-center shrink-0">
                                {{ $initials ?: '?' }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="font-medium text-text truncate">{{ $b->nachname }} {{ $b->vorname }}</div>
                                <div class="text-xs text-muted truncate">{{ $b->email }} · {{ $b->benutzername }}</div>
                            </div>
                            @if($b->aktiv)
                                <x-status status="gruen" text="Aktiv" class="shrink-0" />
                            @else
                                <x-status status="rot" text="Inaktiv" class="shrink-0" />
                            @endif
                        </div>
                        <div class="flex items-center justify-between gap-3 mt-3">
                            <span class="text-xs bg-bg border border-border rounded-lg px-2 py-0.5">{{ $b->rollen ?? '–' }}</span>
                            <div class="flex items-center gap-3 flex-wrap">
                                @if($b->lernender_id)
                                    <a href="{{ route('admin.learners.show', $b->lernender_id) }}"
                                       class="text-sm text-accent hover:underline">Verwalten</a>
                                @else
                                    <a href="{{ route('admin.users.edit', $b->benutzer_id) }}"
                                       class="text-sm text-accent hover:underline">Bearbeiten</a>
                                    <form method="POST"
                                          action="{{ route('admin.users.toggle-active', $b->benutzer_id) }}"
                                          class="inline"
                                          x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true"
                                          onsubmit="return confirm('Status wirklich ändern?');">
                                        @csrf
                                        <button :disabled="loading" class="text-sm {{ $b->aktiv ? 'text-note-ungenuegend hover:underline' : 'text-accent-text hover:underline' }} disabled:opacity-60 disabled:cursor-not-allowed">
                                            {{ $b->aktiv ? 'Deaktivieren' : 'Aktivieren' }}
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="p-5 text-center text-sm text-muted">
                        @if($suche || $rolleId || $status)
                            Keine Benutzer für diese Filtereinstellungen gefunden.
                        @else
                            Keine Benutzer gefunden.
                        @endif
                    </div>
                @endforelse
            </div>

            {{-- Tabelle --}}
            <div class="hidden md:block rounded-xl border border-border bg-card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm text-text">
                        <thead class="sticky top-0 z-10 bg-bg text-muted shadow-xs">
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
                                            <x-status status="gruen" text="Aktiv" />
                                        @else
                                            <x-status status="rot" text="Inaktiv" />
                                        @endif
                                    </td>
                                    <td class="p-3 text-right">
                                        <div class="flex items-center justify-end gap-3 flex-wrap">
                                            @if($b->lernender_id)
                                                <a href="{{ route('admin.learners.show', $b->lernender_id) }}"
                                                   class="text-sm text-accent hover:underline">Verwalten</a>
                                            @else
                                            <a href="{{ route('admin.users.edit', $b->benutzer_id) }}"
                                               class="text-sm text-accent hover:underline">Bearbeiten</a>
                                            <form method="POST"
                                                  action="{{ route('admin.users.toggle-active', $b->benutzer_id) }}"
                                                  class="inline"
                                                  x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true"
                                                  onsubmit="return confirm('Status wirklich ändern?');">
                                                @csrf
                                                <button :disabled="loading" class="text-sm {{ $b->aktiv ? 'text-note-ungenuegend hover:underline' : 'text-accent-text hover:underline' }} disabled:opacity-60 disabled:cursor-not-allowed">
                                                    {{ $b->aktiv ? 'Deaktivieren' : 'Aktivieren' }}
                                                </button>
                                            </form>
                                            @endif
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
