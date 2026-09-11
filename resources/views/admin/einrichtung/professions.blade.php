<x-einrichtung schritt="professions" :stand="$stand" titel="Lehrberufe & Fächer">
    @php
        $vorlagen = \App\Support\Einrichtung::LEHRBERUFE;
        $vorhandenKuerzel = $lehrberufe->pluck('kuerzel')->map(fn ($k) => strtoupper($k))->all();
        $vorhandenNamen = $lehrberufe->pluck('name')->all();
        $gewaehlt = old('berufe', $lehrberufe->isEmpty() ? \App\Support\Einrichtung::VORAUSWAHL_BERUFE : []);
        $weitere = $lehrberufe->reject(fn ($lb) => isset($vorlagen[strtoupper($lb->kuerzel)]) || in_array($lb->name, $vorlagen, true));
        $faecherDa = $faecher->map(fn ($f) => $f->track_typ.':'.$f->name)->all();
        $faecherGewaehlt = old('faecher', $faecher->isEmpty() ? \App\Support\Einrichtung::VORAUSWAHL_FAECHER : []);
        $feld = 'h-10 rounded-lg border border-border bg-input text-text px-3 text-sm focus:ring-2 focus:ring-ring focus:border-ring';
    @endphp
    <form method="POST" action="{{ route('admin.setup.professions') }}" class="flex flex-col gap-5"
          x-data="{ loading: false, eigene: {{ \Illuminate\Support\Js::from(old('eigene', [['kuerzel' => '', 'name' => '']])) }} }"
          @submit="if (!$event.defaultPrevented) loading = true">
        @csrf
        <section class="glass rounded-2xl p-6 flex flex-col gap-4">
            <h3 class="text-sm font-semibold text-text">Lehrberufe</h3>
            <div class="grid sm:grid-cols-2 gap-2">
                @foreach($vorlagen as $kuerzel => $name)
                    @php $da = in_array($kuerzel, $vorhandenKuerzel, true) || in_array($name, $vorhandenNamen, true); @endphp
                    <label @class(['flex items-center gap-3 rounded-xl border border-border px-3 py-2.5 min-h-11 transition-colors has-[:checked]:border-accent/50 has-[:checked]:bg-accent/5',
                        'cursor-pointer' => ! $da, 'opacity-60' => $da])>
                        <input type="checkbox" name="berufe[]" value="{{ $kuerzel }}" @checked($da || in_array($kuerzel, $gewaehlt, true)) @disabled($da)
                               class="w-5 h-5 rounded border-border text-accent focus:ring-ring">
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm text-text">{{ $name }}</span>
                            <span class="text-xs text-muted">{{ $kuerzel }}</span>
                        </span>
                        @if($da)<span class="text-xs text-muted">vorhanden</span>@endif
                    </label>
                @endforeach
                @foreach($weitere as $lb)
                    <div class="flex items-center gap-3 rounded-xl border border-border px-3 py-2.5 min-h-11 opacity-60">
                        <span class="w-5 h-5 rounded bg-accent/15" aria-hidden="true"></span>
                        <span class="min-w-0 flex-1"><span class="block text-sm text-text">{{ $lb->name }}</span><span class="text-xs text-muted">{{ $lb->kuerzel }}</span></span>
                        <span class="text-xs text-muted">vorhanden</span>
                    </div>
                @endforeach
            </div>

            <div class="flex flex-col gap-2">
                <template x-for="(e, i) in eigene" :key="i">
                    <div class="flex gap-2">
                        <input :name="`eigene[${i}][kuerzel]`" x-model="e.kuerzel" maxlength="10" placeholder="Kürzel" aria-label="Kürzel" class="{{ $feld }} w-28 uppercase">
                        <input :name="`eigene[${i}][name]`" x-model="e.name" maxlength="200" placeholder="Weiterer Lehrberuf" aria-label="Weiterer Lehrberuf" class="{{ $feld }} flex-1 min-w-0">
                        <button type="button" @click="eigene.splice(i, 1)" x-show="eigene.length > 1" aria-label="Zeile entfernen"
                                class="w-10 h-10 shrink-0 inline-flex items-center justify-center rounded-lg text-muted hover:text-red-600 dark:hover:text-red-400 hover:bg-red-500/10">×</button>
                    </div>
                </template>
                <button type="button" @click="eigene.push({ kuerzel: '', name: '' })" class="self-start inline-flex items-center px-3 min-h-9 rounded-lg text-sm text-accent hover:bg-accent/10">+ Weiterer Lehrberuf</button>
            </div>
        </section>

        <section class="glass rounded-2xl p-6 grid md:grid-cols-2 gap-6">
            @foreach(\App\Support\Einrichtung::FAECHER as $track => $liste)
                <div>
                    <h3 class="text-sm font-semibold text-text mb-3">Fächer {{ $track }}</h3>
                    <div class="flex flex-wrap gap-2">
                        @foreach($liste as $kurz => $name)
                            @php
                                $schluessel = $track.':'.$kurz;
                                $da = in_array($track.':'.$name, $faecherDa, true);
                            @endphp
                            <label @class(['inline-flex items-center gap-2 rounded-full border border-border px-3 min-h-9 text-sm text-text transition-colors has-[:checked]:border-accent/50 has-[:checked]:bg-accent/10',
                                'cursor-pointer' => ! $da, 'opacity-60' => $da])>
                                <input type="checkbox" name="faecher[]" value="{{ $schluessel }}" @checked($da || in_array($schluessel, $faecherGewaehlt, true)) @disabled($da)
                                       class="w-4 h-4 rounded border-border text-accent focus:ring-ring">
                                {{ $name }}
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </section>

        @if($errors->any())
            <ul class="glass rounded-2xl px-5 py-3 text-xs text-red-600 dark:text-red-400 flex flex-col gap-1">
                @foreach(collect($errors->all())->unique() as $f)<li>{{ $f }}</li>@endforeach
            </ul>
        @endif

        @include('admin.einrichtung._fuss', ['schritt' => 'professions'])
    </form>
</x-einrichtung>
