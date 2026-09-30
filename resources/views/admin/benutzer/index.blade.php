{{-- Benutzerkonten: alle Konten mit Suche und Filtern, die ganze Zeile öffnet das Konto (Lernende: ihr Profil).
     Aktivieren und Deaktivieren liegen im Konto selbst, nicht in der Liste. --}}
@php
    $aktiveFilter = collect([$suche, $rolleId, $status])->filter()->count();
    $auswahl = 'np-feld np-feld-klein w-auto max-w-64';
@endphp
<x-app-layout>
    <x-slot name="title">{{ __('Benutzerkonten') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :titel="__('Benutzerkonten')" :zaehler="$benutzer->count()">
            <x-slot:aktionen>
                <a href="{{ route('admin.users.create') }}" class="np-knopf np-knopf-primaer">
                    <x-symbol name="plus" strich="2" />{{ __('Benutzer erfassen') }}
                </a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-4 sm:px-6 lg:px-8 flex flex-col gap-4">

            <x-filterleiste :action="route('admin.users.index')" suche-name="suche" :suche-wert="$suche"
                             :suche-platzhalter="__('Name, E-Mail oder Benutzername')"
                             :zurueck="route('admin.users.index')" :aktive-filter="$aktiveFilter">
                <label for="rolle_id" class="sr-only">{{ __('Rolle') }}</label>
                <select name="rolle_id" id="rolle_id" x-on:change="$el.form.requestSubmit()" class="{{ $auswahl }}">
                    <option value="">{{ __('Alle Rollen') }}</option>
                    @foreach($rollen as $r)
                        <option value="{{ $r->rolle_id }}" @selected($rolleId == $r->rolle_id)>{{ __($r->name) }}</option>
                    @endforeach
                </select>

                <label for="status" class="sr-only">{{ __('Status') }}</label>
                <select name="status" id="status" x-on:change="$el.form.requestSubmit()" class="{{ $auswahl }}">
                    <option value="" @selected($status === '')>{{ __('Status: alle') }}</option>
                    <option value="aktiv" @selected($status === 'aktiv')>{{ __('Aktiv') }}</option>
                    <option value="inaktiv" @selected($status === 'inaktiv')>{{ __('Inaktiv') }}</option>
                </select>
            </x-filterleiste>

            <div class="np-karte overflow-hidden">
                <div class="@container p-2">
                    <table class="np-tabelle text-sm">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('Name') }}</th>
                                <th scope="col" class="whitespace-nowrap">{{ __('Benutzername') }}</th>
                                <th scope="col">{{ __('Rollen') }}</th>
                                <th scope="col" class="w-32">{{ __('Status') }}</th>
                                <th scope="col" class="w-28 text-right"><span class="sr-only">{{ __('Aktionen') }}</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($benutzer as $b)
                                @php
                                    $zielUrl = $b->lernender_id ? route('admin.learners.show', $b->lernender_id) : route('admin.users.edit', $b->benutzer_id);
                                    $name = trim($b->nachname.' '.$b->vorname);
                                @endphp
                                <tr class="cursor-pointer" onclick="window.location='{{ $zielUrl }}'">
                                    <td>
                                        <div class="flex min-w-0 items-center gap-3">
                                            <span class="np-monogramm size-8 shrink-0 text-2xs" aria-hidden="true">{{ mb_strtoupper(mb_substr($b->vorname ?? '', 0, 1).mb_substr($b->nachname ?? '', 0, 1)) ?: '?' }}</span>
                                            <div class="min-w-0">
                                                <a href="{{ $zielUrl }}" class="font-medium text-text hover:text-accent-text">{{ $name }}</a>
                                                <div class="truncate text-xs text-muted">{{ $b->email }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap text-muted">{{ $b->benutzername }}</td>
                                    <td>
                                        <div class="flex flex-wrap gap-1">
                                            @forelse($b->rollen ? explode(', ', $b->rollen) : [] as $rolle)
                                                <span class="np-marke font-medium text-muted">{{ __($rolle) }}</span>
                                            @empty
                                                <span class="text-muted">–</span>
                                            @endforelse
                                        </div>
                                    </td>
                                    <td>
                                        @if($b->aktiv)
                                            <x-status status="gruen" :text="__('Aktiv')" />
                                        @else
                                            <x-status status="neutral" :text="__('Inaktiv')" />
                                        @endif
                                    </td>
                                    <td class="text-right" onclick="event.stopPropagation()">
                                        <x-zeilen-link :href="$zielUrl" :label="$b->lernender_id ? __('Profil') : __('Bearbeiten')" :zeile="$name" />
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-3 py-6 text-center text-muted">
                                        {{ $aktiveFilter > 0 ? __('Keine Benutzer passen zu den Filtern.') : __('Noch keine Benutzer erfasst.') }}
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
