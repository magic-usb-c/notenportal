@use('App\Services\Stammdaten\StammdatenVorlage')
<x-einrichtung schritt="professions" :stand="$stand" :titel="__('Lehrberufe & Fächer')">
    @php
        $katalog = collect($vorlage['lehrberufe'] ?? []);
        $gewaehlt = old('berufe', $lehrberufe->isEmpty() ? $katalog->where('vorauswahl', true)->pluck('kuerzel')->all() : []);
        $weitere = $lehrberufe->reject(fn ($lb) => in_array((int) $lb->lehrberuf_id, $vorhandeneBerufe, true));
        $faecherGewaehlt = old('faecher', collect($vorlage['faecher'] ?? [])->where('vorauswahl', true)->map(fn ($f) => StammdatenVorlage::fachSchluessel($f))->all());
        $gruppen = collect($vorlage['faecher'] ?? [])->groupBy(fn ($f) => $f['track'] ?? StammdatenVorlage::OHNE_TRACK);
        $gruppenTitel = ['BMS' => __('Berufsmaturität (BMS)'), 'ABU' => __('Allgemeinbildung (ABU)'), StammdatenVorlage::OHNE_TRACK => __('Berufsfachschule')];
        $baeume = collect($vorlage['lehrberufe'] ?? [])->pluck('notenbaum')->merge(collect($vorlage['notenbaeume'] ?? [])->pluck('vorlage'))
            ->filter()->unique()->map(fn ($k) => $baumNamen[$k] ?? null)->filter()->values();
        $feld = 'np-feld';
    @endphp

    @if(count($vorlagen) > 1)
        <form method="GET" action="{{ route('admin.setup', 'professions') }}" class="np-karte p-6 flex flex-col gap-3">
            <label for="vorlage-wahl" class="text-sm font-semibold text-text">{{ __('Vorlage') }}</label>
            <div class="flex flex-wrap items-center gap-2">
                <select id="vorlage-wahl" name="vorlage" data-sofort class="{{ $feld }} w-96 min-w-0 flex-none truncate pr-9">
                    @foreach($vorlagen as $schluessel => $v)
                        <option value="{{ $schluessel }}" @selected($schluessel === $vorlageSchluessel)>{{ $v['name'] }}</option>
                    @endforeach
                </select>
                <noscript><button type="submit" class="np-knopf np-knopf-sekundaer">{{ __('Übernehmen') }}</button></noscript>
            </div>
            @if(filled($vorlage['beschreibung'] ?? null))
                <p class="text-xs text-muted">{{ $vorlage['beschreibung'] }}</p>
            @endif
        </form>
    @endif

    <form method="POST" action="{{ route('admin.setup.professions') }}" class="flex flex-col gap-5"
          x-data="{ loading: false, eigene: {{ \Illuminate\Support\Js::from(old('eigene', [['kuerzel' => '', 'name' => '']])) }}, fehler: {{ \Illuminate\Support\Js::from(array_keys($errors->getMessages())) }},
                    f(i, feld) { return this.fehler.includes(`eigene.${i}.${feld}`); } }"
          @submit="if (!$event.defaultPrevented) loading = true">
        @csrf
        <input type="hidden" name="vorlage" value="{{ $vorlageSchluessel }}">
        <section class="np-karte p-6 flex flex-col gap-4">
            <h2 class="text-sm font-semibold text-text">{{ __('Lehrberufe') }}</h2>
            <div class="grid grid-cols-2 gap-2">
                @foreach($katalog as $l)
                    @php $da = isset($vorhandeneBerufe[$l['kuerzel']]); @endphp
                    <label @class(['flex items-center gap-3 rounded-xl border border-border px-3 py-2.5 min-h-11 transition-colors has-[:checked]:border-accent/50 has-[:checked]:bg-accent/5',
                        'cursor-pointer' => ! $da, 'opacity-60' => $da])>
                        <input type="checkbox" name="berufe[]" value="{{ $l['kuerzel'] }}" @checked($da || in_array($l['kuerzel'], $gewaehlt, true)) @disabled($da)
                               class="np-haken">
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm text-text wrap-break-word">{{ $l['name'] }}</span>
                            <span class="text-xs text-muted">{{ $l['kuerzel'] }}</span>
                        </span>
                        @if($da)<span class="text-xs text-muted">{{ __('vorhanden') }}</span>@endif
                    </label>
                @endforeach
                @foreach($weitere as $lb)
                    <div class="flex items-center gap-3 rounded-xl border border-border px-3 py-2.5 min-h-11 opacity-60">
                        <input type="checkbox" checked disabled aria-label="{{ $lb->name }}" class="np-haken">
                        <span class="min-w-0 flex-1"><span class="block text-sm text-text wrap-break-word">{{ $lb->name }}</span><span class="text-xs text-muted">{{ $lb->kuerzel }}</span></span>
                        <span class="text-xs text-muted">{{ __('vorhanden') }}</span>
                    </div>
                @endforeach
            </div>

            <div class="flex flex-col gap-2">
                <template x-for="(e, i) in eigene" :key="i">
                    <div class="flex gap-2">
                        <input :name="`eigene[${i}][kuerzel]`" x-model="e.kuerzel" maxlength="10" placeholder="{{ __('Kürzel') }}" aria-label="{{ __('Kürzel') }}" class="{{ $feld }} w-28 uppercase"
                               :aria-invalid="f(i, 'kuerzel') ? 'true' : null" :aria-describedby="f(i, 'kuerzel') ? 'professions-fehler' : null">
                        <input :name="`eigene[${i}][name]`" x-model="e.name" maxlength="200" placeholder="{{ __('Weiterer Lehrberuf') }}" aria-label="{{ __('Weiterer Lehrberuf') }}" class="{{ $feld }} flex-1 min-w-0"
                               :aria-invalid="f(i, 'name') ? 'true' : null" :aria-describedby="f(i, 'name') ? 'professions-fehler' : null">
                        <button type="button" @click="eigene.splice(i, 1)" x-show="eigene.length > 1" aria-label="{{ __('Zeile entfernen') }}"
                                class="np-knopf np-knopf-symbol np-knopf-symbol-gefahr shrink-0"><x-symbol name="x-mark" strich="2" class="size-4" /></button>
                    </div>
                </template>
                <button type="button" @click="eigene.push({ kuerzel: '', name: '' })" class="np-knopf np-knopf-schlicht self-start"><x-symbol name="plus" strich="2" class="size-4" />{{ __('Weiterer Lehrberuf') }}</button>
            </div>
        </section>

        <section class="np-karte p-6 flex flex-col gap-6">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-sm font-semibold text-text">{{ __('Fächer') }}</h2>
                <a href="{{ route('admin.master-data.subjects.index') }}" class="np-knopf np-knopf-schlicht">{{ __('Alle Fächer bearbeiten') }}</a>
            </div>
            <div class="flex flex-col gap-6">
                @foreach(['BMS', 'ABU', StammdatenVorlage::OHNE_TRACK] as $track)
                    @continue(! $gruppen->has($track))
                    <div>
                        <h4 class="text-xs font-medium text-muted mb-3">{{ $gruppenTitel[$track] }}</h4>
                        <div class="flex flex-wrap gap-2">
                            @foreach($gruppen[$track] as $f)
                                @php
                                    $schluessel = StammdatenVorlage::fachSchluessel($f);
                                    $db = $vorhandeneFaecher[$schluessel] ?? null;
                                    $da = $db !== null;
                                    // Vorhandene Fächer zeigen, was in der Datenbank steht, nicht die Angabe der Vorlage
                                    $skala = $da ? $db->skala : ($f['skala'] ?? 'note');
                                    $zaehlt = $da ? (bool) $db->zaehlt : ($f['zaehlt'] ?? true);
                                    $zusatz = array_filter([$skala === 'stufe' ? __('Stufe') : null, $zaehlt ? null : __('zählt nicht')]);
                                @endphp
                                <label @class(['inline-flex items-center gap-2 rounded-full border border-border px-3 py-1 min-h-9 text-sm text-text transition-colors has-[:checked]:border-accent/50 has-[:checked]:bg-accent/10',
                                    'cursor-pointer' => ! $da, 'opacity-60' => $da])>
                                    <input type="checkbox" name="faecher[]" value="{{ $schluessel }}" @checked($da || in_array($schluessel, $faecherGewaehlt, true)) @disabled($da)
                                           class="np-haken">
                                    <span class="min-w-0">{{ $f['name'] }}</span>
                                    @if($zusatz)<span class="shrink-0 whitespace-nowrap text-xs text-muted">{{ implode(' · ', $zusatz) }}</span>@endif
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        @if($baeume->isNotEmpty())
            <section class="np-karte p-6 flex flex-col gap-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-sm font-semibold text-text">{{ __('Notenbäume') }}</h2>
                    <a href="{{ route('admin.master-data.grade-trees.index') }}" class="np-knopf np-knopf-schlicht">{{ __('Notenbäume bearbeiten') }}</a>
                </div>
                <label class="flex items-start gap-3 cursor-pointer">
                    <input type="hidden" name="notenbaeume" value="0">
                    <input type="checkbox" name="notenbaeume" value="1" @checked(old('notenbaeume', '1') === '1') class="np-haken mt-0.5">
                    <span class="min-w-0">
                        <span class="block text-sm text-text">{{ __('Gewichtung bis zur Gesamtnote laden') }}</span>
                        <span class="block text-xs text-muted">{{ $baeume->implode(' · ') }}</span>
                    </span>
                </label>
            </section>
        @endif

        @if($errors->any())
            <ul id="professions-fehler" class="np-karte px-5 py-3 text-xs text-note-ungenuegend flex flex-col gap-1">
                @foreach(collect($errors->all())->unique() as $f)<li>{{ $f }}</li>@endforeach
            </ul>
        @endif

        @include('admin.einrichtung._fuss', ['schritt' => 'professions'])
    </form>
</x-einrichtung>
