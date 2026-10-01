{{-- Lehrberuf: Module und Fächer nebeneinander. Jedes Feld einer Zeile speichert sofort (data-sofort), Hinzufügen
     läuft über Sheets (HIG «Sheets»), die nach einem Validierungsfehler wieder öffnen. Entfernen gibt es nur ohne
     Noten und Prüfungen von Lernenden dieses Berufs, sonst bleibt «Aktiv». --}}
@php
    $feld = 'np-feld mt-1.5';
    $modulFehler = $errors->modul;
    $fachFehler = $errors->fach;
    $aktiveLernorte = $kategorien->where('aktiv', 1);
    $lernortStandard = (int) old('kategorie_id', $aktiveLernorte->firstWhere('code', 'FACH')?->kategorie_id ?? $aktiveLernorte->first()?->kategorie_id);
    $fehlerText = 'mt-1 text-xs text-note-ungenuegend';
@endphp
<x-app-layout>
    <x-slot name="title">{{ $lehrberuf->name }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :zurueck="route('admin.master-data.professions.index')" :titel="$lehrberuf->name" :untertitel="$lehrberuf->kuerzel">
            @unless($lehrberuf->aktiv)
                <span class="np-marke text-muted">{{ __('Inaktiv') }}</span>
            @endunless
            <x-slot:aktionen>
                <a href="{{ route('admin.master-data.professions.edit', $lehrberuf->lehrberuf_id) }}" class="np-knopf np-knopf-sekundaer">{{ __('Bearbeiten') }}</a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto grid np-seite grid-cols-12 items-start gap-5 px-8">

            <x-karte :titel="__('Module')" :polster="false" class="col-span-8">
                <x-slot:aktionen>
                    <span class="text-sm text-muted">{{ __(':anzahl zugewiesen', ['anzahl' => $zugewieseneModule->count()]) }}</span>
                    @if($verfuegbareModule->isNotEmpty())
                        <button type="button" class="np-knopf np-knopf-sekundaer np-knopf-klein" x-data @click="$dispatch('open-modal', 'assign-module')">
                            <x-symbol name="plus" strich="2" />{{ __('Modul hinzufügen…') }}
                        </button>
                    @endif
                </x-slot:aktionen>

                @if($zugewieseneModule->isEmpty())
                    <p class="px-5 pb-5 text-sm text-muted">{{ __('Noch keine Module zugewiesen.') }}</p>
                @else
                    <div class="px-2 pb-2">
                        <table class="np-tabelle table-fixed text-sm">
                            <thead>
                                <tr>
                                    <th scope="col" class="w-24">{{ __('Nummer') }}</th>
                                    <th scope="col">{{ __('Titel') }}</th>
                                    <th scope="col" class="w-52">{{ __('Lernort') }}</th>
                                    <th scope="col" class="w-24 text-center">{{ __('Semester') }}</th>
                                    <th scope="col" class="w-20 text-center">{{ __('Pflicht') }}</th>
                                    <th scope="col" class="w-20 text-center">{{ __('Aktiv') }}</th>
                                    <th scope="col" class="w-28"><span class="sr-only">{{ __('Aktionen') }}</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($zugewieseneModule as $m)
                                    @php
                                        $formular = 'lbm-'.$m->modul_id;
                                        $ton = $m->aktiv ? 'text-text' : 'text-muted';
                                    @endphp
                                    <tr>
                                        <td class="{{ $ton }}">{{ $m->modul_nummer }}</td>
                                        <td>
                                            <div class="flex min-w-0 items-center gap-2">
                                                <span class="truncate {{ $ton }}" title="{{ $m->titel }}">{{ $m->titel }}</span>
                                                @unless($m->aktiv)
                                                    <span class="np-marke shrink-0 text-muted">{{ __('Inaktiv') }}</span>
                                                @endunless
                                            </div>
                                        </td>
                                        <td>
                                            <select name="kategorie_id" form="{{ $formular }}" data-sofort class="np-feld np-feld-klein"
                                                    aria-label="{{ __('Lernort für :titel', ['titel' => $m->titel]) }}">
                                                @foreach($kategorien as $k)
                                                    @continue(! $k->aktiv && (int) $k->kategorie_id !== (int) $m->kategorie_id)
                                                    <option value="{{ $k->kategorie_id }}" @selected((int) $m->kategorie_id === (int) $k->kategorie_id)>
                                                        {{ $k->aktiv ? $k->name : __(':name (inaktiv)', ['name' => $k->name]) }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <input type="number" name="empfohlenes_lehrsemester_nr" min="1" max="12" value="{{ $m->empfohlenes_lehrsemester_nr }}"
                                                   form="{{ $formular }}" data-sofort placeholder="–"
                                                   aria-label="{{ __('Empfohlenes Semester :nummer', ['nummer' => $m->modul_nummer]) }}"
                                                   class="np-feld np-feld-klein mx-auto w-16 text-center">
                                        </td>
                                        <td class="text-center">
                                            <input type="hidden" name="pflicht" value="0" form="{{ $formular }}">
                                            <input type="checkbox" name="pflicht" value="1" form="{{ $formular }}" data-sofort @checked($m->pflicht)
                                                   aria-label="{{ __('Pflichtmodul :nummer', ['nummer' => $m->modul_nummer]) }}" class="np-haken">
                                        </td>
                                        <td class="text-center">
                                            <input type="hidden" name="aktiv" value="0" form="{{ $formular }}">
                                            <input type="checkbox" name="aktiv" value="1" form="{{ $formular }}" data-sofort @checked($m->aktiv)
                                                   aria-label="{{ __('Modul :nummer aktiv', ['nummer' => $m->modul_nummer]) }}" class="np-haken">
                                        </td>
                                        <td class="text-right">
                                            <form id="{{ $formular }}" method="POST" class="hidden"
                                                  action="{{ route('admin.master-data.professions.modules.update', [$lehrberuf->lehrberuf_id, $m->modul_id]) }}">
                                                @csrf
                                                @method('PATCH')
                                            </form>
                                            @unless($m->in_gebrauch)
                                                <form method="POST" action="{{ route('admin.master-data.professions.modules.remove', [$lehrberuf->lehrberuf_id, $m->modul_id]) }}"
                                                      data-bestaetigen="{{ __('Modul :nummer entfernen?', ['nummer' => $m->modul_nummer]) }}"
                                                      x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button :disabled="loading" class="np-knopf np-knopf-gefahr np-knopf-klein">{{ __('Entfernen') }}</button>
                                                </form>
                                            @endunless
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-karte>

            <x-karte :titel="__('Fächer (berufsspezifisch)')" :polster="false" class="col-span-4">
                <x-slot:aktionen>
                    <span class="text-sm text-muted">{{ __(':anzahl zugewiesen', ['anzahl' => $zugewieseneFaecher->count()]) }}</span>
                    @if($verfuegbareFaecher->isNotEmpty())
                        <button type="button" class="np-knopf np-knopf-sekundaer np-knopf-klein" x-data @click="$dispatch('open-modal', 'assign-subject')">
                            <x-symbol name="plus" strich="2" />{{ __('Fach hinzufügen…') }}
                        </button>
                    @endif
                </x-slot:aktionen>

                @if($zugewieseneFaecher->isEmpty())
                    <p class="px-5 pb-5 text-sm text-muted">{{ __('Noch keine Fächer zugewiesen.') }}</p>
                @else
                    <div class="px-2 pb-2">
                        <table class="np-tabelle table-fixed text-sm">
                            <thead>
                                <tr>
                                    <th scope="col" class="w-20">{{ __('Kürzel') }}</th>
                                    <th scope="col">{{ __('Name') }}</th>
                                    <th scope="col" class="w-16 text-center">{{ __('Aktiv') }}</th>
                                    <th scope="col" class="w-28"><span class="sr-only">{{ __('Aktionen') }}</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($zugewieseneFaecher as $f)
                                    @php $ton = $f->aktiv ? 'text-text' : 'text-muted'; @endphp
                                    <tr>
                                        <td class="truncate {{ $ton }}">{{ $f->kurzname }}</td>
                                        <td>
                                            <div class="flex min-w-0 items-center gap-2">
                                                <span class="truncate {{ $ton }}" title="{{ $f->name }}">{{ $f->name }}</span>
                                                @if($f->track_typ)
                                                    <span class="np-marke shrink-0 bg-accent/12 text-accent-text">{{ $f->track_typ }}</span>
                                                @endif
                                                @unless($f->aktiv)
                                                    <span class="np-marke shrink-0 text-muted">{{ __('Inaktiv') }}</span>
                                                @endunless
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <form method="POST" action="{{ route('admin.master-data.professions.subjects.update', [$lehrberuf->lehrberuf_id, $f->fach_id]) }}">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="aktiv" value="0">
                                                <input type="checkbox" name="aktiv" value="1" data-sofort @checked($f->aktiv)
                                                       aria-label="{{ __('Fach :name aktiv', ['name' => $f->name]) }}" class="np-haken">
                                            </form>
                                        </td>
                                        <td class="text-right">
                                            @unless($f->in_gebrauch)
                                                <form method="POST" action="{{ route('admin.master-data.professions.subjects.remove', [$lehrberuf->lehrberuf_id, $f->fach_id]) }}"
                                                      data-bestaetigen="{{ __('Fach :name entfernen?', ['name' => $f->name]) }}"
                                                      x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button :disabled="loading" class="np-knopf np-knopf-gefahr np-knopf-klein">{{ __('Entfernen') }}</button>
                                                </form>
                                            @endunless
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-karte>
        </div>
    </div>

    @if($verfuegbareModule->isNotEmpty())
        <x-modal name="assign-module" maxWidth="lg" :show="$modulFehler->any()" focusable>
            <form method="POST" action="{{ route('admin.master-data.professions.modules.assign', $lehrberuf->lehrberuf_id) }}"
                  role="dialog" aria-modal="true" aria-labelledby="assign-module-title" x-data="{ loading: false }" @submit="loading = true">
                @csrf
                <div class="flex flex-col gap-4 p-6">
                    <h2 id="assign-module-title" class="text-lg font-semibold text-text">{{ __('Modul hinzufügen') }}</h2>
                    <div>
                        <label for="modul_id" class="text-sm font-medium text-text">{{ __('Modul') }} <span class="text-note-ungenuegend">*</span></label>
                        <select id="modul_id" name="modul_id" required class="{{ $feld }}"
                                @if($modulFehler->has('modul_id')) aria-invalid="true" aria-describedby="modul_id-fehler" @endif>
                            <option value="">{{ __('Bitte wählen…') }}</option>
                            @foreach($verfuegbareModule as $m)
                                <option value="{{ $m->modul_id }}" @selected((int) old('modul_id') === (int) $m->modul_id)>{{ $m->modul_nummer }} – {{ $m->titel }}</option>
                            @endforeach
                        </select>
                        @if($modulFehler->has('modul_id'))<p id="modul_id-fehler" class="{{ $fehlerText }}">{{ $modulFehler->first('modul_id') }}</p>@endif
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="kategorie_id" class="text-sm font-medium text-text">{{ __('Lernort') }} <span class="text-note-ungenuegend">*</span></label>
                            <select id="kategorie_id" name="kategorie_id" required class="{{ $feld }}"
                                    @if($modulFehler->has('kategorie_id')) aria-invalid="true" aria-describedby="kategorie_id-fehler" @endif>
                                @foreach($aktiveLernorte as $k)
                                    <option value="{{ $k->kategorie_id }}" @selected($lernortStandard === (int) $k->kategorie_id)>{{ $k->name }}</option>
                                @endforeach
                            </select>
                            @if($modulFehler->has('kategorie_id'))<p id="kategorie_id-fehler" class="{{ $fehlerText }}">{{ $modulFehler->first('kategorie_id') }}</p>@endif
                        </div>
                        <div>
                            <label for="empfohlenes_lehrsemester_nr" class="text-sm font-medium text-text">{{ __('Empfohlenes Semester') }}</label>
                            <input type="number" id="empfohlenes_lehrsemester_nr" name="empfohlenes_lehrsemester_nr" min="1" max="12"
                                   value="{{ old('empfohlenes_lehrsemester_nr') }}" class="{{ $feld }}"
                                   @if($modulFehler->has('empfohlenes_lehrsemester_nr')) aria-invalid="true" aria-describedby="empfohlenes_lehrsemester_nr-fehler" @endif>
                            @if($modulFehler->has('empfohlenes_lehrsemester_nr'))<p id="empfohlenes_lehrsemester_nr-fehler" class="{{ $fehlerText }}">{{ $modulFehler->first('empfohlenes_lehrsemester_nr') }}</p>@endif
                        </div>
                    </div>
                    <label class="inline-flex min-h-6 cursor-pointer items-center gap-2.5 self-start text-sm text-text">
                        <input type="hidden" name="pflicht" value="0">
                        <input type="checkbox" name="pflicht" value="1" @checked(old('pflicht', '1') === '1') class="np-haken">
                        {{ __('Pflichtmodul') }}
                    </label>
                </div>
                <div class="flex justify-end gap-2 px-6 pb-6">
                    <button type="button" class="np-knopf np-knopf-sekundaer" @click="$dispatch('close-modal', 'assign-module')">{{ __('Abbrechen') }}</button>
                    <button type="submit" :disabled="loading" class="np-knopf np-knopf-primaer">{{ __('Zuweisen') }}</button>
                </div>
            </form>
        </x-modal>
    @endif

    @if($verfuegbareFaecher->isNotEmpty())
        <x-modal name="assign-subject" maxWidth="md" :show="$fachFehler->any()" focusable>
            <form method="POST" action="{{ route('admin.master-data.professions.subjects.assign', $lehrberuf->lehrberuf_id) }}"
                  role="dialog" aria-modal="true" aria-labelledby="assign-subject-title" x-data="{ loading: false }" @submit="loading = true">
                @csrf
                <div class="flex flex-col gap-4 p-6">
                    <h2 id="assign-subject-title" class="text-lg font-semibold text-text">{{ __('Fach hinzufügen') }}</h2>
                    <div>
                        <label for="fach_id" class="text-sm font-medium text-text">{{ __('Fach') }} <span class="text-note-ungenuegend">*</span></label>
                        <select id="fach_id" name="fach_id" required class="{{ $feld }}"
                                @if($fachFehler->has('fach_id')) aria-invalid="true" aria-describedby="fach_id-fehler" @endif>
                            <option value="">{{ __('Bitte wählen…') }}</option>
                            @foreach($verfuegbareFaecher as $f)
                                <option value="{{ $f->fach_id }}" @selected((int) old('fach_id') === (int) $f->fach_id)>{{ $f->kurzname }} – {{ $f->name }}</option>
                            @endforeach
                        </select>
                        @if($fachFehler->has('fach_id'))<p id="fach_id-fehler" class="{{ $fehlerText }}">{{ $fachFehler->first('fach_id') }}</p>@endif
                    </div>
                </div>
                <div class="flex justify-end gap-2 px-6 pb-6">
                    <button type="button" class="np-knopf np-knopf-sekundaer" @click="$dispatch('close-modal', 'assign-subject')">{{ __('Abbrechen') }}</button>
                    <button type="submit" :disabled="loading" class="np-knopf np-knopf-primaer">{{ __('Zuweisen') }}</button>
                </div>
            </form>
        </x-modal>
    @endif
</x-app-layout>
