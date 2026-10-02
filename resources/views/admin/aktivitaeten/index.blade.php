{{-- Aktivitätsprotokoll: sicherheitsrelevante Aktionen, neueste zuerst, filterbar nach Aktion, Person und Zeitraum. --}}
@php
    $aktiveFilter = collect([$aktion, $person, $von, $bis])->filter(fn ($w) => $w !== '')->count();
    $leer = $eintraege->isEmpty() && $aktiveFilter === 0 && $eintraege->currentPage() === 1;
@endphp
<x-app-layout>
    <x-slot name="title">{{ __('Aktivitätsprotokoll') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :titel="__('Aktivitätsprotokoll')" :zaehler="$leer ? null : $eintraege->total()" />
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-8 flex flex-col gap-4">
            @if($leer)
                <div class="np-karte">
                    <x-leer symbol="clock" :titel="__('Noch keine Einträge im Aktivitätsprotokoll')" />
                </div>
            @else
                <x-filterleiste :action="route('admin.activity.index')" suche-name="person" :suche-wert="$person"
                                 :suche-platzhalter="__('Name oder E-Mail')"
                                 :zurueck="route('admin.activity.index')" :aktive-filter="$aktiveFilter">
                    <label for="aktion" class="sr-only">{{ __('Aktion') }}</label>
                    <select name="aktion" id="aktion" x-on:change="$el.form.requestSubmit()" class="np-feld np-feld-klein w-auto max-w-64">
                        <option value="" @selected($aktion === '')>{{ __('Aktion: alle') }}</option>
                        @foreach($aktionen as $wert => $label)
                            <option value="{{ $wert }}" @selected($aktion === $wert)>{{ __($label) }}</option>
                        @endforeach
                    </select>

                    <x-slot:weitere>
                        <label for="von" class="text-xs text-muted">{{ __('Von') }}</label>
                        <input type="date" name="von" id="von" value="{{ $von }}" x-on:change="$el.form.requestSubmit()" class="np-feld np-feld-klein w-auto">
                        <label for="bis" class="text-xs text-muted">{{ __('Bis') }}</label>
                        <input type="date" name="bis" id="bis" value="{{ $bis }}" x-on:change="$el.form.requestSubmit()" class="np-feld np-feld-klein w-auto">
                    </x-slot:weitere>
                </x-filterleiste>

                @php
                    // Spalten ohne einen einzigen Wert entfallen (notenportal-ui §5) – bei lauter Anmeldungen wären Ziel und Details nur Striche
                    $zeilen = collect($eintraege->items())->map(fn ($e) => [
                        'wer' => $e->benutzer ? trim($e->benutzer->vorname.' '.$e->benutzer->nachname) : __('System'),
                        'details' => \App\Support\Protokoll::detailText($e->details),
                    ]);
                    $hatZiel = collect($eintraege->items())->contains(fn ($e, $i) => $e->ziel_bezeichnung && $e->ziel_bezeichnung !== $zeilen[$i]['wer']);
                    $hatDetails = $zeilen->contains(fn ($z) => $z['details'] !== '');
                @endphp
                <div @class(['np-karte overflow-hidden', 'max-w-4xl' => ! $hatZiel && ! $hatDetails])>
                    <div class="p-2">
                        <table class="np-tabelle table-fixed text-sm">
                            <thead>
                                <tr>
                                    <th scope="col" class="w-40">{{ __('Zeit') }}</th>
                                    <th scope="col" class="w-52">{{ __('Person') }}</th>
                                    <th scope="col" @class(['w-72' => $hatDetails])>{{ __('Aktion') }}</th>
                                    @if($hatZiel)<th scope="col" class="w-64">{{ __('Ziel') }}</th>@endif
                                    @if($hatDetails)<th scope="col">{{ __('Details') }}</th>@endif
                                    <th scope="col" class="w-36">{{ __('IP-Adresse') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($eintraege as $e)
                                    @php
                                        $zeitpunkt = $e->erstellt_am?->timezone(config('app.timezone'));
                                        ['wer' => $wer, 'details' => $details] = $zeilen[$loop->index];
                                        // Bei der eigenen Anmeldung wäre das Ziel nur die Person noch einmal
                                        $ziel = $e->ziel_bezeichnung !== $wer ? $e->ziel_bezeichnung : null;
                                    @endphp
                                    <tr>
                                        <td class="whitespace-nowrap tabular-nums text-muted">
                                            {{ $zeitpunkt?->format('d.m.Y') }} <span class="text-faint" aria-hidden="true">·</span> {{ $zeitpunkt?->format('H:i') }}
                                        </td>
                                        <td class="truncate">{{ $wer }}</td>
                                        <td class="truncate">{{ \App\Support\Protokoll::label($e->aktion) }}</td>
                                        @if($hatZiel)<td class="truncate" @if($ziel) title="{{ $ziel }}" @endif>@if($ziel){{ $ziel }}@else<span class="text-muted">–</span>@endif</td>@endif
                                        @if($hatDetails)<td class="text-muted">
                                            <div class="line-clamp-2 break-words" @if($details !== '') title="{{ $details }}" @endif>@if($details !== ''){{ $details }}@else<span class="text-muted">–</span>@endif</div>
                                        </td>@endif
                                        <td class="truncate tabular-nums text-muted">@if($e->ip){{ $e->ip }}@else<span class="text-muted">–</span>@endif</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ 4 + (int) $hatZiel + (int) $hatDetails }}" class="px-3 py-6 text-center text-muted">{{ __('Keine Einträge passen zu den Filtern.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                @if($eintraege->hasPages())
                    <div class="px-1">{{ $eintraege->links() }}</div>
                @endif
            @endif
        </div>
    </div>
</x-app-layout>
