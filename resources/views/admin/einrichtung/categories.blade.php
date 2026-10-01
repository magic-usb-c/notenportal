<x-einrichtung schritt="categories" :stand="$stand" :titel="__('Kategorien')">
    @php
        $label = 'text-sm font-medium text-text';
        $fehler = 'mt-1 text-xs text-note-ungenuegend';
        $zahlen = [
            'gewicht_gesamt' => [__('Gewicht Gesamt'), 'min="0" step="0.1" required'],
            'promotion_min_schnitt' => [__('Promotion Ø min.'), 'min="1" max="6" step="0.1" placeholder="–"'],
            'promotion_max_ungenuegend' => [__('max. ungenügend'), 'min="0" max="20" step="1" placeholder="–"'],
            'promotion_max_minuspunkte' => [__('max. Minuspunkte'), 'min="0" max="20" step="0.5" placeholder="–"'],
        ];
    @endphp
    {{-- Je Kategorie eine Gruppe wie in den Systemeinstellungen: Name und Aktiv oben, darunter Rundung,
         Gewicht und die Promotionsregeln mit sichtbaren Feldnamen und Fehlern beim Feld. --}}
    <form method="POST" action="{{ route('admin.setup.categories') }}" class="flex flex-col gap-5"
          x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
        @csrf
        @error('kategorien')<p class="text-sm text-note-ungenuegend">{{ $message }}</p>@enderror

        @foreach($kategorien as $k)
            @php
                $id = $k->kategorie_id;
                $alt = fn (string $f) => old('kategorien.'.$id.'.'.$f, $k->$f);
                $name = fn (string $f) => 'kategorien['.$id.']['.$f.']';
                $schluessel = fn (string $f) => 'kategorien.'.$id.'.'.$f;
                $fehlerAttr = fn (string $f) => $errors->has('kategorien.'.$id.'.'.$f) ? 'aria-invalid="true" aria-describedby="k'.$id.'-'.$f.'-fehler"' : '';
            @endphp
            <fieldset class="np-karte grid grid-cols-3 gap-x-4 gap-y-4 p-5">
                <legend class="sr-only">{{ $k->name }} ({{ $k->code }})</legend>

                <div class="col-span-2">
                    <label for="k{{ $id }}-name" class="{{ $label }}">{{ __('Name') }} <span class="text-note-ungenuegend">*</span></label>
                    <input id="k{{ $id }}-name" name="{{ $name('name') }}" value="{{ $alt('name') }}" required maxlength="50" class="np-feld mt-1.5" {!! $fehlerAttr('name') !!}>
                    @error($schluessel('name'))<p id="k{{ $id }}-name-fehler" class="{{ $fehler }}">{{ $message }}</p>@enderror
                </div>
                <div class="flex items-end pb-2">
                    <input type="hidden" name="{{ $name('aktiv') }}" value="0">
                    <label class="inline-flex min-h-9 cursor-pointer items-center gap-2.5 text-sm text-text">
                        <input type="checkbox" name="{{ $name('aktiv') }}" value="1" @checked($alt('aktiv')) class="np-haken">
                        {{ __('Aktiv') }}
                    </label>
                </div>

                @foreach(['rundung_element' => __('Rundung Note'), 'rundung_schnitt' => __('Rundung Schnitt')] as $f => $text)
                    <div>
                        <label for="k{{ $id }}-{{ $f }}" class="{{ $label }}">{{ $text }}</label>
                        <select id="k{{ $id }}-{{ $f }}" name="{{ $name($f) }}" class="np-feld mt-1.5 tabular-nums" {!! $fehlerAttr($f) !!}>
                            @foreach(\App\Support\KategorieRegeln::RUNDUNGEN as $r)
                                <option value="{{ $r }}" @selected(abs((float) $alt($f) - (float) $r) < 0.0001)>{{ $r }}</option>
                            @endforeach
                        </select>
                        @error($schluessel($f))<p id="k{{ $id }}-{{ $f }}-fehler" class="{{ $fehler }}">{{ $message }}</p>@enderror
                    </div>
                @endforeach

                @foreach($zahlen as $f => [$text, $grenzen])
                    <div>
                        <label for="k{{ $id }}-{{ $f }}" class="{{ $label }}">{{ $text }}@if($f === 'gewicht_gesamt') <span class="text-note-ungenuegend">*</span>@endif</label>
                        <input type="number" id="k{{ $id }}-{{ $f }}" name="{{ $name($f) }}" value="{{ $alt($f) }}" {!! $grenzen !!} class="np-feld mt-1.5 tabular-nums" {!! $fehlerAttr($f) !!}>
                        @error($schluessel($f))<p id="k{{ $id }}-{{ $f }}-fehler" class="{{ $fehler }}">{{ $message }}</p>@enderror
                    </div>
                @endforeach
            </fieldset>
        @endforeach

        @include('admin.einrichtung._fuss', ['schritt' => 'categories'])
    </form>
</x-einrichtung>
