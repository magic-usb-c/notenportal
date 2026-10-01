{{-- Dokumente eines Lernenden, je Art eine Karte mit gleich breiten Spalten wie die Listenansicht im Finder.
     Hochladen ist ein Sheet aus der Symbolleiste; nach einem Validierungsfehler öffnet es wieder. --}}
@use('App\Models\Dokument')
@use('App\Services\Auswertung\Konfiguration')
@use('App\Services\Dokumente\Ablage')
@php
    $feld = 'np-feld mt-1.5';
    $label = 'text-sm font-medium text-text';
    $fehlerText = 'mt-1 text-xs text-note-ungenuegend';
    $groesse = fn (int $b) => $b >= 1048576 ? number_format($b / 1048576, 1).' MB' : max(1, (int) round($b / 1024)).' KB';
    $typ = fn (Dokument $d) => match (true) {
        $d->istPdf() => 'PDF',
        str_starts_with($d->mime, 'image/') => __('Bild'),
        in_array($d->endung(), ['xlsx', 'xls', 'ods', 'csv'], true) => __('Tabelle'),
        default => strtoupper($d->endung()),
    };
    $konfiguration = Konfiguration::ausDb();
    $semesterName = fn (?int $id) => $id ? $konfiguration->semesterName($id, (int) $lernender->lernender_id) : null;
    $gruppen = $dokumente->groupBy('art');
    $hochladenFehler = $errors->hasAny(['datei', 'art', 'semester_id', 'titel']);
    $abgleich = \Illuminate\Support\Facades\Route::has('learner.documents.reconcile') && $bereich === null;
