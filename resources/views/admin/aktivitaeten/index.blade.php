<x-app-layout>
    <x-slot name="title">{{ __('Aktivitätsprotokoll') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :titel="__('Aktivitätsprotokoll')" />
    </x-slot>

    <?php $aktiveFilter = collect([$aktion, $person, $von, $bis])->filter(fn ($w) => $w !== '')->count(); ?>

    <div class="py-6">
        <div class="mx-auto np-seite px-4 sm:px-6 lg:px-8 space-y-4">

            <x-filterleiste :action="route('admin.activity.index')" suche-name="person" :suche-wert="$person"
                             :suche-platzhalter="__('Name oder E-Mail')" :zaehler="$eintraege->total()"
                             zaehler-label="{{ __('Einträge') }}" :zurueck="route('admin.activity.index')" :aktive-filter="$aktiveFilter">
                <label for="aktion" class="sr-only">{{ __('Aktion') }}</label>
                <select name="aktion" id="aktion" x-on:change="$el.form.requestSubmit()"
                        class="np-feld np-feld-klein sm:w-56">
                    <option value="" @selected($aktion === '')>{{ __('Aktion: alle') }}</option>
                    @foreach($aktionen as $wert => $label)
                        <option value="{{ $wert }}" @selected($aktion === $wert)>{{ __($label) }}</option>
                    @endforeach
                </select>

                <x-slot:weitere>
                    <label for="von" class="self-center text-xs text-muted">{{ __('Von') }}</label>
                    <input type="date" name="von" id="von" value="{{ $von }}" x-on:change="$el.form.requestSubmit()"
                           class="np-feld np-feld-klein w-auto">
                    <label for="bis" class="self-center text-xs text-muted">{{ __('Bis') }}</label>
                    <input type="date" name="bis" id="bis" value="{{ $bis }}" x-on:change="$el.form.requestSubmit()"
                           class="np-feld np-feld-klein w-auto">
                </x-slot:weitere>
            </x-filterleiste>

            {{-- Karten oder Tabelle je nach Breite des Inhalts, nicht des Fensters (Seitenleiste) --}}
            <div class="@container">
                {{-- Kartenansicht mobil --}}
                <div class="np-karte @4xl:hidden divide-y divide-border overflow-hidden">
                    @forelse($eintraege as $e)
                        <div class="p-4">
                            <div class="flex items-center justify-between gap-3">
                                <span class="font-medium text-text">{{ \App\Support\Protokoll::label($e->aktion) }}</span>
                                <span class="text-xs text-muted whitespace-nowrap">{{ $e->erstellt_am?->timezone(config('app.timezone'))->format('d.m.Y H:i') }}</span>
                            </div>
                            <div class="text-sm text-muted mt-1">
                                {{ $e->benutzer ? trim($e->benutzer->vorname.' '.$e->benutzer->nachname) : __('System') }}
                            </div>
                            @if($e->ziel_bezeichnung)
                                <div class="text-sm text-text mt-1">{{ $e->ziel_bezeichnung }}</div>
                            @endif
                            @if($e->details)
                                <div class="text-xs text-muted mt-1 truncate">{{ json_encode($e->details, JSON_UNESCAPED_UNICODE) }}</div>
                            @endif
                            @if($e->ip)
                                <div class="text-xs text-muted mt-1 tabular-nums">{{ $e->ip }}</div>
                            @endif
                        </div>
                    @empty
                        <div class="p-5 text-center text-sm text-muted">
                            @if($aktiveFilter > 0)
                                {{ __('Keine Einträge für diese Filtereinstellungen gefunden.') }}
                            @else
                                {{ __('Noch keine Einträge im Aktivitätsprotokoll.') }}
                            @endif
                        </div>
                    @endforelse
                </div>

                {{-- Tabelle --}}
                <div class="np-karte hidden @4xl:block overflow-hidden">
                    <div class="overflow-x-auto p-2">
                        <table class="np-tabelle text-sm">
                            <thead>
                                <tr>
                                    <th scope="col" class="whitespace-nowrap">{{ __('Zeit') }}</th>
                                    <th scope="col" class="whitespace-nowrap">{{ __('Person') }}</th>
                                    <th scope="col" class="whitespace-nowrap">{{ __('Aktion') }}</th>
                                    <th scope="col">{{ __('Ziel') }}</th>
                                    <th scope="col">{{ __('Details') }}</th>
                                    <th scope="col" class="whitespace-nowrap">{{ __('IP-Adresse') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($eintraege as $e)
                                    <tr>
                                        <td class="text-muted whitespace-nowrap">{{ $e->erstellt_am?->timezone(config('app.timezone'))->format('d.m.Y H:i') }}</td>
                                        <td class="whitespace-nowrap">{{ $e->benutzer ? trim($e->benutzer->vorname.' '.$e->benutzer->nachname) : __('System') }}</td>
                                        <td class="whitespace-nowrap">{{ \App\Support\Protokoll::label($e->aktion) }}</td>
                                        <td class="max-w-xs truncate">{{ $e->ziel_bezeichnung }}</td>
                                        <td class="max-w-xs truncate text-muted" @if($e->details) title="{{ json_encode($e->details, JSON_UNESCAPED_UNICODE) }}" @endif>
                                            {{ $e->details ? json_encode($e->details, JSON_UNESCAPED_UNICODE) : '–' }}
                                        </td>
                                        <td class="whitespace-nowrap text-muted tabular-nums">{{ $e->ip }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="p-6 text-center text-muted">
                                            @if($aktiveFilter > 0)
                                                {{ __('Keine Einträge für diese Filtereinstellungen gefunden.') }}
                                            @else
                                                {{ __('Noch keine Einträge im Aktivitätsprotokoll.') }}
                                            @endif
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            @if($eintraege->hasPages())
                <div class="px-1">{{ $eintraege->links() }}</div>
            @endif

        </div>
    </div>
</x-app-layout>
