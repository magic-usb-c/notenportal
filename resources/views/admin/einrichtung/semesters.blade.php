<x-einrichtung schritt="semesters" :stand="$stand" :titel="__('Semester')">
    @php
        $feld = 'np-feld mt-1 tabular-nums';
        $label = 'text-sm font-medium text-text';
        $start = ['herbst' => old('herbst', $vorschlag['herbst']), 'fruehling' => old('fruehling', $vorschlag['fruehling']), 'bis' => (int) old('bis_jahr', $vorschlag['bis'])];
    @endphp
    <script>
        function npSemesterPlan(start, vorhanden, labels) {
            const iso = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
            return {
                ...start,
                labels,
                loading: false,
                get plan() {
                    if (!this.herbst || !this.fruehling) return [];
                    const h = new Date(this.herbst + 'T00:00');
                    const f = new Date(this.fruehling + 'T00:00');
                    const out = [];
                    for (let j = h.getFullYear(); j <= Math.min(this.bis, h.getFullYear() + 15); j++) {
                        const kurz = `${String(j % 100).padStart(2, '0')}/${String((j + 1) % 100).padStart(2, '0')}`;
                        const hs = new Date(j, h.getMonth(), h.getDate());
                        const fs = new Date(j + 1, f.getMonth(), f.getDate());
                        const nh = new Date(j + 1, h.getMonth(), h.getDate());
                        const vor = (d) => { const x = new Date(d); x.setDate(x.getDate() - 1); return x; };
                        out.push({ b: `${kurz}-1`, von: hs, bis: vor(fs) }, { b: `${kurz}-2`, von: fs, bis: vor(nh) });
                    }
                    return out.map((s) => ({ ...s, da: vorhanden.includes(s.b) }));
                },
                get neu() { return this.plan.filter((s) => !s.da).length; },
                fmt(d) { return d.toLocaleDateString('de-CH', { day: '2-digit', month: '2-digit', year: 'numeric' }); },
                iso,
            };
        }
    </script>

    <form method="POST" action="{{ route('admin.setup.semesters') }}" class="flex flex-col gap-5"
          x-data="npSemesterPlan(@js($start), @js($semester->pluck('bezeichnung')), @js(['neu' => __('neu'), 'vorhanden' => __('vorhanden')]))" @submit="if (!$event.defaultPrevented) loading = true">
        @csrf
        <section class="np-karte p-6 flex flex-col gap-5">
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label for="herbst" class="{{ $label }}">{{ __('Erstes Herbstsemester ab') }}</label>
                    <input id="herbst" name="herbst" type="date" required x-model="herbst" class="{{ $feld }}"
                           @error('herbst') aria-invalid="true" aria-describedby="herbst-fehler" @enderror>
                    @error('herbst')<p id="herbst-fehler" class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="fruehling" class="{{ $label }}">{{ __('Erstes Frühlingssemester ab') }}</label>
                    <input id="fruehling" name="fruehling" type="date" required x-model="fruehling" class="{{ $feld }}"
                           @error('fruehling') aria-invalid="true" aria-describedby="fruehling-fehler" @enderror>
                    @error('fruehling')<p id="fruehling-fehler" class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="bis_jahr" class="{{ $label }}">{{ __('Bis Schuljahr') }}</label>
                    <input id="bis_jahr" name="bis_jahr" type="number" required min="2000" max="2100" x-model.number="bis" class="{{ $feld }}"
                           @error('bis_jahr') aria-invalid="true" aria-describedby="bis_jahr-fehler" @enderror>
                    @error('bis_jahr')<p id="bis_jahr-fehler" class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <div class="flex items-baseline justify-between gap-3 mb-2">
                    <h3 class="text-sm font-semibold text-text">{{ __('Vorschau') }}</h3>
                    <span class="text-xs text-muted" x-text="`${neu} ${labels.neu} · ${plan.length - neu} ${labels.vorhanden}`"></span>
                </div>
                <ul class="grid grid-cols-4 gap-2">
                    <template x-for="s in plan" :key="s.b">
                        <li class="rounded-xl border px-3 py-2" :class="s.da ? 'border-border text-muted' : 'border-accent/40 bg-accent/5 text-text'">
                            <div class="text-sm font-semibold tabular-nums" x-text="s.b"></div>
                            <div class="text-2xs text-muted tabular-nums"><span class="whitespace-nowrap" x-text="`${fmt(s.von)} –`"></span> <span class="whitespace-nowrap" x-text="fmt(s.bis)"></span></div>
                        </li>
                    </template>
                </ul>
            </div>
        </section>

        @if($semester->isNotEmpty())
            <section class="np-karte p-5">
                <h3 class="text-sm font-semibold text-text mb-3">{{ __('Vorhanden') }}</h3>
                <div class="flex flex-wrap gap-1.5">
                    @foreach($semester as $s)
                        <span class="px-2.5 py-1 rounded-lg bg-bg/60 border border-border text-xs tabular-nums" title="{{ \Illuminate\Support\Carbon::parse($s->start_datum)->format('d.m.Y') }} – {{ \Illuminate\Support\Carbon::parse($s->end_datum)->format('d.m.Y') }}">{{ $s->bezeichnung }} <span class="text-muted">({{ \App\Models\Semester::neutralerName($s->start_datum) }})</span></span>
                    @endforeach
                </div>
            </section>
        @endif

        @include('admin.einrichtung._fuss', ['schritt' => 'semesters', 'knopf' => __('Semester anlegen')])
    </form>
</x-einrichtung>
