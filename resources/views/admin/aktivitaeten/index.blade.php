<x-app-layout>
    <x-slot name="title">{{ __('Aktivitätsprotokoll') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :titel="__('Aktivitätsprotokoll')" />
    </x-slot>

    <?php $aktiveFilter = collect([$aktion, $person, $von, $bis])->filter(fn ($w) => $w !== '')->count(); ?>

    <div class="py-6">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-4">

            <x-filterleiste :action="route('admin.activity.index')" suche-name="person" :suche-wert="$person"
                             :suche-platzhalter="__('Name oder E-Mail der handelnden Person')" :zaehler="$eintraege->total()"
                             zaehler-label="{{ __('Einträge') }}" :zurueck="route('admin.activity.index')" :aktive-filter="$aktiveFilter">
                <label for="aktion" class="sr-only">{{ __('Aktion') }}</label>
                <select name="aktion" id="aktion" x-on:change="$el.form.requestSubmit()"
                        class="h-9 rounded-lg border border-border-strong/60 bg-input px-2.5 text-sm text-text focus:border-accent focus:ring-2 focus:ring-ring/30 sm:w-56">
                    <option value="" @selected($aktion === '')>{{ __('Aktion: alle') }}</option>
                    @foreach($aktionen as $wert => $label)
                        <option value="{{ $wert }}" @selected($aktion === $wert)>{{ __($label) }}</option>
                    @endforeach
                </select>

                <x-slot:weitere>
                    <label for="von" class="sr-only">{{ __('Von') }}</label>
                    <input type="date" name="von" id="von" value="{{ $von }}" x-on:change="$el.form.requestSubmit()"
                           class="h-9 rounded-lg border border-border-strong/60 bg-input px-2.5 text-sm text-text focus:border-accent focus:ring-2 focus:ring-ring/30">
                    <label for="bis" class="sr-only">{{ __('Bis') }}</label>
                    <input type="date" name="bis" id="bis" value="{{ $bis }}" x-on:change="$el.form.requestSubmit()"
                           class="h-9 rounded-lg border border-border-strong/60 bg-input px-2.5 text-sm text-text focus:border-accent focus:ring-2 focus:ring-ring/30">
                </x-slot:weitere>
            </x-filterleiste>

            {{-- Kartenansicht mobil --}}
            <div class="md:hidden divide-y divide-border rounded-xl border border-border bg-card overflow-hidden">
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
                            <div class="text-xs text-muted mt-1 font-mono">{{ $e->ip }}</div>
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
            <div class="hidden md:block rounded-xl border border-border bg-card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm tabular-nums">
                        <thead class="sticky top-0 z-10 bg-surface-2">
                            <tr>
                                <th scope="col" class="h-9 px-3 text-left text-2xs font-medium text-muted whitespace-nowrap">{{ __('Zeit') }}</th>
                                <th scope="col" class="h-9 px-3 text-left text-2xs font-medium text-muted whitespace-nowrap">{{ __('Person') }}</th>
                                <th scope="col" class="h-9 px-3 text-left text-2xs font-medium text-muted whitespace-nowrap">{{ __('Aktion') }}</th>
                                <th scope="col" class="h-9 px-3 text-left text-2xs font-medium text-muted">{{ __('Ziel') }}</th>
                                <th scope="col" class="h-9 px-3 text-left text-2xs font-medium text-muted">{{ __('Details') }}</th>
                                <th scope="col" class="h-9 px-3 text-left text-2xs font-medium text-muted whitespace-nowrap">{{ __('IP-Adresse') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @forelse($eintraege as $e)
                                <tr class="hover:bg-surface-2/60">
                                    <td class="px-3 py-2.5 text-muted whitespace-nowrap align-top">{{ $e->erstellt_am?->timezone(config('app.timezone'))->format('d.m.Y H:i') }}</td>
                                    <td class="px-3 py-2.5 align-top whitespace-nowrap">{{ $e->benutzer ? trim($e->benutzer->vorname.' '.$e->benutzer->nachname) : __('System') }}</td>
                                    <td class="px-3 py-2.5 align-top whitespace-nowrap">{{ \App\Support\Protokoll::label($e->aktion) }}</td>
                                    <td class="px-3 py-2.5 align-top max-w-xs truncate">{{ $e->ziel_bezeichnung }}</td>
                                    <td class="px-3 py-2.5 align-top max-w-xs truncate text-muted" @if($e->details) title="{{ json_encode($e->details, JSON_UNESCAPED_UNICODE) }}" @endif>
                                        {{ $e->details ? json_encode($e->details, JSON_UNESCAPED_UNICODE) : '–' }}
                                    </td>
                                    <td class="px-3 py-2.5 align-top font-mono text-xs whitespace-nowrap">{{ $e->ip }}</td>
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

            @if($eintraege->hasPages())
                <div class="rounded-xl border border-border bg-card px-4 py-3">
                    {{ $eintraege->links() }}
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
