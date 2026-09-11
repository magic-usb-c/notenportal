<x-einrichtung schritt="people" :stand="$stand" titel="Personen">
    @php
        $feld = 'h-10 w-full rounded-lg border border-border bg-input text-text px-2 text-sm normal-case tracking-normal focus:ring-2 focus:ring-ring focus:border-ring';
        $label = 'flex flex-col gap-1 text-[11px] uppercase tracking-widest text-muted font-medium min-w-0';
        $knopf = 'inline-flex items-center px-5 h-10 rounded-xl bg-accent text-white text-sm font-semibold np-btn-primary disabled:opacity-60';
        $fehlerKeys = array_keys($errors->getMessages());
        $meldungen = fn (string $praefix) => collect($errors->getMessages())->filter(fn ($m, $k) => str_starts_with($k, $praefix))->flatten()->unique();
        $leerePerson = ['vorname' => '', 'nachname' => '', 'email' => '', 'rolle' => 'Berufsbildner'];
        $leererLernender = [
            'vorname' => '', 'nachname' => '', 'email' => '',
            'lehrberuf_id' => $lehrberufe->first()?->lehrberuf_id,
            'lehrbeginn' => $lehrbeginn,
            'lehrende' => \Illuminate\Support\Carbon::parse($lehrbeginn)->addYears(4)->subDay()->toDateString(),
            'berufsbildner_id' => $berufsbildner->count() === 1 ? $berufsbildner->first()->berufsbildner_id : '',
            'track' => 'ABU',
        ];
    @endphp
    <script>
        function npZeilen(start, leer, fehler, praefix) {
            return {
                loading: false,
                zeilen: start.length ? start : [{ ...leer }],
                neu() { this.zeilen.push({ ...leer }); },
                weg(i) { this.zeilen.splice(i, 1); },
                f(i, feld) { return fehler.includes(`${praefix}.${i}.${feld}`); },
                lehrende(z) {
                    if (!z.lehrbeginn) return;
                    const d = new Date(z.lehrbeginn + 'T00:00');
                    d.setFullYear(d.getFullYear() + 4);
                    d.setDate(d.getDate() - 1);
                    z.lehrende = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
                },
            };
        }
    </script>

    @include('admin.einrichtung._zugaenge')

    <form method="POST" action="{{ route('admin.setup.people') }}" class="glass rounded-2xl p-6 flex flex-col gap-4 print:hidden"
          x-data="npZeilen({{ \Illuminate\Support\Js::from(old('personen', [])) }}, {{ \Illuminate\Support\Js::from($leerePerson) }}, {{ \Illuminate\Support\Js::from($fehlerKeys) }}, 'personen')"
          @submit="if (!$event.defaultPrevented) loading = true">
        @csrf
        <div class="flex items-baseline justify-between gap-3">
            <h3 class="text-sm font-semibold text-text">Berufsbildner und Admins</h3>
            <span class="text-xs text-muted">{{ $berufsbildner->count() }} Berufsbildner</span>
        </div>
        <template x-for="(z, i) in zeilen" :key="i">
            <div class="grid grid-cols-2 md:grid-cols-[1fr_1fr_1.5fr_9rem_2.5rem] gap-2 items-end">
                <label class="{{ $label }}">Vorname<input :name="`personen[${i}][vorname]`" x-model="z.vorname" required maxlength="100" autocomplete="off" class="{{ $feld }}" :class="f(i, 'vorname') && 'border-red-500!'"></label>
                <label class="{{ $label }}">Nachname<input :name="`personen[${i}][nachname]`" x-model="z.nachname" required maxlength="100" autocomplete="off" class="{{ $feld }}" :class="f(i, 'nachname') && 'border-red-500!'"></label>
                <label class="{{ $label }}">E-Mail<input type="email" :name="`personen[${i}][email]`" x-model="z.email" required maxlength="255" autocomplete="off" class="{{ $feld }}" :class="f(i, 'email') && 'border-red-500!'"></label>
                <label class="{{ $label }}">Rolle
                    <select :name="`personen[${i}][rolle]`" x-model="z.rolle" class="{{ $feld }}">
                        <option value="Berufsbildner">Berufsbildner</option>
                        <option value="Admin">Admin</option>
                    </select>
                </label>
                <button type="button" @click="weg(i)" :class="zeilen.length > 1 ? '' : 'invisible'" aria-label="Zeile entfernen"
                        class="w-10 h-10 inline-flex items-center justify-center rounded-lg text-muted hover:text-red-600 dark:hover:text-red-400 hover:bg-red-500/10">×</button>
            </div>
        </template>
        @if($meldungen('personen')->isNotEmpty())
            <ul class="text-xs text-red-600 dark:text-red-400 flex flex-col gap-1">@foreach($meldungen('personen') as $m)<li>{{ $m }}</li>@endforeach</ul>
        @endif
        <div class="flex items-center justify-between gap-3">
            <button type="button" @click="neu()" class="inline-flex items-center px-3 min-h-9 rounded-lg text-sm text-accent hover:bg-accent/10">+ Weitere Person</button>
            <button type="submit" :disabled="loading" class="{{ $knopf }}">Konten anlegen</button>
        </div>
    </form>

    @if($lehrberufe->isEmpty() || ! $semesterVorhanden)
        <section class="glass rounded-2xl p-6 flex flex-wrap items-center justify-between gap-3 print:hidden">
            <h3 class="text-sm font-semibold text-text">Lernende</h3>
            <div class="flex gap-2">
                @unless($semesterVorhanden)
                    <a href="{{ route('admin.setup', 'semesters') }}" class="inline-flex items-center px-4 h-10 rounded-xl glass-btn text-text text-sm">Semester anlegen</a>
                @endunless
                @if($lehrberufe->isEmpty())
                    <a href="{{ route('admin.setup', 'professions') }}" class="inline-flex items-center px-4 h-10 rounded-xl glass-btn text-text text-sm">Lehrberufe anlegen</a>
                @endif
            </div>
        </section>
    @else
        <form method="POST" action="{{ route('admin.setup.learners') }}" class="glass rounded-2xl p-6 flex flex-col gap-4 print:hidden"
              x-data="npZeilen({{ \Illuminate\Support\Js::from(old('lernende', [])) }}, {{ \Illuminate\Support\Js::from($leererLernender) }}, {{ \Illuminate\Support\Js::from($fehlerKeys) }}, 'lernende')"
              @submit="if (!$event.defaultPrevented) loading = true">
            @csrf
            <h3 class="text-sm font-semibold text-text">Lernende</h3>
            <template x-for="(z, i) in zeilen" :key="i">
                <div class="rounded-xl border border-border p-3 grid grid-cols-2 lg:grid-cols-4 gap-2 items-end">
                    <label class="{{ $label }}">Vorname<input :name="`lernende[${i}][vorname]`" x-model="z.vorname" required maxlength="100" autocomplete="off" class="{{ $feld }}" :class="f(i, 'vorname') && 'border-red-500!'"></label>
                    <label class="{{ $label }}">Nachname<input :name="`lernende[${i}][nachname]`" x-model="z.nachname" required maxlength="100" autocomplete="off" class="{{ $feld }}" :class="f(i, 'nachname') && 'border-red-500!'"></label>
                    <label class="{{ $label }} col-span-2">E-Mail<input type="email" :name="`lernende[${i}][email]`" x-model="z.email" required maxlength="255" autocomplete="off" class="{{ $feld }}" :class="f(i, 'email') && 'border-red-500!'"></label>
                    <label class="{{ $label }} col-span-2 lg:col-span-1">Lehrberuf
                        <select :name="`lernende[${i}][lehrberuf_id]`" x-model="z.lehrberuf_id" class="{{ $feld }}">
                            @foreach($lehrberufe as $lb)<option value="{{ $lb->lehrberuf_id }}">{{ $lb->kuerzel }} · {{ $lb->name }}</option>@endforeach
                        </select>
                    </label>
                    <label class="{{ $label }}">Lehrbeginn<input type="date" :name="`lernende[${i}][lehrbeginn]`" x-model="z.lehrbeginn" @change="lehrende(z)" required class="{{ $feld }}" :class="f(i, 'lehrbeginn') && 'border-red-500!'"></label>
                    <label class="{{ $label }}">Lehrende<input type="date" :name="`lernende[${i}][lehrende]`" x-model="z.lehrende" class="{{ $feld }}" :class="f(i, 'lehrende') && 'border-red-500!'"></label>
                    <label class="{{ $label }}">Berufsbildner
                        <select :name="`lernende[${i}][berufsbildner_id]`" x-model="z.berufsbildner_id" class="{{ $feld }}">
                            <option value="">–</option>
                            @foreach($berufsbildner as $bb)<option value="{{ $bb->berufsbildner_id }}">{{ $bb->vorname }} {{ $bb->nachname }}</option>@endforeach
                        </select>
                    </label>
                    <div class="flex items-end gap-2 col-span-2 lg:col-span-1">
                        <label class="{{ $label }} flex-1">Track
                            <select :name="`lernende[${i}][track]`" x-model="z.track" class="{{ $feld }}">
                                <option value="">–</option>
                                <option value="BMS">BMS</option>
                                <option value="ABU">ABU</option>
                            </select>
                        </label>
                        <button type="button" @click="weg(i)" :class="zeilen.length > 1 ? '' : 'invisible'" aria-label="Zeile entfernen"
                                class="w-10 h-10 shrink-0 inline-flex items-center justify-center rounded-lg text-muted hover:text-red-600 dark:hover:text-red-400 hover:bg-red-500/10">×</button>
                    </div>
                </div>
            </template>
            @if($meldungen('lernende')->isNotEmpty())
                <ul class="text-xs text-red-600 dark:text-red-400 flex flex-col gap-1">@foreach($meldungen('lernende') as $m)<li>{{ $m }}</li>@endforeach</ul>
            @endif
            <div class="flex items-center justify-between gap-3">
                <button type="button" @click="neu()" class="inline-flex items-center px-3 min-h-9 rounded-lg text-sm text-accent hover:bg-accent/10">+ Weitere Lernende</button>
                <button type="submit" :disabled="loading" class="{{ $knopf }}">Lernende anlegen</button>
            </div>
        </form>
    @endif

    <div class="print:hidden">
        @include('admin.einrichtung._fuss', ['schritt' => 'people', 'knopf' => false, 'weiterText' => 'Weiter'])
    </div>
</x-einrichtung>
