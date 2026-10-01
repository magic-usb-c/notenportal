@php
    // Verweis in den Modulbaukasten nur mit bekannter Version – siehe App\Support\Modulbaukasten.
    $mbk = \App\Support\Modulbaukasten::modulLink($modul->modul_nummer, $mbkVersion ?? $modul->version);
    $feld = 'np-feld mt-1';
    $groesse = fn (int $b) => $b >= 1048576 ? number_format($b / 1048576, 1).' MB' : max(1, (int) round($b / 1024)).' KB';
    $ich = (int) auth()->user()->benutzer_id;
    $istAdmin = auth()->user()->hasRole('Admin');
    $lernender = \Illuminate\Support\Facades\Route::has('modules.enroll') && auth()->user()->hasRole('Lernender');
    $kannBelegen = $lernender && ! $belegt;
    $hatInhalt = $modul->beschreibung || $modul->handlungsziele->isNotEmpty() || $modul->lbvElemente->isNotEmpty();
    $meta = collect([
        $modul->version ? __('Katalogversion').' '.$modul->version : null,
        $modul->ausKatalog() ? __('aus dem Modulbaukasten')
            : ($modul->ersteller ? __('Erfasst von :name', ['name' => trim($modul->ersteller->vorname.' '.$modul->ersteller->nachname)]) : null),
        $lernender && $belegt ? __('In deinen Modulen') : null,
    ])->filter()->implode(' · ') ?: null;
@endphp

