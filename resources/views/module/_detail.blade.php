@php
    // Verweis in den Modulbaukasten nur mit bekannter Version – siehe App\Support\Modulbaukasten.
    $katalogversion = $mbkVersion ?? $modul->version;
    $mbk = \App\Support\Modulbaukasten::modulLink($modul->modul_nummer, $katalogversion);
    $groesse = fn (int $b) => $b >= 1048576 ? number_format($b / 1048576, 1).' MB' : max(1, (int) round($b / 1024)).' KB';
    $ich = (int) auth()->user()->benutzer_id;
    $istAdmin = auth()->user()->hasRole('Admin');
    $lernender = \Illuminate\Support\Facades\Route::has('modules.enroll') && auth()->user()->hasRole('Lernender');
    $kannBelegen = $lernender && ! $belegt;
    $lbvForm = $modul->lbvElemente->contains(fn ($e) => filled($e->pruefungsform));
    $lbvGewicht = $modul->lbvElemente->contains(fn ($e) => $e->gewichtung_prozent !== null);
    $meta = collect([
        $katalogversion ? __('Katalogversion').' '.$katalogversion : null,
        $modul->ausKatalog() ? __('aus dem Modulbaukasten')
            : ($modul->ersteller ? __('Erfasst von :name', ['name' => trim($modul->ersteller->vorname.' '.$modul->ersteller->nachname)]) : null),
        $lernender && $belegt ? __('In deinen Modulen') : null,
    ])->filter()->implode(' · ') ?: null;
