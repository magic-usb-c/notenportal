<x-einrichtung schritt="people" :stand="$stand" :titel="__('Personen')">
    @php
        $feld = 'np-feld px-2 normal-case tracking-normal';
        $label = 'flex flex-col gap-1 text-sm font-medium text-text min-w-0';
        $knopf = 'np-knopf np-knopf-primaer np-knopf-gross';
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

    <form method="POST" action="{{ route('admin.setup.people') }}" class="np-karte p-6 flex flex-col gap-4 print:hidden"
          x-data="npZeilen({{ \Illuminate\Support\Js::from(old('personen', [])) }}, {{ \Illuminate\Support\Js::from($leerePerson) }}, {{ \Illuminate\Support\Js::from($fehlerKeys) }}, 'personen')"
          @submit="if (!$event.defaultPrevented) loading = true">
        @csrf
        <div class="flex items-baseline justify-between gap-3">
            <h3 class="text-sm font-semibold text-text">{{ __('Berufsbildner und Admins') }}</h3>
            <span class="text-xs text-muted">{{ $berufsbildner->count() }} {{ __('Berufsbildner') }}</span>
        </div>
        <template x-for="(z, i) in zeilen" :key="i">
            <div class="grid grid-cols-2 md:grid-cols-[1fr_1fr_1.5fr_9rem_2.5rem] gap-2 items-end">
                <label class="{{ $label }}">{{ __('Vorname') }}<input :name="`personen[${i}][vorname]`" x-model="z.vorname" required maxlength="100" autocomplete="off" class="{{ $feld }}" :class="f(i, 'vorname') && 'border-note-ungenuegend!'"></label>
                <label class="{{ $label }}">{{ __('Nachname') }}<input :name="`personen[${i}][nachname]`" x-model="z.nachname" required maxlength="100" autocomplete="off" class="{{ $feld }}" :class="f(i, 'nachname') && 'border-note-ungenuegend!'"></label>
                <label class="{{ $label }}">{{ __('E-Mail') }}<input type="email" :name="`personen[${i}][email]`" x-model="z.email" required maxlength="255" autocomplete="off" class="{{ $feld }}" :class="f(i, 'email') && 'border-note-ungenuegend!'"></label>
                <label class="{{ $label }}">{{ __('Rolle') }}
                    <select :name="`personen[${i}][rolle]`" x-model="z.rolle" class="{{ $feld }}">
                        <option value="Berufsbildner">{{ __('Berufsbildner') }}</option>
                        <option value="Admin">{{ __('Admin') }}</option>
                    </select>
                </label>
                <button type="button" @click="weg(i)" :class="zeilen.length > 1 ? '' : 'invisible'" aria-label="{{ __('Zeile entfernen') }}"
                        class="w-10 h-10 inline-flex items-center justify-center rounded-lg text-muted hover:text-note-ungenuegend hover:bg-note-ungenuegend/10">×</button>
            </div>
        </template>
        @if($meldungen('personen')->isNotEmpty())
            <ul class="text-xs text-note-ungenuegend flex flex-col gap-1">@foreach($meldungen('personen') as $m)<li>{{ $m }}</li>@endforeach</ul>
        @endif
        <div class="flex items-center justify-between gap-3">
            <button type="button" @click="neu()" class="np-knopf np-knopf-schlicht">+ {{ __('Weitere Person') }}</button>
            <button type="submit" :disabled="loading" class="{{ $knopf }}">{{ __('Konten anlegen') }}</button>
        </div>
    </form>

    @if($lehrberufe->isEmpty() || ! $semesterVorhanden)
        <section class="np-karte p-6 flex flex-wrap items-center justify-between gap-3 print:hidden">
            <h3 class="text-sm font-semibold text-text">{{ __('Lernende') }}</h3>
            <div class="flex gap-2">
                @unless($semesterVorhanden)
                    <a href="{{ route('admin.setup', 'semesters') }}" class="np-knopf np-knopf-sekundaer np-knopf-gross">{{ __('Semester anlegen') }}</a>
                @endunless
                @if($lehrberufe->isEmpty())
                    <a href="{{ route('admin.setup', 'professions') }}" class="np-knopf np-knopf-sekundaer np-knopf-gross">{{ __('Lehrberufe anlegen') }}</a>
                @endif
            </div>
        </section>
    @else
        <form method="POST" action="{{ route('admin.setup.learners') }}" class="np-karte p-6 flex flex-col gap-4 print:hidden"
              x-data="npZeilen({{ \Illuminate\Support\Js::from(old('lernende', [])) }}, {{ \Illuminate\Support\Js::from($leererLernender) }}, {{ \Illuminate\Support\Js::from($fehlerKeys) }}, 'lernende')"
              @submit="if (!$event.defaultPrevented) loading = true">
            @csrf
            <h3 class="text-sm font-semibold text-text">{{ __('Lernende') }}</h3>
            <template x-for="(z, i) in zeilen" :key="i">
                <div class="rounded-xl border border-border p-3 grid grid-cols-2 lg:grid-cols-4 gap-2 items-end">
                    <label class="{{ $label }}">{{ __('Vorname') }}<input :name="`lernende[${i}][vorname]`" x-model="z.vorname" required maxlength="100" autocomplete="off" class="{{ $feld }}" :class="f(i, 'vorname') && 'border-note-ungenuegend!'"></label>
                    <label class="{{ $label }}">{{ __('Nachname') }}<input :name="`lernende[${i}][nachname]`" x-model="z.nachname" required maxlength="100" autocomplete="off" class="{{ $feld }}" :class="f(i, 'nachname') && 'border-note-ungenuegend!'"></label>
                    <label class="{{ $label }} col-span-2">{{ __('E-Mail') }}<input type="email" :name="`lernende[${i}][email]`" x-model="z.email" required maxlength="255" autocomplete="off" class="{{ $feld }}" :class="f(i, 'email') && 'border-note-ungenuegend!'"></label>
                    <label class="{{ $label }} col-span-2 lg:col-span-1">{{ __('Lehrberuf') }}
                        <select :name="`lernende[${i}][lehrberuf_id]`" x-model="z.lehrberuf_id" class="{{ $feld }}">
                            @foreach($lehrberufe as $lb)<option value="{{ $lb->lehrberuf_id }}">{{ $lb->kuerzel }} · {{ $lb->name }}</option>@endforeach
                        </select>
                    </label>
                    <label class="{{ $label }}">{{ __('Lehrbeginn') }}<input type="date" :name="`lernende[${i}][lehrbeginn]`" x-model="z.lehrbeginn" @change="lehrende(z)" required class="{{ $feld }}" :class="f(i, 'lehrbeginn') && 'border-note-ungenuegend!'"></label>
                    <label class="{{ $label }}">{{ __('Lehrende') }}<input type="date" :name="`lernende[${i}][lehrende]`" x-model="z.lehrende" class="{{ $feld }}" :class="f(i, 'lehrende') && 'border-note-ungenuegend!'"></label>
                    <label class="{{ $label }}">{{ __('Berufsbildner') }}
                        <select :name="`lernende[${i}][berufsbildner_id]`" x-model="z.berufsbildner_id" class="{{ $feld }}">
                            <option value="">–</option>
                            @foreach($berufsbildner as $bb)<option value="{{ $bb->berufsbildner_id }}">{{ $bb->vorname }} {{ $bb->nachname }}</option>@endforeach
                        </select>
                    </label>
                    <div class="flex items-end gap-2 col-span-2 lg:col-span-1">
                        <label class="{{ $label }} flex-1">{{ __('Track') }}
                            <select :name="`lernende[${i}][track]`" x-model="z.track" class="{{ $feld }}">
                                <option value="">–</option>
                                <option value="BMS">BMS</option>
                                <option value="ABU">ABU</option>
                            </select>
                        </label>
                        <button type="button" @click="weg(i)" :class="zeilen.length > 1 ? '' : 'invisible'" aria-label="{{ __('Zeile entfernen') }}"
                                class="w-10 h-10 shrink-0 inline-flex items-center justify-center rounded-lg text-muted hover:text-note-ungenuegend hover:bg-note-ungenuegend/10">×</button>
                    </div>
                </div>
            </template>
            @if($meldungen('lernende')->isNotEmpty())
                <ul class="text-xs text-note-ungenuegend flex flex-col gap-1">@foreach($meldungen('lernende') as $m)<li>{{ $m }}</li>@endforeach</ul>
            @endif
            <div class="flex items-center justify-between gap-3">
                <button type="button" @click="neu()" class="np-knopf np-knopf-schlicht">+ {{ __('Weitere Lernende') }}</button>
                <button type="submit" :disabled="loading" class="{{ $knopf }}">{{ __('Lernende anlegen') }}</button>
            </div>
        </form>
    @endif

    <div class="print:hidden">
        @include('admin.einrichtung._fuss', ['schritt' => 'people', 'knopf' => false, 'weiterText' => __('Weiter')])
    </div>
</x-einrichtung>
