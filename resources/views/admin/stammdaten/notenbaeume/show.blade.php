@use('App\Http\Controllers\Admin\StammdatenNotenbaeumeController')
@php
    // Ab 64rem Container eine Tabelle, darunter je Teil eine Formularkarte mit sichtbaren Feldnamen
    $einzug = ['@5xl:pl-4', '@5xl:pl-8', '@5xl:pl-12', '@5xl:pl-16', '@5xl:pl-20'];
    $einzugKarte = ['', '@max-5xl:ml-4', '@max-5xl:ml-8', '@max-5xl:ml-12', '@max-5xl:ml-16'];
    $feldname = 'sr-only @max-5xl:not-sr-only @max-5xl:mb-1 @max-5xl:block @max-5xl:text-2xs @max-5xl:font-medium @max-5xl:text-muted';
    $karteZelle = '@max-5xl:p-0 @max-5xl:text-left';
    $zelle = 'np-feld np-feld-klein px-2 tabular-nums';
    $zahl = fn ($v) => $v === null ? '' : (string) (float) $v;
    $prozent = fn (float $anteil) => rtrim(rtrim(number_format($anteil * 100, 1, '.', ''), '0'), '.').' %';
    $fuer = $baum->bezug === 'lehrberuf' ? $baum->lehrberuf : __('Bildungsgang :track', ['track' => $baum->track_typ]);
