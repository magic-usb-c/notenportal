@use('App\Http\Controllers\Admin\StammdatenNotenbaeumeController')
{{-- Notenbaum bearbeiten: Name und Beschreibung oben, darunter der Aufbau als Tabelle mit Einzug je Ebene.
     «Speichern» steht in der Symbolleiste und bleibt beim Scrollen durch lange Bäume erreichbar. Fehler kommen
     gesammelt über der Tabelle und markieren zusätzlich ihr Feld. --}}
@php
    $einzug = ['pl-3', 'pl-8', 'pl-13', 'pl-18', 'pl-23'];
    $zelle = 'np-feld np-feld-klein px-2 tabular-nums';
    $zahl = fn ($v) => $v === null ? '' : (string) (float) $v;
    $prozent = fn (float $anteil) => rtrim(rtrim(number_format($anteil * 100, 1, '.', ''), '0'), '.').' %';
    $fuer = $baum->bezug === 'lehrberuf' ? $baum->lehrberuf : __('Bildungsgang :track', ['track' => $baum->track_typ]);
    $knotenFehler = collect($errors->getMessages())->filter(fn ($m, $schluessel) => str_starts_with($schluessel, 'knoten.'));
    $fehlerId = fn (string $schluessel) => 'k'.str_replace('.', '-', substr($schluessel, strlen('knoten.'))).'-fehler';
    $fehlerAttr = fn (int $id, string $feld) => $errors->has("knoten.$id.$feld")
        ? 'aria-invalid="true" aria-describedby="'.$fehlerId("knoten.$id.$feld").'"' : '';
