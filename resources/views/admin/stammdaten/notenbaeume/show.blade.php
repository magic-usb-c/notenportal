@use('App\Http\Controllers\Admin\StammdatenNotenbaeumeController')
@php
    $einzug = ['pl-4', 'pl-8', 'pl-12', 'pl-16', 'pl-20'];
    $zelle = 'h-8 rounded-lg border border-border-strong/70 bg-input px-2 text-sm tabular-nums text-text focus:border-accent focus:ring-2 focus:ring-ring/30';
    $zahl = fn ($v) => $v === null ? '' : (string) (float) $v;
    $prozent = fn (float $anteil) => rtrim(rtrim(number_format($anteil * 100, 1, '.', ''), '0'), '.').' %';
    $fuer = $baum->bezug === 'lehrberuf' ? $baum->lehrberuf : __('Bildungsgang :track', ['track' => $baum->track_typ]);
@endphp
<x-app-layout>
    <x-slot name="title">{{ $baum->name }}</x-slot>
    <x-slot name="header">
        <nav class="mb-1 flex items-center gap-1 text-xs text-muted" aria-label="{{ __('Brotkrumen') }}">
            <a href="{{ route('admin.master-data.grade-trees.index') }}" class="transition-colors hover:text-text">{{ __('Notenbäume') }}</a>
            <span class="text-muted/40">›</span>
            <span class="text-text">{{ $baum->name }}</span>
        </nav>
        <x-seitenkopf :titel="$baum->name" :untertitel="$fuer">
            @if($baum->aktiv)
                <span class="rounded-md bg-note-gut/14 px-2 py-0.5 text-xs text-note-gut">{{ __('aktiv') }}</span>
            @else
                <span class="rounded-md border border-border bg-surface-2 px-2 py-0.5 text-xs text-muted">{{ __('inaktiv') }}</span>
            @endif
            <x-slot:aktionen>
                <form method="POST" action="{{ route('admin.master-data.grade-trees.activate', $baum->baum_id) }}">
                    @csrf
                    <input type="hidden" name="aktiv" value="{{ $baum->aktiv ? 0 : 1 }}">
                    <button type="submit" class="inline-flex h-9 items-center gap-2 rounded-lg glass-btn px-3.5 text-sm font-medium text-text">
                        {{ $baum->aktiv ? __('Deaktivieren') : __('Aktivieren') }}
                    </button>
                </form>
                <a href="{{ route('admin.master-data.grade-trees.export', $baum->baum_id) }}"
                   class="inline-flex h-9 items-center gap-2 rounded-lg glass-btn px-3.5 text-sm font-medium text-text">{{ __('Exportieren') }}</a>
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
                               class="mt-1.5 h-10 w-full rounded-lg border border-border-strong/70 bg-input px-3 text-sm text-text focus:border-accent focus:ring-2 focus:ring-ring/30"
                               @error('name') aria-invalid="true" aria-describedby="name-fehler" @enderror>
                        @error('name')<p id="name-fehler" class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label for="beschreibung" class="text-sm font-medium text-text">{{ __('Beschreibung') }}</label>
                        <textarea id="beschreibung" name="beschreibung" rows="2" maxlength="500"
                                  class="mt-1.5 w-full rounded-lg border border-border-strong/70 bg-input px-3 py-2 text-sm text-text focus:border-accent focus:ring-2 focus:ring-ring/30">{{ old('beschreibung', $baum->beschreibung) }}</textarea>
                    </div>
                </div>

                @if($errors->has('knoten.*'))
                    <p class="text-sm text-note-ungenuegend" role="alert">{{ $errors->first('knoten.*') }}</p>
                @endif

                <div class="overflow-x-auto rounded-xl border border-border bg-card">
                    <table class="w-full min-w-[62rem] text-sm tabular-nums">
                        <thead class="bg-surface-2">
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
                        <tbody>
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
                                <tr class="border-b border-border last:border-0">
                                    <td class="py-2 pr-2 {{ $einzug[min($z['tiefe'], 4)] }}">
                                        <label for="k{{ $id }}-name" class="sr-only">{{ __('Name') }}</label>
                                        <input id="k{{ $id }}-name" name="knoten[{{ $id }}][name]" value="{{ $alt('name', $k->name) }}" required maxlength="150"
                                               class="{{ $zelle }} w-full min-w-40 {{ $k->typ === 'gruppe' ? 'font-medium' : '' }}">
                                    </td>
                                    <td class="max-w-56 px-2 text-xs text-muted">{{ $quelle }}</td>
                                    <td class="px-2 text-right">
                                        @if($z['tiefe'] > 0)
                                            <label for="k{{ $id }}-gewicht" class="sr-only">{{ __('Gewicht') }}</label>
                                            <input id="k{{ $id }}-gewicht" name="knoten[{{ $id }}][gewicht]" inputmode="decimal" value="{{ $alt('gewicht', $zahl($k->gewicht)) }}"
                                                   class="{{ $zelle }} w-16 text-right">
                                        @else
                                            <input type="hidden" name="knoten[{{ $id }}][gewicht]" value="{{ $zahl($k->gewicht) }}">
                                        @endif
                                    </td>
                                    <td class="px-2 text-right text-muted">{{ $z['anteil'] !== null ? $prozent($z['anteil']) : '' }}</td>
                                    <td class="px-2">
                                        <label for="k{{ $id }}-rundung" class="sr-only">{{ __('Rundung') }}</label>
                                        <select id="k{{ $id }}-rundung" name="knoten[{{ $id }}][rundung]" class="{{ $zelle }} w-24">
                                            <option value="" @selected($rundung === '')>{{ __('keine') }}</option>
                                            <option value="0.1" @selected($rundung === '0.1')>0.1</option>
                                            <option value="0.5" @selected($rundung === '0.5')>0.5</option>
                                            <option value="1" @selected($rundung === '1')>1</option>
                                        </select>
                                    </td>
                                    <td class="px-2">
                                        <label for="k{{ $id }}-fallnote" class="sr-only">{{ __('Mindestnote') }}</label>
                                        <input id="k{{ $id }}-fallnote" name="knoten[{{ $id }}][fallnote]" inputmode="decimal" value="{{ $alt('fallnote', $zahl($k->fallnote)) }}"
                                               class="{{ $zelle }} w-16 text-right" placeholder="–">
                                    </td>
                                    <td class="px-2">
                                        @if($k->typ === 'gruppe')
                                            <label for="k{{ $id }}-mu" class="sr-only">{{ __('Max. ungenügend') }}</label>
                                            <input id="k{{ $id }}-mu" name="knoten[{{ $id }}][max_ungenuegend]" inputmode="numeric" value="{{ $alt('max_ungenuegend', $k->max_ungenuegend) }}"
                                                   class="{{ $zelle }} w-16 text-right" placeholder="–">
                                        @endif
                                    </td>
                                    <td class="px-2">
                                        @if($k->typ === 'gruppe')
                                            <label for="k{{ $id }}-mp" class="sr-only">{{ __('Max. Minuspunkte') }}</label>
                                            <input id="k{{ $id }}-mp" name="knoten[{{ $id }}][max_minuspunkte]" inputmode="decimal" value="{{ $alt('max_minuspunkte', $zahl($k->max_minuspunkte)) }}"
                                                   class="{{ $zelle }} w-16 text-right" placeholder="–">
                                        @endif
                                    </td>
                                    <td class="px-2 text-center">
                                        @if($z['tiefe'] > 0)
                                            <input type="hidden" name="knoten[{{ $id }}][zaehlt]" value="0">
                                            <input id="k{{ $id }}-zaehlt" type="checkbox" name="knoten[{{ $id }}][zaehlt]" value="1" @checked((bool) $alt('zaehlt', $k->zaehlt))
                                                   aria-label="{{ __('Zählt') }}" class="size-4 rounded border-border-strong/70 text-accent focus:ring-ring/30">
                                        @else
                                            <input type="hidden" name="knoten[{{ $id }}][zaehlt]" value="1">
                                        @endif
                                    </td>
                                    <td class="px-4">
                                        @if($z['tiefe'] > 0)
                                            @php($track = $alt('entfaellt_mit_track', $k->entfaellt_mit_track))
                                            <label for="k{{ $id }}-track" class="sr-only">{{ __('Entfällt mit') }}</label>
                                            <select id="k{{ $id }}-track" name="knoten[{{ $id }}][entfaellt_mit_track]" class="{{ $zelle }} w-24">
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
                            class="inline-flex h-9 items-center gap-2 rounded-lg bg-accent px-3.5 text-sm font-medium text-accent-contrast np-btn-primary disabled:opacity-50">
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
                        <button type="submit" class="inline-flex h-9 items-center rounded-lg px-3 text-sm text-note-ungenuegend hover:bg-note-ungenuegend/10">{{ __('Notenbaum löschen') }}</button>
                    </form>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
