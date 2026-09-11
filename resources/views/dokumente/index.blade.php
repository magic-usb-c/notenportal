<x-app-layout>
    <x-slot name="title">{{ __('Dokumente') }}</x-slot>
    @php
        $feld = 'mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring';
        $label = 'text-sm font-medium text-text';
        $groesse = fn (int $b) => $b >= 1048576 ? number_format($b / 1048576, 1).' MB' : max(1, (int) round($b / 1024)).' KB';
        $typ = fn (\App\Models\Dokument $d) => match (true) {
            $d->istPdf() => 'PDF',
            str_starts_with($d->mime, 'image/') => __('Bild'),
            in_array($d->endung(), ['xlsx', 'xls', 'ods', 'csv'], true) => __('Tabelle'),
            default => strtoupper($d->endung()),
        };
        $gruppen = $dokumente->groupBy('art');
    @endphp

    <x-slot name="header">
        <x-seitenkopf titel="{{ __('Dokumente') }}" :untertitel="$bereich ? $lernender->benutzer->vorname.' '.$lernender->benutzer->nachname : null">
            @if($zurueck)
                <x-slot:aktionen>
                    <a href="{{ $zurueck }}" class="inline-flex h-9 items-center gap-2 rounded-lg glass-btn px-3.5 text-sm font-medium text-text">{{ __('Zurück') }}</a>
                </x-slot:aktionen>
            @endif
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col gap-5">
            @if($darfHochladen)
                <form method="POST" action="{{ $r('store') }}" enctype="multipart/form-data" class="rounded-xl border border-border bg-card p-5 flex flex-col gap-4"
                      x-data="{ loading: false, name: '', ueber: false }" @submit="if (!$event.defaultPrevented) loading = true">
                    @csrf
                    <label for="datei" class="flex flex-col items-center justify-center gap-1.5 rounded-xl border-2 border-dashed px-4 py-7 text-center cursor-pointer transition-colors"
                           :class="ueber ? 'border-accent bg-accent/5' : 'border-border hover:border-accent/50'"
                           @dragover.prevent="ueber = true" @dragleave.prevent="ueber = false"
                           @drop.prevent="ueber = false; $refs.datei.files = $event.dataTransfer.files; name = $event.dataTransfer.files[0]?.name ?? ''">
                        <svg class="w-7 h-7 text-accent" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0l-4 4m4-4l4 4M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2"/></svg>
                        <span class="text-sm font-medium text-text" x-text="name || @js(__('Datei wählen oder hierher ziehen'))"></span>
                        <span class="text-xs text-muted">{{ __('PDF, Bild, Excel, CSV · bis 10 MB') }}</span>
                        <input id="datei" x-ref="datei" name="datei" type="file" required class="sr-only"
                               accept=".pdf,.jpg,.jpeg,.png,.xlsx,.xls,.ods,.csv,.docx" @change="name = $event.target.files[0]?.name ?? ''">
                    </label>
                    @error('datei')<p class="-mt-2 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror

                    <div class="grid sm:grid-cols-[10rem_12rem_minmax(0,1fr)_auto] gap-3 items-end">
                        <div>
                            <label for="art" class="{{ $label }}">{{ __('Art') }} *</label>
                            <select id="art" name="art" class="{{ $feld }}">
                                @foreach(\App\Models\Dokument::ARTEN as $wert => $text)
                                    <option value="{{ $wert }}" @selected(old('art', 'zeugnis') === $wert)>{{ \App\Models\Dokument::label($wert) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="semester_id" class="{{ $label }}">{{ __('Semester') }}</label>
                            <select id="semester_id" name="semester_id" class="{{ $feld }}">
                                <option value="">–</option>
                                @foreach($semester as $s)
                                    <option value="{{ $s->semester_id }}" @selected((int) old('semester_id') === (int) $s->semester_id)>{{ $s->bezeichnung }}</option>
                                @endforeach
                            </select>
                            @error('semester_id')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="titel" class="{{ $label }}">{{ __('Titel') }}</label>
                            <input id="titel" name="titel" type="text" maxlength="150" value="{{ old('titel') }}" class="{{ $feld }}">
                            @error('titel')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                        </div>
                        <button type="submit" :disabled="loading" class="inline-flex items-center justify-center px-5 h-10 rounded-xl bg-accent text-accent-contrast text-sm font-semibold np-btn-primary disabled:opacity-60">{{ __('Hochladen') }}</button>
                    </div>
                </form>
            @endif

            @if($dokumente->isEmpty())
                <div class="flex flex-col items-center gap-2 rounded-xl border border-border bg-card px-5 py-10 text-center">
                    <span class="inline-flex size-10 items-center justify-center rounded-full bg-accent/10 text-accent" aria-hidden="true">
                        <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6M7 3h7l5 5v11a2 2 0 01-2 2H7a2 2 0 01-2-2V5a2 2 0 012-2z"/></svg>
                    </span>
                    <p class="text-sm font-medium text-text">{{ __('Noch keine Dokumente') }}</p>
                    @if($bereich === null)
                        <p class="max-w-md text-sm text-muted">{{ __('Leg hier Zeugnisse und Semesterberichte ab. Aus einem PDF-Zeugnis kannst du deine Noten danach mit dem Portal abgleichen.') }}</p>
                    @endif
                </div>
            @else
                @foreach(\App\Models\Dokument::ARTEN as $art => $artName)
                    @continue(! $gruppen->has($art))
                    <section class="flex flex-col gap-2">
                        <h3 class="px-1 text-xs uppercase tracking-widest text-muted font-semibold">{{ \App\Models\Dokument::label($art) }} · {{ $gruppen[$art]->count() }}</h3>
                        <ul class="rounded-xl border border-border bg-card divide-y divide-border overflow-hidden">
                            @foreach($gruppen[$art] as $d)
                                <li class="flex flex-wrap sm:flex-nowrap items-center gap-x-4 gap-y-1 px-4 py-3">
                                    <span class="w-11 h-11 shrink-0 rounded-xl bg-accent/10 text-accent text-[10px] font-bold inline-flex items-center justify-center">{{ $typ($d) }}</span>
                                    <div class="min-w-0 flex-1">
                                        <div class="text-sm font-medium text-text truncate">{{ $d->titel }}</div>
                                        <div class="text-xs text-muted truncate">
                                            {{ collect([$d->semester?->bezeichnung, $d->erstellt_am->format('d.m.Y'), $groesse($d->groesse), $d->hochgeladenVon ? $d->hochgeladenVon->vorname.' '.$d->hochgeladenVon->nachname : null])->filter()->implode(' · ') }}
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-end gap-1 shrink-0 w-full sm:w-auto">
                                        @if($d->art === 'zeugnis' && $d->istPdf() && \Illuminate\Support\Facades\Route::has('learner.documents.reconcile'))
                                            <a href="{{ $r('reconcile', ['dokument_id' => $d->dokument_id]) }}" class="inline-flex items-center px-3 min-h-9 rounded-lg text-sm text-accent hover:bg-accent/10">{{ __('Abgleich') }}</a>
                                        @endif
                                        @if(in_array($d->mime, \App\Models\Dokument::INLINE, true))
                                            <a href="{{ $r('show', ['dokument_id' => $d->dokument_id, 'anzeigen' => 1]) }}" target="_blank" rel="noopener"
                                               class="inline-flex items-center px-3 min-h-9 rounded-lg text-sm text-accent hover:bg-accent/10">{{ __('Öffnen') }}</a>
                                        @endif
                                        <a href="{{ $r('show', ['dokument_id' => $d->dokument_id]) }}" aria-label="{{ __(':titel herunterladen', ['titel' => $d->titel]) }}"
                                           class="w-9 h-9 inline-flex items-center justify-center rounded-lg text-muted hover:text-text hover:bg-accent/10">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v12m0 0l-4-4m4 4l4-4M4 20h16"/></svg>
                                        </a>
                                        @if($bereich ? $darfHochladen : (int) $d->hochgeladen_von_benutzer_id === $ich)
                                            <form method="POST" action="{{ $r('destroy', ['dokument_id' => $d->dokument_id]) }}" onsubmit="return confirm('{{ __('Dokument löschen?') }}')"
                                                  x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                                                @csrf
                                                @method('DELETE')
                                                <button :disabled="loading" aria-label="{{ __(':titel löschen', ['titel' => $d->titel]) }}"
                                                        class="w-9 h-9 inline-flex items-center justify-center rounded-lg text-muted hover:text-note-ungenuegend hover:bg-note-ungenuegend/10 disabled:opacity-60">×</button>
                                            </form>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endforeach
            @endif
        </div>
    </div>
</x-app-layout>