@endphp
{{-- Gewähltes Modul neben der Quellliste: Kopf mit Aktionen, links der Inhalt, rechts Verweise und Unterlagen --}}
<article class="flex flex-col gap-6" aria-labelledby="modul-titel">
    <header class="flex items-start justify-between gap-6">
        <div class="min-w-0">
            <h2 id="modul-titel" class="flex flex-wrap items-center gap-x-2.5 gap-y-1 text-xl font-semibold text-text">
                <span class="min-w-0 break-words">{{ $modul->modul_nummer }} {{ $modul->titel }}</span>
                @unless($modul->aktiv)<span class="np-marke bg-surface-2 text-muted">{{ __('Inaktiv') }}</span>@endunless
            </h2>
            @if($meta)<p class="mt-1 text-sm text-muted">{{ $meta }}</p>@endif
        </div>
        <div class="flex shrink-0 items-center gap-2">
            <a href="{{ route('modules.edit', $modul->modul_id) }}" class="np-knopf np-knopf-sekundaer">{{ __('Bearbeiten') }}</a>
            @if($kannBelegen)
                <form method="POST" action="{{ route('modules.enroll', $modul->modul_id) }}" x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                    @csrf
                    <button type="submit" :disabled="loading" class="np-knopf np-knopf-primaer">{{ __('Zu meinen Modulen hinzufügen') }}</button>
                </form>
            @endif
        </div>
    </header>

    <div class="grid grid-cols-12 items-start gap-5">
        <div class="col-span-8 flex flex-col gap-5">
            @if($modul->beschreibung)
                <section class="np-karte p-5" aria-labelledby="modul-beschreibung">
                    <h3 id="modul-beschreibung" class="text-sm font-semibold text-text">{{ __('Beschreibung') }}</h3>
                    <p class="mt-2 max-w-[75ch] whitespace-pre-line text-sm text-text">{{ $modul->beschreibung }}</p>
                </section>
            @endif

            @if($modul->handlungsziele->isNotEmpty())
                <section class="np-karte p-5" aria-labelledby="modul-ziele">
                    <h3 id="modul-ziele" class="text-sm font-semibold text-text">{{ __('Handlungsziele und Handlungskompetenzen') }}</h3>
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
                <section class="np-karte" aria-labelledby="modul-lbv">
                    <h3 id="modul-lbv" class="px-5 pb-2 pt-5 text-sm font-semibold text-text">{{ __('Leistungsbeurteilung') }}</h3>
                    <div class="px-2 pb-2">
                        <table class="np-tabelle table-fixed text-sm">
                            <colgroup>
                                <col>
                                @if($lbvForm)<col class="w-56">@endif
                                @if($lbvGewicht)<col class="w-24">@endif
                            </colgroup>
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('Element') }}</th>
                                    @if($lbvForm)<th scope="col">{{ __('Prüfungsform') }}</th>@endif
                                    @if($lbvGewicht)<th scope="col" class="text-right">{{ __('Gewicht') }}</th>@endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($modul->lbvElemente as $element)
                                    <tr>
                                        <td class="text-text">{{ $element->bezeichnung }}</td>
                                        @if($lbvForm)<td class="text-muted">{{ $element->pruefungsform ?: '–' }}</td>@endif
                                        @if($lbvGewicht)<td class="text-right tabular-nums text-text">{{ $element->gewichtung_prozent !== null ? (int) $element->gewichtung_prozent.' %' : '–' }}</td>@endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            @unless($modul->beschreibung || $modul->handlungsziele->isNotEmpty() || $modul->lbvElemente->isNotEmpty())
                <p class="np-karte flex items-center gap-3 px-5 py-4 text-sm text-muted">
                    {{ __('Noch keine Beschreibung und keine Handlungsziele.') }}
                    <a href="{{ route('modules.edit', $modul->modul_id) }}" class="inline-flex min-h-6 items-center text-accent-text underline-offset-2 hover:underline">{{ __('Ergänzen') }}</a>
                </p>
            @endunless
        </div>

        <div class="col-span-4 flex flex-col gap-5">
            @if($mbk || $modul->link)
                <section class="np-karte p-5" aria-labelledby="modul-verweise">
                    <h3 id="modul-verweise" class="text-sm font-semibold text-text">{{ __('Verweise') }}</h3>
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

            <section class="np-karte flex flex-col gap-4 p-5" aria-labelledby="modul-unterlagen">
                <h3 id="modul-unterlagen" class="text-sm font-semibold text-text">{{ __('Unterlagen') }}</h3>

                @if($modul->dokumente->isEmpty())
                    <p class="text-sm text-muted">{{ __('Noch keine Unterlagen.') }}</p>
                @else
                    <ul class="np-gruppe">
                        @foreach($modul->dokumente as $d)
                            <li class="flex items-center gap-3 py-2.5">
                                <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-lg bg-fill text-3xs font-semibold text-muted">{{ strtoupper($d->endung()) }}</span>
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-sm font-medium text-text">{{ $d->titel }}</div>
                                    <div class="truncate text-xs text-muted">
                                        {{ collect([$d->erstellt_am?->format('d.m.Y'), $groesse((int) $d->groesse), $d->hochgeladenVon ? $d->hochgeladenVon->vorname.' '.$d->hochgeladenVon->nachname : null])->filter()->implode(' · ') }}
                                    </div>
                                </div>
                                <div class="flex shrink-0 items-center gap-1">
                                    @if(in_array($d->mime, \App\Models\ModulDokument::INLINE, true))
                                        <a href="{{ route('modules.documents.show', [$modul->modul_id, $d->modul_dokument_id]) }}" target="_blank" rel="noopener"
                                           aria-label="{{ __(':titel öffnen', ['titel' => $d->titel]) }} ({{ __('neues Fenster') }})" title="{{ __('Öffnen') }}"
                                           class="np-knopf np-knopf-symbol"><x-symbol name="arrow-up-right" /></a>
                                    @endif
                                    <a href="{{ route('modules.documents.show', [$modul->modul_id, $d->modul_dokument_id]) }}?download=1"
                                       aria-label="{{ __(':titel herunterladen', ['titel' => $d->titel]) }}" title="{{ __('Herunterladen') }}"
                                       class="np-knopf np-knopf-symbol"><x-symbol name="arrow-down-tray" /></a>
                                    @if($istAdmin || (int) $d->hochgeladen_von_benutzer_id === $ich)
                                        <form method="POST" action="{{ route('modules.documents.destroy', [$modul->modul_id, $d->modul_dokument_id]) }}"
                                              data-bestaetigen="{{ __('Unterlage löschen?') }}" data-bestaetigen-knopf="{{ __('Löschen') }}" x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                                            @csrf
                                            @method('DELETE')
                                            <button :disabled="loading" aria-label="{{ __(':titel löschen', ['titel' => $d->titel]) }}" title="{{ __('Löschen') }}"
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
                    <x-ablagezone polster="py-6" required
                                  :accept="collect(\App\Services\Dokumente\Modulablage::ENDUNGEN)->map(fn ($e) => '.'.$e)->implode(',')"
                                  :titel="__('Datei wählen oder hierher ziehen')"
                                  :hinweis="__('PDF, Bild, Word, OpenDocument · bis :mb MB', ['mb' => intdiv(\App\Services\Dokumente\Modulablage::MAX_KB, 1024)])" />
                    <div>
                        <label for="titel" class="text-sm font-medium text-text">{{ __('Titel') }}</label>
                        <input id="titel" name="titel" type="text" maxlength="150" value="{{ old('titel') }}" placeholder="{{ __('Optional') }}" class="np-feld mt-1.5 w-full"
                               @error('titel') aria-invalid="true" aria-describedby="titel-fehler" @enderror>
                        @error('titel')<p id="titel-fehler" class="mt-1.5 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" :disabled="loading" class="np-knopf np-knopf-sekundaer">{{ __('Hochladen') }}</button>
                    </div>
                </form>
            </section>
        </div>
    </div>
</article>