<x-app-layout>
    <x-slot name="title">{{ $modul->modul_nummer }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :zurueck="route('modules.index')" :titel="$modul->modul_nummer.' '.$modul->titel" :untertitel="$meta">
            <x-slot:aktionen>
                <a href="{{ route('modules.edit', $modul->modul_id) }}"
                   class="np-knopf np-knopf-sekundaer">{{ __('Bearbeiten') }}</a>
                @if($kannBelegen)
                    <form method="POST" action="{{ route('modules.enroll', $modul->modul_id) }}" x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                        @csrf
                        <button type="submit" :disabled="loading" class="np-knopf np-knopf-primaer"
                                title="{{ __('Danach kannst du hier eigene Noten erfassen.') }}">{{ __('Zu meinen Modulen hinzufügen') }}</button>
                    </form>
                @endif
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div @class(['mx-auto np-seite px-8', 'grid grid-cols-12 items-start gap-4' => $hatInhalt])>
            @if($hatInhalt)
                <div class="col-span-8 flex flex-col gap-4">
                    @if($modul->beschreibung)
                        <section class="np-karte p-5">
                            <h2 class="text-sm font-semibold text-text">{{ __('Beschreibung') }}</h2>
                            <p class="mt-2 max-w-[75ch] whitespace-pre-line text-sm text-text">{{ $modul->beschreibung }}</p>
                        </section>
                    @endif

                    @if($modul->handlungsziele->isNotEmpty())
                        <section class="np-karte p-5">
                            <h2 class="text-sm font-semibold text-text">{{ __('Handlungsziele und Handlungskompetenzen') }}</h2>
                            <ol class="mt-3 flex flex-col gap-2.5">
                                @foreach($modul->handlungsziele as $ziel)
                                    <li class="flex gap-3 text-sm">
                                        <span class="w-10 shrink-0 tabular-nums text-muted">{{ $ziel->nummer }}</span>
                                        <span class="max-w-[75ch] text-text">{{ $ziel->text }}</span>
                                    </li>
                                @endforeach
                            </ol>
                        </section>
                    @endif

                    @if($modul->lbvElemente->isNotEmpty())
                        <section class="np-karte overflow-hidden">
                            <h2 class="px-5 pb-3 pt-5 text-sm font-semibold text-text">{{ __('Leistungsbeurteilung') }}</h2>
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-y border-border bg-surface-2">
                                        <th scope="col" class="h-9 px-5 text-left text-2xs font-medium text-muted">{{ __('Element') }}</th>
                                        <th scope="col" class="h-9 px-3 text-left text-2xs font-medium text-muted">{{ __('Prüfungsform') }}</th>
                                        <th scope="col" class="h-9 px-5 text-right text-2xs font-medium text-muted">{{ __('Gewicht') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($modul->lbvElemente as $element)
                                        <tr class="border-b border-border last:border-0">
                                            <td class="h-11 px-5 text-text">{{ $element->bezeichnung }}</td>
                                            <td class="h-11 px-3 text-muted">{{ $element->pruefungsform ?: '–' }}</td>
                                            <td class="h-11 px-5 text-right tabular-nums text-text">{{ $element->gewichtung_prozent !== null ? (int) $element->gewichtung_prozent.' %' : '–' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </section>
                    @endif
                </div>
            @endif

            <div @class(['flex flex-col gap-4', 'col-span-4' => $hatInhalt, 'max-w-3xl' => ! $hatInhalt])>
                @if($mbk || $modul->link)
                    <section class="np-karte p-5">
                        <h2 class="text-sm font-semibold text-text">{{ __('Verweise') }}</h2>
                        <ul class="mt-2 flex flex-col gap-1 text-sm">
                            @if($mbk)
                                <li><a href="{{ $mbk }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-6 items-center gap-1 text-accent-text hover:underline underline-offset-2">
                                    {{ __('Modulbaukasten') }}<x-symbol name="arrow-up-right" class="size-3.5" /><span class="sr-only"> ({{ __('neues Fenster') }})</span>
                                </a></li>
                            @endif
                            @if($modul->link)
                                <li><a href="{{ $modul->link }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-6 items-center gap-1 text-accent-text hover:underline underline-offset-2">
                                    {{ __('Verweis') }}<x-symbol name="arrow-up-right" class="size-3.5" /><span class="sr-only"> ({{ __('neues Fenster') }})</span>
                                </a></li>
                            @endif
                        </ul>
                    </section>
                @endif
                <section class="np-karte flex flex-col gap-4 p-5">
                    <h2 class="text-sm font-semibold text-text">{{ __('Unterlagen') }}</h2>

                    @if($modul->dokumente->isEmpty())
                        <p class="text-sm text-muted">{{ __('Noch keine Unterlagen. Lade die Modulbeschreibung hoch – alle sehen sie danach.') }}</p>
                    @else
                        <ul class="divide-y divide-border rounded-xl border border-border overflow-hidden">
                            @foreach($modul->dokumente as $d)
                                <li class="flex flex-nowrap items-center gap-x-4 gap-y-1 px-4 py-3">
                                    <span class="inline-flex size-11 shrink-0 items-center justify-center rounded-xl bg-accent/10 text-3xs font-semibold text-accent-text">{{ strtoupper($d->endung()) }}</span>
                                    <div class="min-w-0 flex-1">
                                        <div class="truncate text-sm font-medium text-text">{{ $d->titel }}</div>
                                        <div class="truncate text-xs text-muted">
                                            {{ collect([$d->erstellt_am?->format('d.m.Y'), $groesse((int) $d->groesse), $d->hochgeladenVon ? $d->hochgeladenVon->vorname.' '.$d->hochgeladenVon->nachname : null])->filter()->implode(' · ') }}
                                        </div>
                                    </div>
                                    <div class="flex w-auto shrink-0 items-center justify-end gap-1">
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
                          class="flex flex-col gap-3 border-t border-border pt-4"
                          x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                        @csrf
                        <div>
                            <label for="datei" class="text-sm font-medium text-text">{{ __('Datei') }}</label>
                            <x-datei-feld id="datei" name="datei" required accept=".pdf,.jpg,.jpeg,.png,.docx,.odt" aria-describedby="datei-hilfe" />
                            <p id="datei-hilfe" class="mt-1 text-xs text-muted">{{ __('PDF, Bild, Word, OpenDocument · bis 20 MB') }}</p>
                            @error('datei')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="titel" class="text-sm font-medium text-text">{{ __('Titel') }}</label>
                            <input id="titel" name="titel" type="text" maxlength="150" value="{{ old('titel') }}" class="{{ $feld }}">
                            @error('titel')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" :disabled="loading" class="np-knopf np-knopf-sekundaer">{{ __('Hochladen') }}</button>
                        </div>
                    </form>
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