@endphp
<x-app-layout>
    <x-slot name="title">{{ $baum->name }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :zurueck="route('admin.master-data.grade-trees.index')" :titel="$baum->name" :untertitel="$fuer">
            @unless($baum->aktiv)
                <span class="np-marke text-muted">{{ __('Inaktiv') }}</span>
            @endunless
            <x-slot:aktionen>
                <form method="POST" action="{{ route('admin.master-data.grade-trees.activate', $baum->baum_id) }}"
                      @if($baum->aktiv) data-bestaetigen="{{ __('Notenbaum «:name» deaktivieren?', ['name' => $baum->name]) }}" data-bestaetigen-knopf="{{ __('Deaktivieren') }}" @endif
                      x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                    @csrf
                    <input type="hidden" name="aktiv" value="{{ $baum->aktiv ? 0 : 1 }}">
                    <button type="submit" :disabled="loading" class="np-knopf np-knopf-sekundaer">
                        {{ $baum->aktiv ? __('Deaktivieren') : __('Aktivieren') }}
                    </button>
                </form>
                <a href="{{ route('admin.master-data.grade-trees.export', $baum->baum_id) }}" class="np-knopf np-knopf-sekundaer">{{ __('Exportieren') }}</a>
                <button type="submit" form="notenbaum" class="np-knopf np-knopf-primaer"
                        x-data="{ loading: false }" :disabled="loading"
                        @submit.window="if ($event.target.id === 'notenbaum' && ! $event.defaultPrevented) loading = true"
                        @pageshow.window="loading = false">
                    {{ __('Speichern') }}
                </button>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite flex flex-col gap-5 px-8">
            <form id="notenbaum" method="POST" action="{{ route('admin.master-data.grade-trees.update', $baum->baum_id) }}" class="flex flex-col gap-5">
                @csrf
                @method('PUT')

                <div class="np-karte grid grid-cols-3 gap-x-5 gap-y-4 p-5">
                    <div>
                        <label for="name" class="text-sm font-medium text-text">{{ __('Name') }}</label>
                        <input id="name" name="name" required maxlength="150" value="{{ old('name', $baum->name) }}" class="np-feld mt-1.5"
                               @error('name') aria-invalid="true" aria-describedby="name-fehler" @enderror>
                        @error('name')<p id="name-fehler" class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                    </div>
                    <div class="col-span-2">
                        <label for="beschreibung" class="text-sm font-medium text-text">{{ __('Beschreibung') }}</label>
                        <textarea id="beschreibung" name="beschreibung" rows="2" maxlength="500" class="np-feld mt-1.5"
                                  @error('beschreibung') aria-invalid="true" aria-describedby="beschreibung-fehler" @enderror>{{ old('beschreibung', $baum->beschreibung) }}</textarea>
                        @error('beschreibung')<p id="beschreibung-fehler" class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                    </div>
                </div>

                <x-karte :titel="__('Aufbau')" :polster="false">
                    @if($knotenFehler->isNotEmpty())
                        <ul class="mx-5 mb-2 flex flex-col gap-1 text-sm text-note-ungenuegend" role="alert">
                            @foreach($knotenFehler as $schluessel => $meldungen)
                                <li id="{{ $fehlerId($schluessel) }}">{{ $meldungen[0] }}</li>
                            @endforeach
                        </ul>
                    @endif
                    <div class="overflow-x-auto px-2 pb-2">
                        <table class="np-tabelle text-sm">
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('Teil') }}</th>
                                    <th scope="col">{{ __('Rechnet aus') }}</th>
                                    <th scope="col" class="text-right">{{ __('Gewicht') }}</th>
                                    <th scope="col" class="text-right">{{ __('Anteil') }}</th>
                                    <th scope="col">{{ __('Rundung') }}</th>
                                    <th scope="col" class="text-right">{{ __('Mindestnote') }}</th>
                                    <th scope="col" class="text-right">{{ __('Max. ungenügend') }}</th>
                                    <th scope="col" class="text-right">{{ __('Max. Minuspunkte') }}</th>
                                    <th scope="col" class="text-center">{{ __('Zählt') }}</th>
                                    <th scope="col">{{ __('Entfällt mit') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($zeilen as $z)
                                    @php
                                        $k = $z['k'];
                                        $id = (int) $k->knoten_id;
                                        $alt = fn (string $f, $v) => old("knoten.$id.$f", $v);
                                        $quelle = match ($k->typ) {
                                            'kategorie' => $k->kategorie_name.($k->elementtyp === 'modul' ? ' · '.__('nur Module') : ($k->elementtyp === 'fach' ? ' · '.__('nur Fächer') : '')),
                                            'faecher' => implode(', ', $faecher[$id] ?? []),
                                            default => StammdatenNotenbaeumeController::typName($k->typ),
                                        };
                                        $rundung = (string) $alt('rundung', $k->rundung === null ? '' : $zahl($k->rundung));
                                        $track = $alt('entfaellt_mit_track', $k->entfaellt_mit_track);
                                        $gruppe = $k->typ === 'gruppe';
                                    @endphp
                                    <tr>
                                        <td class="{{ $einzug[min($z['tiefe'], 4)] }}">
                                            <label for="k{{ $id }}-name" class="sr-only">{{ __('Name') }}</label>
                                            <input id="k{{ $id }}-name" name="knoten[{{ $id }}][name]" value="{{ $alt('name', $k->name) }}" required maxlength="150"
                                                   class="{{ $zelle }} w-full min-w-64 {{ $gruppe ? 'font-medium' : '' }}" {!! $fehlerAttr($id, 'name') !!}>
                                        </td>
                                        <td class="max-w-80 text-xs text-muted">{{ $quelle }}</td>
                                        <td class="text-right">
                                            @if($z['tiefe'] > 0)
                                                <label for="k{{ $id }}-gewicht" class="sr-only">{{ __('Gewicht') }}</label>
                                                <input id="k{{ $id }}-gewicht" name="knoten[{{ $id }}][gewicht]" inputmode="decimal" value="{{ $alt('gewicht', $zahl($k->gewicht)) }}"
                                                       class="{{ $zelle }} ml-auto w-18 text-right" {!! $fehlerAttr($id, 'gewicht') !!}>
                                            @else
                                                <input type="hidden" name="knoten[{{ $id }}][gewicht]" value="{{ $zahl($k->gewicht) }}">
                                            @endif
                                        </td>
                                        <td class="text-right tabular-nums text-muted">{{ $z['anteil'] !== null ? $prozent($z['anteil']) : '' }}</td>
                                        <td>
                                            <label for="k{{ $id }}-rundung" class="sr-only">{{ __('Rundung') }}</label>
                                            <select id="k{{ $id }}-rundung" name="knoten[{{ $id }}][rundung]" class="{{ $zelle }} w-24 py-0 pr-8" {!! $fehlerAttr($id, 'rundung') !!}>
                                                <option value="" @selected($rundung === '')>{{ __('keine') }}</option>
                                                @foreach(['0.1', '0.5', '1'] as $schritt)
                                                    <option value="{{ $schritt }}" @selected($rundung === $schritt)>{{ $schritt }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <label for="k{{ $id }}-fallnote" class="sr-only">{{ __('Mindestnote') }}</label>
                                            <input id="k{{ $id }}-fallnote" name="knoten[{{ $id }}][fallnote]" inputmode="decimal" value="{{ $alt('fallnote', $zahl($k->fallnote)) }}"
                                                   class="{{ $zelle }} ml-auto w-18 text-right" placeholder="–" {!! $fehlerAttr($id, 'fallnote') !!}>
                                        </td>
                                        <td>
                                            @if($gruppe)
                                                <label for="k{{ $id }}-max_ungenuegend" class="sr-only">{{ __('Max. ungenügend') }}</label>
                                                <input id="k{{ $id }}-max_ungenuegend" name="knoten[{{ $id }}][max_ungenuegend]" inputmode="numeric" value="{{ $alt('max_ungenuegend', $k->max_ungenuegend) }}"
                                                       class="{{ $zelle }} ml-auto w-18 text-right" placeholder="–" {!! $fehlerAttr($id, 'max_ungenuegend') !!}>
                                            @endif
                                        </td>
                                        <td>
                                            @if($gruppe)
                                                <label for="k{{ $id }}-max_minuspunkte" class="sr-only">{{ __('Max. Minuspunkte') }}</label>
                                                <input id="k{{ $id }}-max_minuspunkte" name="knoten[{{ $id }}][max_minuspunkte]" inputmode="decimal" value="{{ $alt('max_minuspunkte', $zahl($k->max_minuspunkte)) }}"
                                                       class="{{ $zelle }} ml-auto w-18 text-right" placeholder="–" {!! $fehlerAttr($id, 'max_minuspunkte') !!}>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if($z['tiefe'] > 0)
                                                <input type="hidden" name="knoten[{{ $id }}][zaehlt]" value="0">
                                                <input id="k{{ $id }}-zaehlt" type="checkbox" name="knoten[{{ $id }}][zaehlt]" value="1" @checked((bool) $alt('zaehlt', $k->zaehlt))
                                                       class="np-haken" aria-label="{{ __(':teil zählt', ['teil' => $k->name]) }}">
                                            @else
                                                <input type="hidden" name="knoten[{{ $id }}][zaehlt]" value="1">
                                            @endif
                                        </td>
                                        <td>
                                            @if($z['tiefe'] > 0)
                                                <label for="k{{ $id }}-track" class="sr-only">{{ __('Entfällt mit') }}</label>
                                                <select id="k{{ $id }}-track" name="knoten[{{ $id }}][entfaellt_mit_track]" class="{{ $zelle }} w-24 py-0 pr-8" {!! $fehlerAttr($id, 'entfaellt_mit_track') !!}>
                                                    <option value="" @selected(! $track)>–</option>
                                                    <option value="BMS" @selected($track === 'BMS')>BMS</option>
                                                    <option value="ABU" @selected($track === 'ABU')>ABU</option>
                                                </select>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-karte>
            </form>

            <section class="flex items-center gap-4 pt-3">
                @if($positionen > 0)
                    <p class="text-sm text-muted">{{ __('Zu diesem Notenbaum sind :anzahl Noten erfasst. Löschen geht erst ohne erfasste Noten.', ['anzahl' => $positionen]) }}</p>
                @else
                    <form method="POST" action="{{ route('admin.master-data.grade-trees.destroy', $baum->baum_id) }}"
                          data-bestaetigen="{{ __('Notenbaum «:name» endgültig löschen?', ['name' => $baum->name]) }}" data-bestaetigen-knopf="{{ __('Löschen') }}"
                          x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                        @csrf
                        @method('DELETE')
                        <button type="submit" :disabled="loading" class="np-knopf np-knopf-gefahr">{{ __('Notenbaum löschen') }}</button>
                    </form>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
