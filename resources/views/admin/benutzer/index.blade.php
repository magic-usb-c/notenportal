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

    <?php $aktiveFilter = collect([$suche, $rolleId, $status])->filter()->count(); ?>

    <div class="py-6">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-4">

            <x-filterleiste :action="route('admin.users.index')" suche-name="suche" :suche-wert="$suche"
                             suche-platzhalter="Name, E-Mail oder Benutzername" :zaehler="$benutzer->count()"
                             :zurueck="route('admin.users.index')" :aktive-filter="$aktiveFilter">
                <label for="rolle_id" class="sr-only">Rolle</label>
                <select name="rolle_id" id="rolle_id" x-on:change="$el.form.requestSubmit()"
                        class="h-9 rounded-lg border border-border-strong/60 bg-input px-2.5 text-sm text-text focus:border-accent focus:ring-2 focus:ring-ring/30 sm:w-44">
                    <option value="">Alle Rollen</option>
                    @foreach($rollen as $r)
                        <option value="{{ $r->rolle_id }}" @selected($rolleId == $r->rolle_id)>{{ $r->name }}</option>
                    @endforeach
                </select>

                <label for="status" class="sr-only">Status</label>
                <select name="status" id="status" x-on:change="$el.form.requestSubmit()"
                        class="h-9 rounded-lg border border-border-strong/60 bg-input px-2.5 text-sm text-text focus:border-accent focus:ring-2 focus:ring-ring/30 sm:w-36">
                    <option value="" @selected($status === '')>Status: alle</option>
                    <option value="aktiv" @selected($status === 'aktiv')>Aktiv</option>
                    <option value="inaktiv" @selected($status === 'inaktiv')>Inaktiv</option>
                </select>
            </x-filterleiste>

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
                    <table class="w-full text-sm text-text">
                        <thead class="sticky top-0 z-10 bg-surface-2">
                            <tr>
                                <th class="h-9 px-3 text-left text-2xs font-medium text-muted">Benutzer</th>
                                <th class="h-9 px-3 text-left text-2xs font-medium text-muted whitespace-nowrap">Benutzername</th>
                                <th class="h-9 px-3 text-left text-2xs font-medium text-muted">Rollen</th>
                                <th class="h-9 px-3 text-left text-2xs font-medium text-muted whitespace-nowrap">Status</th>
                                <th class="h-9 px-3 text-right"><span class="sr-only">Aktionen</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @forelse($benutzer as $b)
                                @php
                                    $initials = strtoupper(mb_substr($b->vorname ?? '', 0, 1) . mb_substr($b->nachname ?? '', 0, 1));
                                @endphp
                                <tr class="group h-11 border-b border-border last:border-0 hover:bg-surface-2/60">
                                    <td class="px-3">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <div class="w-8 h-8 rounded-full bg-accent/10 text-accent text-xs font-bold flex items-center justify-center shrink-0">
                                                {{ $initials ?: '?' }}
                                            </div>
                                            <div class="min-w-0">
                                                <div class="font-medium text-text truncate">{{ $b->nachname }} {{ $b->vorname }}</div>
                                                <div class="text-xs text-muted truncate">{{ $b->email }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 font-mono text-xs">{{ $b->benutzername }}</td>
                                    <td class="px-3">
                                        <span class="text-xs bg-surface-2 border border-border rounded-md px-2 py-0.5">
                                            {{ $b->rollen ?? '–' }}
                                        </span>
                                    </td>
                                    <td class="px-3">
                                        @if($b->aktiv)
                                            <x-status status="gruen" text="Aktiv" />
                                        @else
                                            <x-status status="rot" text="Inaktiv" />
                                        @endif
                                    </td>
                                    <td class="px-3 text-right">
                                        <div class="flex items-center justify-end gap-3 flex-wrap opacity-0 group-hover:opacity-100 group-focus-within:opacity-100 max-md:opacity-100">
                                            @if($b->lernender_id)
                                                <a href="{{ route('admin.learners.show', $b->lernender_id) }}"
                                                   class="text-sm text-accent-text hover:underline">Verwalten</a>
                                            @else
                                            <a href="{{ route('admin.users.edit', $b->benutzer_id) }}"
                                               class="text-sm text-accent-text hover:underline">Bearbeiten</a>
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