@endphp
<x-app-layout>
    <x-slot name="title">{{ $baum->name }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :zurueck="route('admin.master-data.grade-trees.index')" :titel="$baum->name" :untertitel="$fuer">
            @if($baum->aktiv)
                <span class="rounded-md bg-accent/12 px-2 py-0.5 text-xs font-medium text-accent-text">{{ __('aktiv') }}</span>
            @else
                <span class="rounded-md border border-border bg-surface-2 px-2 py-0.5 text-xs text-muted">{{ __('inaktiv') }}</span>
            @endif
            <x-slot:aktionen>
                <form method="POST" action="{{ route('admin.master-data.grade-trees.activate', $baum->baum_id) }}">
                    @csrf
                    <input type="hidden" name="aktiv" value="{{ $baum->aktiv ? 0 : 1 }}">
                    <button type="submit" class="np-knopf np-knopf-sekundaer">
                        {{ $baum->aktiv ? __('Deaktivieren') : __('Aktivieren') }}
                    </button>
                </form>
                <a href="{{ route('admin.master-data.grade-trees.export', $baum->baum_id) }}"
                   class="np-knopf np-knopf-sekundaer">{{ __('Exportieren') }}</a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-4 sm:px-6 lg:px-8 flex flex-col gap-8">
            <form method="POST" action="{{ route('admin.master-data.grade-trees.update', $baum->baum_id) }}" class="flex flex-col gap-5"
                  x-data="{ loading: false }" @submit="loading = true">
                @csrf
                @method('PUT')

                <div class="grid max-w-3xl grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="name" class="text-sm font-medium text-text">{{ __('Name') }} <span class="text-note-ungenuegend">*</span></label>
                        <input id="name" name="name" required maxlength="150" value="{{ old('name', $baum->name) }}"
                               class="np-feld mt-1.5"
                               @error('name') aria-invalid="true" aria-describedby="name-fehler" @enderror>
                        @error('name')<p id="name-fehler" class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label for="beschreibung" class="text-sm font-medium text-text">{{ __('Beschreibung') }}</label>
                        <textarea id="beschreibung" name="beschreibung" rows="3" maxlength="500"
                                  class="np-feld mt-1.5">{{ old('beschreibung', $baum->beschreibung) }}</textarea>
                    </div>
                </div>

                @if($errors->has('knoten.*'))
                    <p class="text-sm text-note-ungenuegend" role="alert">{{ $errors->first('knoten.*') }}</p>
                @endif

                <div class="np-karte @container overflow-x-auto">
                    <table class="w-full text-sm tabular-nums @max-5xl:block">
                        <thead class="bg-surface-2 @max-5xl:hidden">
                            <tr>
                                <th scope="col" class="h-9 px-4 text-left text-2xs font-medium text-muted">{{ __('Teil') }}</th>
                                <th scope="col" class="h-9 px-2 text-left text-2xs font-medium text-muted">{{ __('Rechnet aus') }}</th>
                                <th scope="col" class="h-9 px-2 text-right text-2xs font-medium text-muted">{{ __('Gewicht') }}</th>
                                <th scope="col" class="h-9 px-2 text-right text-2xs font-medium text-muted">{{ __('Anteil') }}</th>
                                <th scope="col" class="h-9 px-2 text-left text-2xs font-medium text-muted">{{ __('Rundung') }}</th>
                                <th scope="col" class="h-9 px-2 text-left text-2xs font-medium text-muted">{{ __('Mindestnote') }}</th>
                                <th scope="col" class="h-9 px-2 text-left text-2xs font-medium text-muted">{{ __('Max. ungenügend') }}</th>
                                <th scope="col" class="h-9 px-2 text-left text-2xs font-medium text-muted">{{ __('Max. Minuspunkte') }}</th>
                                <th scope="col" class="h-9 px-2 text-center text-2xs font-medium text-muted">{{ __('Zählt') }}</th>
                                <th scope="col" class="h-9 px-4 text-left text-2xs font-medium text-muted">{{ __('Entfällt mit') }}</th>
                            </tr>
                        </thead>
                        <tbody class="@max-5xl:block">
                            @foreach($zeilen as $z)
                                @php
                                    $k = $z['k'];
                                    $id = $k->knoten_id;
                                    $alt = fn (string $f, $v) => old("knoten.$id.$f", $v);
                                    $quelle = match ($k->typ) {
                                        'kategorie' => $k->kategorie_name.($k->elementtyp === 'modul' ? ' · '.__('nur Module') : ($k->elementtyp === 'fach' ? ' · '.__('nur Fächer') : '')),
                                        'faecher' => implode(', ', $faecher[$id] ?? []),
                                        default => StammdatenNotenbaeumeController::typName($k->typ),
                                    };
                                    $rundung = $alt('rundung', $k->rundung === null ? '' : $zahl($k->rundung));
                                @endphp
                                <tr class="border-b border-border last:border-0 @max-5xl:grid @max-5xl:grid-cols-2 @max-5xl:gap-x-3 @max-5xl:gap-y-3 @max-5xl:py-4 @max-5xl:pr-4 @max-5xl:pl-4 @md:@max-5xl:grid-cols-4 {{ $einzugKarte[min($z['tiefe'], 4)] }}">
                                    <td class="py-2 pr-2 {{ $einzug[min($z['tiefe'], 4)] }} @max-5xl:col-span-full {{ $karteZelle }}">
                                        <label for="k{{ $id }}-name" class="sr-only">{{ __('Name') }}</label>
                                        <input id="k{{ $id }}-name" name="knoten[{{ $id }}][name]" value="{{ $alt('name', $k->name) }}" required maxlength="150"
                                               class="{{ $zelle }} w-full min-w-40 {{ $k->typ === 'gruppe' ? 'font-medium' : '' }}">
                                    </td>
                                    <td class="max-w-56 px-2 text-xs text-muted @max-5xl:col-span-full @max-5xl:-mt-2 @max-5xl:max-w-none {{ $karteZelle }}">{{ $quelle }}</td>
                                    <td @class(['px-2 text-right', $karteZelle, '@max-5xl:hidden' => $z['tiefe'] === 0])>
                                        @if($z['tiefe'] > 0)
                                            <label for="k{{ $id }}-gewicht" class="{{ $feldname }}">{{ __('Gewicht') }}</label>
                                            <input id="k{{ $id }}-gewicht" name="knoten[{{ $id }}][gewicht]" inputmode="decimal" value="{{ $alt('gewicht', $zahl($k->gewicht)) }}"
                                                   class="{{ $zelle }} w-16 text-right @max-5xl:w-full">
                                        @else
                                            <input type="hidden" name="knoten[{{ $id }}][gewicht]" value="{{ $zahl($k->gewicht) }}">
                                        @endif
                                    </td>
                                    <td @class(['px-2 text-right text-muted', $karteZelle, '@max-5xl:hidden' => $z['anteil'] === null])>
                                        <span class="hidden @max-5xl:mb-1 @max-5xl:block @max-5xl:text-2xs @max-5xl:font-medium">{{ __('Anteil') }}</span>
                                        <span class="@max-5xl:inline-flex @max-5xl:h-8 @max-5xl:items-center">{{ $z['anteil'] !== null ? $prozent($z['anteil']) : '' }}</span>
                                    </td>
                                    <td class="px-2 {{ $karteZelle }}">
                                        <label for="k{{ $id }}-rundung" class="{{ $feldname }}">{{ __('Rundung') }}</label>
                                        <select id="k{{ $id }}-rundung" name="knoten[{{ $id }}][rundung]" class="{{ $zelle }} w-24 py-0 pr-8 @max-5xl:w-full">
                                            <option value="" @selected($rundung === '')>{{ __('keine') }}</option>
                                            <option value="0.1" @selected($rundung === '0.1')>0.1</option>
                                            <option value="0.5" @selected($rundung === '0.5')>0.5</option>
                                            <option value="1" @selected($rundung === '1')>1</option>
                                        </select>
                                    </td>
                                    <td class="px-2 {{ $karteZelle }}">
                                        <label for="k{{ $id }}-fallnote" class="{{ $feldname }}">{{ __('Mindestnote') }}</label>
                                        <input id="k{{ $id }}-fallnote" name="knoten[{{ $id }}][fallnote]" inputmode="decimal" value="{{ $alt('fallnote', $zahl($k->fallnote)) }}"
                                               class="{{ $zelle }} w-16 text-right @max-5xl:w-full" placeholder="–">
                                    </td>
                                    <td @class(['px-2', $karteZelle, '@max-5xl:hidden' => $k->typ !== 'gruppe'])>
                                        @if($k->typ === 'gruppe')
                                            <label for="k{{ $id }}-mu" class="{{ $feldname }}">{{ __('Max. ungenügend') }}</label>
                                            <input id="k{{ $id }}-mu" name="knoten[{{ $id }}][max_ungenuegend]" inputmode="numeric" value="{{ $alt('max_ungenuegend', $k->max_ungenuegend) }}"
                                                   class="{{ $zelle }} w-16 text-right @max-5xl:w-full" placeholder="–">
                                        @endif
                                    </td>
                                    <td @class(['px-2', $karteZelle, '@max-5xl:hidden' => $k->typ !== 'gruppe'])>
                                        @if($k->typ === 'gruppe')
                                            <label for="k{{ $id }}-mp" class="{{ $feldname }}">{{ __('Max. Minuspunkte') }}</label>
                                            <input id="k{{ $id }}-mp" name="knoten[{{ $id }}][max_minuspunkte]" inputmode="decimal" value="{{ $alt('max_minuspunkte', $zahl($k->max_minuspunkte)) }}"
                                                   class="{{ $zelle }} w-16 text-right @max-5xl:w-full" placeholder="–">
                                        @endif
                                    </td>
                                    <td @class(['px-2 text-center', $karteZelle, '@max-5xl:hidden' => $z['tiefe'] === 0])>
                                        @if($z['tiefe'] > 0)
                                            <input type="hidden" name="knoten[{{ $id }}][zaehlt]" value="0">
                                            <label for="k{{ $id }}-zaehlt" class="{{ $feldname }}">{{ __('Zählt') }}</label>
                                            <span class="@max-5xl:inline-flex @max-5xl:h-8 @max-5xl:items-center">
                                                <input id="k{{ $id }}-zaehlt" type="checkbox" name="knoten[{{ $id }}][zaehlt]" value="1" @checked((bool) $alt('zaehlt', $k->zaehlt))
                                                       class="size-4 rounded border-border-strong/70 text-accent focus:ring-ring/30">
                                            </span>
                                        @else
                                            <input type="hidden" name="knoten[{{ $id }}][zaehlt]" value="1">
                                        @endif
                                    </td>
                                    <td @class(['px-4', $karteZelle, '@max-5xl:hidden' => $z['tiefe'] === 0])>
                                        @if($z['tiefe'] > 0)
                                            @php($track = $alt('entfaellt_mit_track', $k->entfaellt_mit_track))
                                            <label for="k{{ $id }}-track" class="{{ $feldname }}">{{ __('Entfällt mit') }}</label>
                                            <select id="k{{ $id }}-track" name="knoten[{{ $id }}][entfaellt_mit_track]" class="{{ $zelle }} w-24 py-0 pr-8 @max-5xl:w-full">
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

                <div class="flex flex-wrap items-center justify-end gap-3">
                    <button type="submit" :disabled="loading"
                            class="np-knopf np-knopf-primaer">
                        {{ __('Speichern') }}
                    </button>
                </div>
            </form>

            <section class="flex max-w-3xl flex-wrap items-center justify-between gap-3 border-t border-border pt-6">
                @if($positionen > 0)
                    <p class="text-sm text-muted">{{ __('Zu diesem Notenbaum sind :anzahl Noten erfasst. Löschen geht erst ohne erfasste Noten.', ['anzahl' => $positionen]) }}</p>
                @else
                    <form method="POST" action="{{ route('admin.master-data.grade-trees.destroy', $baum->baum_id) }}"
                          onsubmit="return confirm(@js(__('Notenbaum «:name» endgültig löschen?', ['name' => $baum->name])));">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="np-knopf np-knopf-gefahr">{{ __('Notenbaum löschen') }}</button>
                    </form>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