@endphp
<x-app-layout>
    <x-slot name="title">{{ __('Dokumente') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :zurueck="$zurueck" :titel="__('Dokumente')" :zaehler="$dokumente->count() ?: null"
                      :untertitel="$bereich ? $lernender->benutzer->vorname.' '.$lernender->benutzer->nachname : null">
            @if($darfHochladen)
                <x-slot:aktionen>
                    <button type="button" class="np-knopf np-knopf-primaer" x-data @click="$dispatch('open-modal', 'upload-document')">
                        <x-symbol name="plus" strich="2" />{{ __('Hochladen…') }}
                    </button>
                </x-slot:aktionen>
            @endif
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite flex flex-col gap-5 px-8">
            @if($bereich)
                @include('verwaltung.lernende._tabs', ['lernender' => $lernender, 'bereich' => $bereich, 'aktiv' => 'documents', 'klasse' => '-mb-1'])
            @endif
            @if($dokumente->isEmpty())
                <x-leer symbol="document-text" :titel="__('Noch keine Dokumente')"
                        :text="$bereich === null ? __('Zeugnisse ablegen und Noten aus PDF-Zeugnissen abgleichen.') : null">
                    @if($darfHochladen)
                        <button type="button" class="np-knopf np-knopf-sekundaer" x-data @click="$dispatch('open-modal', 'upload-document')">{{ __('Hochladen…') }}</button>
                    @endif
                </x-leer>
            @else
                @foreach(Dokument::ARTEN as $art => $artName)
                    @continue(! $gruppen->has($art))
                    <x-karte :titel="Dokument::label($art)" :polster="false">
                        <x-slot:aktionen>
                            <span class="text-sm tabular-nums text-muted">{{ $gruppen[$art]->count() }}</span>
                        </x-slot:aktionen>
                        <div class="px-2 pb-2">
                            <table class="np-tabelle table-fixed text-sm">
                                <colgroup>
                                    <col>
                                    <col class="w-24">
                                    <col class="w-56">
                                    <col class="w-32">
                                    <col class="w-56">
                                    <col class="w-24">
                                    <col class="w-52">
                                </colgroup>
                                <thead>
                                    <tr>
                                        <th scope="col">{{ __('Titel') }}</th>
                                        <th scope="col">{{ __('Typ') }}</th>
                                        <th scope="col">{{ __('Semester') }}</th>
                                        <th scope="col">{{ __('Hochgeladen') }}</th>
                                        <th scope="col">{{ __('Von') }}</th>
                                        <th scope="col" class="text-right">{{ __('Grösse') }}</th>
                                        <th scope="col"><span class="sr-only">{{ __('Aktionen') }}</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($gruppen[$art] as $d)
                                        @php
                                            $inline = in_array($d->mime, Dokument::INLINE, true);
                                            $oeffnen = $inline ? $r('show', ['dokument_id' => $d->dokument_id, 'anzeigen' => 1]) : $r('show', ['dokument_id' => $d->dokument_id]);
                                            $loeschbar = $bereich ? $darfHochladen : (int) $d->hochgeladen_von_benutzer_id === $ich;
                                        @endphp
                                        <tr>
                                            <td class="truncate">
                                                <a href="{{ $oeffnen }}" @if($inline) target="_blank" rel="noopener" @endif
                                                   class="font-medium text-text hover:underline underline-offset-2">{{ $d->titel }}</a>
                                            </td>
                                            <td class="text-muted">{{ $typ($d) }}</td>
                                            <td class="truncate text-muted">{{ $semesterName($d->semester_id ? (int) $d->semester_id : null) ?? '–' }}</td>
                                            <td class="text-muted">
                                                <time datetime="{{ $d->erstellt_am->toDateString() }}">{{ $d->erstellt_am->format('d.m.Y') }}</time>
                                            </td>
                                            <td class="truncate text-muted">{{ $d->hochgeladenVon ? $d->hochgeladenVon->vorname.' '.$d->hochgeladenVon->nachname : '–' }}</td>
                                            <td class="text-right text-muted">{{ $groesse((int) $d->groesse) }}</td>
                                            <td>
                                                <div class="flex items-center justify-end gap-1">
                                                    @if($abgleich && $d->art === 'zeugnis' && $d->istPdf())
                                                        <x-zeilen-link :href="$r('reconcile', ['dokument_id' => $d->dokument_id])" :label="__('Abgleich')" :zeile="$d->titel" />
                                                    @endif
                                                    <a href="{{ $r('show', ['dokument_id' => $d->dokument_id]) }}" class="np-knopf np-knopf-symbol"
                                                       aria-label="{{ __(':titel herunterladen', ['titel' => $d->titel]) }}" title="{{ __('Herunterladen') }}">
                                                        <x-symbol name="arrow-down-tray" />
                                                    </a>
                                                    @if($loeschbar)
                                                        <form method="POST" action="{{ $r('destroy', ['dokument_id' => $d->dokument_id]) }}"
                                                              data-bestaetigen="{{ __('Dokument «:titel» löschen?', ['titel' => $d->titel]) }}" data-bestaetigen-knopf="{{ __('Löschen') }}"
                                                              x-data="{ loading: false }" @submit="if (! $event.defaultPrevented) loading = true">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button :disabled="loading" class="np-knopf np-knopf-symbol np-knopf-symbol-gefahr"
                                                                    aria-label="{{ __(':titel löschen', ['titel' => $d->titel]) }}" title="{{ __('Löschen') }}">
                                                                <x-symbol name="trash" />
                                                            </button>
                                                        </form>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </x-karte>
                @endforeach
            @endif
        </div>
    </div>

    @if($darfHochladen)
        <x-modal name="upload-document" maxWidth="lg" :show="$hochladenFehler" focusable>
            <form method="POST" action="{{ $r('store') }}" enctype="multipart/form-data" role="dialog" aria-modal="true" aria-labelledby="upload-document-title"
                  x-data="{ loading: false }" @submit="if (! $event.defaultPrevented) loading = true">
                @csrf
                <div class="flex flex-col gap-4 p-6">
                    <h2 id="upload-document-title" class="text-base font-semibold text-text">{{ __('Dokument hochladen') }}</h2>

                    <x-ablagezone required :accept="collect(Ablage::ENDUNGEN)->map(fn ($e) => '.'.$e)->implode(',')"
                                  :titel="__('Datei wählen oder hierher ziehen')"
                                  :hinweis="__('PDF, Bild, Word, Excel, CSV · bis :mb MB', ['mb' => intdiv(Ablage::MAX_KB, 1024)])" />

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="art" class="{{ $label }}">{{ __('Art') }}</label>
                            <select id="art" name="art" required class="{{ $feld }}" @error('art') aria-invalid="true" aria-describedby="art-fehler" @enderror>
                                @foreach(Dokument::ARTEN as $wert => $text)
                                    <option value="{{ $wert }}" @selected(old('art', 'zeugnis') === $wert)>{{ Dokument::label($wert) }}</option>
                                @endforeach
                            </select>
                            @error('art')<p id="art-fehler" class="{{ $fehlerText }}">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="semester_id" class="{{ $label }}">{{ __('Semester') }}</label>
                            <select id="semester_id" name="semester_id" class="{{ $feld }}" @error('semester_id') aria-invalid="true" aria-describedby="semester_id-fehler" @enderror>
                                <option value="">–</option>
                                @foreach($semester as $s)
                                    <option value="{{ $s->semester_id }}" @selected((int) old('semester_id') === (int) $s->semester_id)>{{ $semesterName((int) $s->semester_id) }}</option>
                                @endforeach
                            </select>
                            @error('semester_id')<p id="semester_id-fehler" class="{{ $fehlerText }}">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div>
                        <label for="titel" class="{{ $label }}">{{ __('Titel') }}</label>
                        <input id="titel" name="titel" type="text" maxlength="150" value="{{ old('titel') }}" placeholder="{{ __('Optional') }}" class="{{ $feld }}"
                               @error('titel') aria-invalid="true" aria-describedby="titel-fehler" @enderror>
                        @error('titel')<p id="titel-fehler" class="{{ $fehlerText }}">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="flex justify-end gap-2 px-6 pb-6">
                    <button type="button" class="np-knopf np-knopf-sekundaer" @click="$dispatch('close-modal', 'upload-document')">{{ __('Abbrechen') }}</button>
                    <button type="submit" :disabled="loading" class="np-knopf np-knopf-primaer">{{ __('Hochladen') }}</button>
                </div>
            </form>
        </x-modal>
    @endif
</x-app-layout>
