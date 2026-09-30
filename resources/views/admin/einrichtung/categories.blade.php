<x-einrichtung schritt="categories" :stand="$stand" :titel="__('Kategorien')">
    @php
        $feld = 'np-feld px-2 tabular-nums @max-5xl:w-full';
        // Ab 64rem Container eine Tabelle, darunter je Kategorie eine Karte mit sichtbaren Feldnamen
        $feldname = 'hidden @max-5xl:mb-1 @max-5xl:block @max-5xl:text-2xs @max-5xl:font-medium @max-5xl:text-muted';
    @endphp
    <form method="POST" action="{{ route('admin.setup.categories') }}" class="flex flex-col gap-5"
          x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
        @csrf
        <section class="np-karte overflow-hidden">
            <div class="@container overflow-x-auto p-2">
                <table class="np-tabelle text-sm @max-5xl:block">
                    <thead class="@max-5xl:hidden">
                        <tr>
                            <th>{{ __('Kategorie') }}</th>
                            <th>{{ __('Aktiv') }}</th>
                            <th class="whitespace-nowrap">{{ __('Rundung Note') }}</th>
                            <th class="whitespace-nowrap">{{ __('Rundung Schnitt') }}</th>
                            <th class="whitespace-nowrap">{{ __('Gewicht Gesamt') }}</th>
                            <th class="whitespace-nowrap">{{ __('Promotion Ø min.') }}</th>
                            <th class="whitespace-nowrap">{{ __('max. ungenügend') }}</th>
                            <th class="whitespace-nowrap">{{ __('max. Minuspunkte') }}</th>
                        </tr>
                    </thead>
                    <tbody class="@max-5xl:block">
                        @foreach($kategorien as $k)
                            @php
                                $id = $k->kategorie_id;
                                $alt = fn (string $f) => old('kategorien.'.$id.'.'.$f, $k->$f);
                                $name = fn (string $f) => 'kategorien['.$id.']['.$f.']';
                            @endphp
                            <tr class="@max-5xl:grid @max-5xl:grid-cols-2 @max-5xl:gap-x-3 @max-5xl:gap-y-3 @max-5xl:p-4 @md:@max-5xl:grid-cols-3">
                                <td class="@max-5xl:col-span-full @max-5xl:p-0 @md:@max-5xl:col-span-2">
                                    <label for="k{{ $id }}-name" class="sr-only @max-5xl:not-sr-only @max-5xl:mb-1 @max-5xl:block @max-5xl:text-2xs @max-5xl:font-medium @max-5xl:text-muted">{{ __('Name') }}<span class="sr-only"> {{ $k->code }}</span></label>
                                    <input id="k{{ $id }}-name" name="{{ $name('name') }}" value="{{ $alt('name') }}" required maxlength="50" class="{{ $feld }} w-40">
                                </td>
                                <td class="text-center @max-5xl:p-0 @max-5xl:text-left">
                                    <span class="{{ $feldname }}" aria-hidden="true">{{ __('Aktiv') }}</span>
                                    <input type="hidden" name="{{ $name('aktiv') }}" value="0">
                                    <label class="inline-flex items-center justify-center min-w-9 min-h-9 cursor-pointer">
                                        <input type="checkbox" name="{{ $name('aktiv') }}" value="1" @checked($alt('aktiv')) aria-label="{{ $k->name }} {{ __('aktiv') }}"
                                               class="np-haken">
                                    </label>
                                </td>
                                @foreach(['rundung_element', 'rundung_schnitt'] as $f)
                                    <td class="@max-5xl:p-0">
                                        <span class="{{ $feldname }}" aria-hidden="true">{{ $f === 'rundung_element' ? __('Rundung Note') : __('Rundung Schnitt') }}</span>
                                        <select name="{{ $name($f) }}" aria-label="{{ $k->name }} {{ $f === 'rundung_element' ? __('Rundung Note') : __('Rundung Schnitt') }}" class="{{ $feld }} w-24">
                                            @foreach(\App\Support\KategorieRegeln::RUNDUNGEN as $r)
                                                <option value="{{ $r }}" @selected(abs((float) $alt($f) - (float) $r) < 0.0001)>{{ $r }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                @endforeach
                                <td class="@max-5xl:p-0"><span class="{{ $feldname }}" aria-hidden="true">{{ __('Gewicht Gesamt') }}</span><input type="number" name="{{ $name('gewicht_gesamt') }}" value="{{ $alt('gewicht_gesamt') }}" min="0" step="0.1" required aria-label="{{ $k->name }} {{ __('Gewicht Gesamt') }}" class="{{ $feld }} w-20"></td>
                                <td class="@max-5xl:p-0"><span class="{{ $feldname }}" aria-hidden="true">{{ __('Promotion Ø min.') }}</span><input type="number" name="{{ $name('promotion_min_schnitt') }}" value="{{ $alt('promotion_min_schnitt') }}" min="1" max="6" step="0.1" placeholder="–" aria-label="{{ $k->name }} {{ __('Promotion Ø min.') }}" class="{{ $feld }} w-20"></td>
                                <td class="@max-5xl:p-0"><span class="{{ $feldname }}" aria-hidden="true">{{ __('max. ungenügend') }}</span><input type="number" name="{{ $name('promotion_max_ungenuegend') }}" value="{{ $alt('promotion_max_ungenuegend') }}" min="0" max="20" step="1" placeholder="–" aria-label="{{ $k->name }} {{ __('max. ungenügend') }}" class="{{ $feld }} w-20"></td>
                                <td class="@max-5xl:p-0"><span class="{{ $feldname }}" aria-hidden="true">{{ __('max. Minuspunkte') }}</span><input type="number" name="{{ $name('promotion_max_minuspunkte') }}" value="{{ $alt('promotion_max_minuspunkte') }}" min="0" max="20" step="0.5" placeholder="–" aria-label="{{ $k->name }} {{ __('max. Minuspunkte') }}" class="{{ $feld }} w-20"></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($errors->any())
                <ul class="px-4 py-3 border-t border-border text-xs text-note-ungenuegend flex flex-col gap-1">
                    @foreach(collect($errors->all())->unique() as $f)<li>{{ $f }}</li>@endforeach
                </ul>
            @endif
        </section>

        @include('admin.einrichtung._fuss', ['schritt' => 'categories'])
    </form>
</x-einrichtung>
