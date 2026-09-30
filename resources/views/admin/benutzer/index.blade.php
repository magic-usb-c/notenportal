<x-app-layout>
    <x-slot name="title">{{ __('Systembenutzer') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :titel="__('Benutzerverwaltung')" :zaehler="$benutzer->count()">
            <x-slot:aktionen>
                <a href="{{ route('admin.users.create') }}"
                   class="np-knopf np-knopf-primaer">
                    {{ __('Benutzer anlegen') }}
                </a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    <?php $aktiveFilter = collect([$suche, $rolleId, $status])->filter()->count(); ?>

    <div class="py-6">
        <div class="mx-auto np-seite px-4 sm:px-6 lg:px-8 space-y-4">

            <x-filterleiste :action="route('admin.users.index')" suche-name="suche" :suche-wert="$suche"
                             :suche-platzhalter="__('Name, E-Mail oder Benutzername')" :zaehler="$benutzer->count()"
                             :zurueck="route('admin.users.index')" :aktive-filter="$aktiveFilter">
                <label for="rolle_id" class="sr-only">{{ __('Rolle') }}</label>
                <select name="rolle_id" id="rolle_id" x-on:change="$el.form.requestSubmit()"
                        class="np-feld np-feld-klein w-auto max-w-64">
                    <option value="">{{ __('Alle Rollen') }}</option>
                    @foreach($rollen as $r)
                        <option value="{{ $r->rolle_id }}" @selected($rolleId == $r->rolle_id)>{{ __($r->name) }}</option>
                    @endforeach
                </select>

                <label for="status" class="sr-only">{{ __('Status') }}</label>
                <select name="status" id="status" x-on:change="$el.form.requestSubmit()"
                        class="np-feld np-feld-klein w-auto max-w-64">
                    <option value="" @selected($status === '')>{{ __('Status: alle') }}</option>
                    <option value="aktiv" @selected($status === 'aktiv')>{{ __('Aktiv') }}</option>
                    <option value="inaktiv" @selected($status === 'inaktiv')>{{ __('Inaktiv') }}</option>
                </select>
            </x-filterleiste>

            {{-- Karten oder Tabelle je nach Breite des Inhalts, nicht des Fensters (Seitenleiste) --}}
            <div class="@container">
                {{-- Kartenansicht mobil: Aktionen bleiben ohne seitliches Scrollen erreichbar --}}
                <div class="np-karte @3xl:hidden divide-y divide-border overflow-hidden">
                    @forelse($benutzer as $b)
                        @php
                            $initials = strtoupper(mb_substr($b->vorname ?? '', 0, 1) . mb_substr($b->nachname ?? '', 0, 1));
                        @endphp
                        <div class="p-4">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="np-monogramm size-9 shrink-0 text-xs" aria-hidden="true">
                                    {{ $initials ?: '?' }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="font-medium text-text truncate">{{ $b->nachname }} {{ $b->vorname }}</div>
                                    <div class="text-xs text-muted truncate" title="{{ $b->email }} · {{ $b->benutzername }}">{{ $b->email }} · {{ $b->benutzername }}</div>
                                </div>
                                @if($b->aktiv)
                                    <x-status status="gruen" :text="__('Aktiv')" class="shrink-0" />
                                @else
                                    <x-status status="rot" :text="__('Inaktiv')" class="shrink-0" />
                                @endif
                            </div>
                            <div class="flex items-center justify-between gap-3 mt-3">
                                <span class="np-marke font-medium text-muted">{{ $b->rollen ? implode(', ', array_map('__', explode(', ', $b->rollen))) : '–' }}</span>
                                <div class="flex items-center gap-1 flex-wrap">
                                    @if($b->lernender_id)
                                        <a href="{{ route('admin.learners.show', $b->lernender_id) }}"
                                           class="np-knopf np-knopf-schlicht np-knopf-klein np-ziel">{{ __('Verwalten') }}</a>
                                    @else
                                        <a href="{{ route('admin.users.edit', $b->benutzer_id) }}"
                                           class="np-knopf np-knopf-schlicht np-knopf-klein np-ziel">{{ __('Bearbeiten') }}</a>
                                        <form method="POST"
                                              action="{{ route('admin.users.toggle-active', $b->benutzer_id) }}"
                                              class="inline"
                                              x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true"
                                              onsubmit="return confirm(@js(__('Status wirklich ändern?')));">
                                            @csrf
                                            <button :disabled="loading" class="np-knopf np-knopf-klein np-ziel {{ $b->aktiv ? 'np-knopf-gefahr' : 'np-knopf-schlicht' }}">
                                                {{ $b->aktiv ? __('Deaktivieren') : __('Aktivieren') }}
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="p-5 text-center text-sm text-muted">
                            @if($suche || $rolleId || $status)
                                {{ __('Keine Benutzer für diese Filtereinstellungen gefunden.') }}
                            @else
                                {{ __('Keine Benutzer gefunden.') }}
                            @endif
                        </div>
                    @endforelse
                </div>

                {{-- Tabelle --}}
                <div class="np-karte hidden @3xl:block overflow-hidden">
                    <div class="overflow-x-auto p-2">
                        <table class="np-tabelle text-sm">
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('Benutzer') }}</th>
                                    <th scope="col" class="whitespace-nowrap">{{ __('Benutzername') }}</th>
                                    <th scope="col">{{ __('Rollen') }}</th>
                                    <th scope="col" class="whitespace-nowrap">{{ __('Status') }}</th>
                                    <th scope="col" class="text-right"><span class="sr-only">{{ __('Aktionen') }}</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($benutzer as $b)
                                    @php
                                        $initials = strtoupper(mb_substr($b->vorname ?? '', 0, 1) . mb_substr($b->nachname ?? '', 0, 1));
                                    @endphp
                                    <tr class="group">
                                        <td>
                                            <div class="flex items-center gap-3 min-w-0">
                                                <div class="np-monogramm size-8 shrink-0 text-2xs" aria-hidden="true">
                                                    {{ $initials ?: '?' }}
                                                </div>
                                                <div class="min-w-0">
                                                    <div class="font-medium text-text truncate">{{ $b->nachname }} {{ $b->vorname }}</div>
                                                    <div class="text-xs text-muted truncate" title="{{ $b->email }}">{{ $b->email }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-xs">{{ $b->benutzername }}</td>
                                        <td>
                                            <span class="np-marke font-medium text-muted">{{ $b->rollen ? implode(', ', array_map('__', explode(', ', $b->rollen))) : '–' }}</span>
                                        </td>
                                        <td>
                                            @if($b->aktiv)
                                                <x-status status="gruen" :text="__('Aktiv')" />
                                            @else
                                                <x-status status="rot" :text="__('Inaktiv')" />
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            <div class="flex items-center justify-end gap-1 flex-wrap opacity-0 group-hover:opacity-100 group-focus-within:opacity-100 max-md:opacity-100">
                                                @if($b->lernender_id)
                                                    <a href="{{ route('admin.learners.show', $b->lernender_id) }}"
                                                       class="np-knopf np-knopf-schlicht np-knopf-klein np-ziel">{{ __('Verwalten') }}</a>
                                                @else
                                                <a href="{{ route('admin.users.edit', $b->benutzer_id) }}"
                                                   class="np-knopf np-knopf-schlicht np-knopf-klein np-ziel">{{ __('Bearbeiten') }}</a>
                                                <form method="POST"
                                                      action="{{ route('admin.users.toggle-active', $b->benutzer_id) }}"
                                                      class="inline"
                                                      x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true"
                                                      onsubmit="return confirm(@js(__('Status wirklich ändern?')));">
                                                    @csrf
                                                    <button :disabled="loading" class="np-knopf np-knopf-klein np-ziel {{ $b->aktiv ? 'np-knopf-gefahr' : 'np-knopf-schlicht' }}">
                                                        {{ $b->aktiv ? __('Deaktivieren') : __('Aktivieren') }}
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
                                                {{ __('Keine Benutzer für diese Filtereinstellungen gefunden.') }}
                                            @else
                                                {{ __('Keine Benutzer gefunden.') }}
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
