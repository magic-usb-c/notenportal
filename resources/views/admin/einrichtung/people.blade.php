<x-einrichtung schritt="people" :stand="$stand" :titel="__('Personen')">
    @php
        $feld = 'np-feld px-2 normal-case tracking-normal';
        $entfernen = 'np-knopf np-knopf-symbol np-knopf-symbol-gefahr shrink-0';
        $rollen = ['Berufsbildner' => __('Berufsbildner'), 'Admin' => __('Admin')];
        $berufe = $lehrberufe->mapWithKeys(fn ($lb) => [$lb->lehrberuf_id => $lb->kuerzel.' · '.$lb->name])->all();
        $betreuer = ['' => '–'] + $berufsbildner->mapWithKeys(fn ($bb) => [$bb->berufsbildner_id => $bb->vorname.' '.$bb->nachname])->all();
        $tracks = ['' => '–', 'BMS' => 'BMS', 'ABU' => 'ABU'];
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
            <h2 class="text-sm font-semibold text-text">{{ __('Berufsbildner und Admins') }}</h2>
            <span class="text-xs text-muted">{{ $berufsbildner->count() }} {{ __('Berufsbildner') }}</span>
        </div>
        <template x-for="(z, i) in zeilen" :key="i">
            <div class="grid grid-cols-[1fr_1fr_1.5fr_9rem_2.5rem] gap-2 items-end">
                @include('admin.einrichtung._zeilenfeld', ['gruppe' => 'personen', 'schluessel' => 'vorname', 'text' => __('Vorname'), 'klasse' => $feld, 'attr' => 'required maxlength="100" autocomplete="off"'])
                @include('admin.einrichtung._zeilenfeld', ['gruppe' => 'personen', 'schluessel' => 'nachname', 'text' => __('Nachname'), 'klasse' => $feld, 'attr' => 'required maxlength="100" autocomplete="off"'])
                @include('admin.einrichtung._zeilenfeld', ['gruppe' => 'personen', 'schluessel' => 'email', 'art' => 'email', 'text' => __('E-Mail'), 'klasse' => $feld, 'attr' => 'required maxlength="255" autocomplete="off"'])
                @include('admin.einrichtung._zeilenfeld', ['gruppe' => 'personen', 'schluessel' => 'rolle', 'art' => 'select', 'text' => __('Rolle'), 'klasse' => $feld, 'optionen' => $rollen])
                <button type="button" @click="weg(i)" :class="zeilen.length > 1 ? '' : 'invisible'" aria-label="{{ __('Zeile entfernen') }}"
                        class="{{ $entfernen }}"><x-symbol name="x-mark" strich="2" class="size-4" /></button>
            </div>
        </template>
        @if($meldungen('personen')->isNotEmpty())
            <ul id="personen-fehler" class="text-xs text-note-ungenuegend flex flex-col gap-1">@foreach($meldungen('personen') as $m)<li>{{ $m }}</li>@endforeach</ul>
        @endif
        <div class="flex items-center justify-between gap-3">
            <button type="button" @click="neu()" class="np-knopf np-knopf-schlicht"><x-symbol name="plus" strich="2" class="size-4" />{{ __('Weitere Person') }}</button>
            <button type="submit" :disabled="loading" class="np-knopf np-knopf-sekundaer">{{ __('Konten anlegen') }}</button>
        </div>
    </form>

    @if($lehrberufe->isEmpty() || ! $semesterVorhanden)
        <section class="np-karte p-6 flex flex-wrap items-center justify-between gap-3 print:hidden">
            <h2 class="text-sm font-semibold text-text">{{ __('Lernende') }}</h2>
            <div class="flex gap-2">
                @unless($semesterVorhanden)
                    <a href="{{ route('admin.setup', 'semesters') }}" class="np-knopf np-knopf-sekundaer">{{ __('Semester anlegen') }}</a>
                @endunless
                @if($lehrberufe->isEmpty())
                    <a href="{{ route('admin.setup', 'professions') }}" class="np-knopf np-knopf-sekundaer">{{ __('Lehrberufe anlegen') }}</a>
                @endif
            </div>
        </section>
    @else
        <form method="POST" action="{{ route('admin.setup.learners') }}" class="np-karte p-6 flex flex-col gap-4 print:hidden"
              x-data="npZeilen({{ \Illuminate\Support\Js::from(old('lernende', [])) }}, {{ \Illuminate\Support\Js::from($leererLernender) }}, {{ \Illuminate\Support\Js::from($fehlerKeys) }}, 'lernende')"
              @submit="if (!$event.defaultPrevented) loading = true">
            @csrf
            <h2 class="text-sm font-semibold text-text">{{ __('Lernende') }}</h2>
            <template x-for="(z, i) in zeilen" :key="i">
                <div class="rounded-xl border border-border p-3 grid grid-cols-4 gap-2 items-end">
                    @include('admin.einrichtung._zeilenfeld', ['gruppe' => 'lernende', 'schluessel' => 'vorname', 'text' => __('Vorname'), 'klasse' => $feld, 'attr' => 'required maxlength="100" autocomplete="off"'])
                    @include('admin.einrichtung._zeilenfeld', ['gruppe' => 'lernende', 'schluessel' => 'nachname', 'text' => __('Nachname'), 'klasse' => $feld, 'attr' => 'required maxlength="100" autocomplete="off"'])
                    @include('admin.einrichtung._zeilenfeld', ['gruppe' => 'lernende', 'schluessel' => 'email', 'art' => 'email', 'text' => __('E-Mail'), 'klasse' => $feld, 'spanne' => 'col-span-2', 'attr' => 'required maxlength="255" autocomplete="off"'])
                    @include('admin.einrichtung._zeilenfeld', ['gruppe' => 'lernende', 'schluessel' => 'lehrberuf_id', 'art' => 'select', 'text' => __('Lehrberuf'), 'klasse' => $feld, 'spanne' => 'col-span-2', 'optionen' => $berufe])
                    @include('admin.einrichtung._zeilenfeld', ['gruppe' => 'lernende', 'schluessel' => 'lehrbeginn', 'art' => 'date', 'text' => __('Lehrbeginn'), 'klasse' => $feld, 'attr' => '@change="lehrende(z)" required'])
                    @include('admin.einrichtung._zeilenfeld', ['gruppe' => 'lernende', 'schluessel' => 'lehrende', 'art' => 'date', 'text' => __('Ende der Lehre'), 'klasse' => $feld])
                    @include('admin.einrichtung._zeilenfeld', ['gruppe' => 'lernende', 'schluessel' => 'berufsbildner_id', 'art' => 'select', 'text' => __('Berufsbildner'), 'klasse' => $feld, 'spanne' => 'col-span-2', 'optionen' => $betreuer])
                    @include('admin.einrichtung._zeilenfeld', ['gruppe' => 'lernende', 'schluessel' => 'track', 'art' => 'select', 'text' => __('Track'), 'klasse' => $feld, 'optionen' => $tracks])
                    <div class="flex justify-end">
                        <button type="button" @click="weg(i)" :class="zeilen.length > 1 ? '' : 'invisible'" aria-label="{{ __('Zeile entfernen') }}"
                                class="{{ $entfernen }}"><x-symbol name="x-mark" strich="2" class="size-4" /></button>
                    </div>
                </div>
            </template>
            @if($meldungen('lernende')->isNotEmpty())
                <ul id="lernende-fehler" class="text-xs text-note-ungenuegend flex flex-col gap-1">@foreach($meldungen('lernende') as $m)<li>{{ $m }}</li>@endforeach</ul>
            @endif
            <div class="flex items-center justify-between gap-3">
                <button type="button" @click="neu()" class="np-knopf np-knopf-schlicht"><x-symbol name="plus" strich="2" class="size-4" />{{ __('Weitere Lernende') }}</button>
                <button type="submit" :disabled="loading" class="np-knopf np-knopf-sekundaer">{{ __('Lernende anlegen') }}</button>
            </div>
        </form>
    @endif

    <div class="print:hidden">
        @include('admin.einrichtung._fuss', ['schritt' => 'people', 'knopf' => false, 'weiterText' => __('Weiter')])
    </div>
</x-einrichtung>
