@php
    // Verweis in den Modulbaukasten nur mit bekannter Version – siehe App\Support\Modulbaukasten.
    $mbk = \App\Support\Modulbaukasten::modulLink($modul->modul_nummer, $mbkVersion ?? $modul->version);
    $feld = 'np-feld mt-1';
    $groesse = fn (int $b) => $b >= 1048576 ? number_format($b / 1048576, 1).' MB' : max(1, (int) round($b / 1024)).' KB';
    $ich = (int) auth()->user()->benutzer_id;
    $istAdmin = auth()->user()->hasRole('Admin');
@endphp

<x-app-layout>
    <x-slot name="title">{{ $modul->modul_nummer }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :zurueck="route('modules.index')" :titel="$modul->modul_nummer.' '.$modul->titel">
            <x-slot:aktionen>
                <a href="{{ route('modules.edit', $modul->modul_id) }}"
                   class="np-knopf np-knopf-sekundaer">{{ __('Bearbeiten') }}</a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-8 space-y-4">

            <div class="np-karte p-5 space-y-3">
                <div class="flex flex-wrap items-center gap-2 text-xs text-muted">
                    @if($modul->version)<span class="np-marke text-muted">{{ __('Katalogversion') }} {{ $modul->version }}</span>@endif
                    @if($modul->ausKatalog())
                        <span class="np-marke text-muted">{{ __('aus dem Modulbaukasten') }}</span>
                    @elseif($modul->ersteller)
                        <span>{{ __('Erfasst von :name', ['name' => trim($modul->ersteller->vorname.' '.$modul->ersteller->nachname)]) }}</span>
                    @endif
                </div>

                @if($modul->beschreibung)
                    <p class="whitespace-pre-line text-sm text-text">{{ $modul->beschreibung }}</p>
                @endif

                <div class="flex flex-wrap gap-4 text-sm">
                    @if($mbk)
                        <a href="{{ $mbk }}" target="_blank" rel="noopener noreferrer" class="text-accent-text underline underline-offset-2 hover:opacity-80">
                            {{ __('Modulbaukasten') }}<span class="sr-only"> ({{ __('neues Fenster') }})</span>
                        </a>
                    @endif
                    @if($modul->link)
                        <a href="{{ $modul->link }}" target="_blank" rel="noopener noreferrer" class="text-accent-text underline underline-offset-2 hover:opacity-80">
                            {{ __('Verweis') }}<span class="sr-only"> ({{ __('neues Fenster') }})</span>
                        </a>
                    @endif
                </div>

                @if(\Illuminate\Support\Facades\Route::has('modules.enroll') && auth()->user()->hasRole('Lernender'))
                    @if($belegt)
                        <p class="text-sm text-muted">{{ __('Dieses Modul steht in deiner Notenerfassung zur Auswahl.') }}</p>
                    @else
                        <form method="POST" action="{{ route('modules.enroll', $modul->modul_id) }}" x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                            @csrf
                            <button type="submit" :disabled="loading"
                                    class="np-knopf np-knopf-primaer">
                                {{ __('Zu meinen Modulen hinzufügen') }}
                            </button>
                            <span class="ml-2 text-xs text-muted">{{ __('Danach kannst du hier eigene Noten erfassen.') }}</span>
                        </form>
                    @endif
                @endif
            </div>

            @if($modul->handlungsziele->isNotEmpty())
                <section class="np-karte p-5">
                    <h2 class="text-sm font-semibold text-text">{{ __('Handlungsziele und Handlungskompetenzen') }}</h2>
                    <ul class="mt-3 space-y-2">
                        @foreach($modul->handlungsziele as $ziel)
                            <li class="flex gap-3 text-sm">
                                <span class="w-10 shrink-0 tabular-nums text-muted">{{ $ziel->nummer }}</span>
                                <span class="text-text">{{ $ziel->text }}</span>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            @if($modul->lbvElemente->isNotEmpty())
                <section class="np-karte p-5">
                    <h2 class="text-sm font-semibold text-text">{{ __('Leistungsbeurteilung') }}</h2>
                    <ul class="mt-3 space-y-2">
                        @foreach($modul->lbvElemente as $element)
                            <li class="flex flex-wrap gap-x-3 text-sm">
                                <span class="text-text">{{ $element->bezeichnung }}</span>
                                @if($element->gewichtung_prozent !== null)<span class="text-muted">{{ (int) $element->gewichtung_prozent }}%</span>@endif
                                @if($element->pruefungsform)<span class="text-muted">{{ $element->pruefungsform }}</span>@endif
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <section class="np-karte p-5 space-y-4">
                <h2 class="text-sm font-semibold text-text">{{ __('Unterlagen') }}</h2>

                @if($modul->dokumente->isEmpty())
                    <p class="text-sm text-muted">{{ __('Noch keine Unterlagen. Lade die Modulbeschreibung hoch – alle sehen sie danach.') }}</p>
                @else
                    <ul class="divide-y divide-border rounded-xl border border-border overflow-hidden">
                        @foreach($modul->dokumente as $d)
                            <li class="flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-3 sm:flex-nowrap">
                                <span class="inline-flex size-11 shrink-0 items-center justify-center rounded-xl bg-accent/10 text-3xs font-semibold text-accent-text">{{ strtoupper($d->endung()) }}</span>
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-sm font-medium text-text">{{ $d->titel }}</div>
                                    <div class="truncate text-xs text-muted">
                                        {{ collect([$d->erstellt_am?->format('d.m.Y'), $groesse((int) $d->groesse), $d->hochgeladenVon ? $d->hochgeladenVon->vorname.' '.$d->hochgeladenVon->nachname : null])->filter()->implode(' · ') }}
                                    </div>
                                </div>
                                <div class="flex w-full shrink-0 items-center justify-end gap-1 sm:w-auto">
                                    @if(in_array($d->mime, \App\Models\ModulDokument::INLINE, true))
                                        <a href="{{ route('modules.documents.show', [$modul->modul_id, $d->modul_dokument_id]) }}" target="_blank" rel="noopener"
                                           class="np-knopf np-knopf-schlicht">{{ __('Öffnen') }}<span class="sr-only"> ({{ __('neues Fenster') }})</span></a>
                                    @endif
                                    <a href="{{ route('modules.documents.show', [$modul->modul_id, $d->modul_dokument_id]) }}?download=1"
                                       aria-label="{{ __(':titel herunterladen', ['titel' => $d->titel]) }}"
                                       class="np-knopf np-knopf-symbol">
                                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v12m0 0l-4-4m4 4l4-4M4 20h16"/></svg>
                                    </a>
                                    @if($istAdmin || (int) $d->hochgeladen_von_benutzer_id === $ich)
                                        <form method="POST" action="{{ route('modules.documents.destroy', [$modul->modul_id, $d->modul_dokument_id]) }}"
                                              data-bestaetigen="{{ __('Unterlage löschen?') }}" data-bestaetigen-knopf="{{ __('Löschen') }}" x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                                            @csrf
                                            @method('DELETE')
                                            <button :disabled="loading" aria-label="{{ __(':titel löschen', ['titel' => $d->titel]) }}"
                                                    class="np-knopf np-knopf-symbol np-knopf-symbol-gefahr"><x-symbol name="trash" /></button>
                                        </form>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <form method="POST" action="{{ route('modules.documents.store', $modul->modul_id) }}" enctype="multipart/form-data"
                      class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_12rem_auto] sm:items-end"
                      x-data="{ loading: false, name: '' }" @submit="if (!$event.defaultPrevented) loading = true">
                    @csrf
                    <div>
                        <label for="datei" class="text-sm font-medium text-text">{{ __('Datei') }}</label>
                        <x-datei-feld id="datei" name="datei" required accept=".pdf,.jpg,.jpeg,.png,.docx,.odt" />
                        <p class="mt-1 text-xs text-muted">{{ __('PDF, Bild, Word, OpenDocument · bis 20 MB') }}</p>
                        @error('datei')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="titel" class="text-sm font-medium text-text">{{ __('Titel') }}</label>
                        <input id="titel" name="titel" type="text" maxlength="150" value="{{ old('titel') }}" class="{{ $feld }}">
                        @error('titel')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                    </div>
                    <button type="submit" :disabled="loading"
                            class="np-knopf np-knopf-primaer">{{ __('Hochladen') }}</button>
                </form>
            </section>

        </div>
    </div>
</x-app-layout>
