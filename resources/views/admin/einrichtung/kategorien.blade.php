<x-einrichtung schritt="kategorien" :stand="$stand" titel="Kategorien">
    @php
        $feld = 'h-10 rounded-lg border border-border bg-input text-text px-2 text-sm focus:ring-2 focus:ring-ring focus:border-ring tabular-nums';
    @endphp
    <form method="POST" action="{{ route('admin.einrichtung.kategorien') }}" class="flex flex-col gap-5"
          x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
        @csrf
        <section class="glass rounded-2xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm text-text">
                    <thead class="text-xs text-muted">
                        <tr class="border-b border-border">
                            <th class="text-left px-4 py-3 font-medium">Kategorie</th>
                            <th class="px-2 py-3 font-medium">Aktiv</th>
                            <th class="px-2 py-3 font-medium whitespace-nowrap">Rundung Note</th>
                            <th class="px-2 py-3 font-medium whitespace-nowrap">Rundung Schnitt</th>
                            <th class="px-2 py-3 font-medium whitespace-nowrap">Gewicht Gesamt</th>
                            <th class="px-2 py-3 font-medium whitespace-nowrap">Promotion Ø min.</th>
                            <th class="px-2 py-3 font-medium whitespace-nowrap">max. ungenügend</th>
                            <th class="px-4 py-3 font-medium whitespace-nowrap">max. Minuspunkte</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach($kategorien as $k)
                            @php
                                $id = $k->kategorie_id;
                                $alt = fn (string $f) => old('kategorien.'.$id.'.'.$f, $k->$f);
                                $name = fn (string $f) => 'kategorien['.$id.']['.$f.']';
                            @endphp
                            <tr>
                                <td class="px-4 py-2">
                                    <label for="k{{ $id }}-name" class="sr-only">Name {{ $k->code }}</label>
                                    <input id="k{{ $id }}-name" name="{{ $name('name') }}" value="{{ $alt('name') }}" required maxlength="50" class="{{ $feld }} w-40">
                                </td>
                                <td class="px-2 py-2 text-center">
                                    <input type="hidden" name="{{ $name('aktiv') }}" value="0">
                                    <label class="inline-flex items-center justify-center min-w-9 min-h-9 cursor-pointer">
                                        <input type="checkbox" name="{{ $name('aktiv') }}" value="1" @checked($alt('aktiv')) aria-label="{{ $k->name }} aktiv"
                                               class="w-5 h-5 rounded border-border text-accent focus:ring-ring">
                                    </label>
                                </td>
                                @foreach(['rundung_element', 'rundung_schnitt'] as $f)
                                    <td class="px-2 py-2">
                                        <select name="{{ $name($f) }}" aria-label="{{ $k->name }} {{ $f === 'rundung_element' ? 'Rundung Note' : 'Rundung Schnitt' }}" class="{{ $feld }} w-24">
                                            @foreach(\App\Support\KategorieRegeln::RUNDUNGEN as $r)
                                                <option value="{{ $r }}" @selected(abs((float) $alt($f) - (float) $r) < 0.0001)>{{ $r }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                @endforeach
                                <td class="px-2 py-2"><input type="number" name="{{ $name('gewicht_gesamt') }}" value="{{ $alt('gewicht_gesamt') }}" min="0" step="0.1" required aria-label="{{ $k->name }} Gewicht" class="{{ $feld }} w-20"></td>
                                <td class="px-2 py-2"><input type="number" name="{{ $name('promotion_min_schnitt') }}" value="{{ $alt('promotion_min_schnitt') }}" min="1" max="6" step="0.1" placeholder="–" aria-label="{{ $k->name }} Promotion Ø min." class="{{ $feld }} w-20"></td>
                                <td class="px-2 py-2"><input type="number" name="{{ $name('promotion_max_ungenuegend') }}" value="{{ $alt('promotion_max_ungenuegend') }}" min="0" max="20" step="1" placeholder="–" aria-label="{{ $k->name }} max. ungenügend" class="{{ $feld }} w-20"></td>
                                <td class="px-4 py-2"><input type="number" name="{{ $name('promotion_max_minuspunkte') }}" value="{{ $alt('promotion_max_minuspunkte') }}" min="0" max="20" step="0.5" placeholder="–" aria-label="{{ $k->name }} max. Minuspunkte" class="{{ $feld }} w-20"></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($errors->any())
                <ul class="px-4 py-3 border-t border-border text-xs text-red-600 dark:text-red-400 flex flex-col gap-1">
                    @foreach(collect($errors->all())->unique() as $f)<li>{{ $f }}</li>@endforeach
                </ul>
            @endif
        </section>

        @include('admin.einrichtung._fuss', ['schritt' => 'kategorien'])
    </form>
</x-einrichtung>
