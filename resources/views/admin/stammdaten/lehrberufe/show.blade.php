<x-app-layout>
    <x-slot name="title">{{ __('Lehrberuf') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :zurueck="route('admin.master-data.professions.index')" :titel="$lehrberuf->name" :untertitel="$lehrberuf->kuerzel">
            <x-slot:aktionen>
                <a href="{{ route('admin.master-data.professions.edit', $lehrberuf->lehrberuf_id) }}" class="np-knopf np-knopf-sekundaer">{{ __('Bearbeiten') }}</a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-4 sm:px-6 lg:px-8 space-y-6">

            {{-- ========== MODULE ========== --}}
            <div class="np-karte p-5 space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="font-semibold text-text">{{ __('Module') }}</h3>
                    <span class="text-xs text-muted">{{ __(':anzahl zugewiesen', ['anzahl' => $zugewieseneModule->count()]) }}</span>
                </div>

                {{-- Zugewiesene Module: ab 48rem Container eine Tabelle, darunter je Modul eine Karte mit sichtbaren Feldnamen --}}
                @if($zugewieseneModule->isNotEmpty())
                    <div class="@container -mx-3 overflow-x-auto">
                        <table class="np-tabelle text-sm @max-3xl:block">
                            <thead class="@max-3xl:hidden">
                                <tr>
                                    <th>{{ __('Nummer') }}</th>
                                    <th>{{ __('Titel') }}</th>
                                    <th>{{ __('Lernort') }}</th>
                                    <th class="text-center">{{ __('Pflicht') }}</th>
                                    <th>{{ __('Semester') }}</th>
                                    <th class="text-center">{{ __('Aktiv') }}</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody class="@max-3xl:block">
                                @foreach($zugewieseneModule as $m)
                                    @php $formular = 'lbm-'.$m->modul_id; @endphp
                                    <tr @class(['opacity-60' => ! $m->aktiv, '@max-3xl:grid @max-3xl:grid-cols-3 @max-3xl:gap-x-3 @max-3xl:gap-y-2 @max-3xl:py-3'])>
                                        <td class="text-text @max-3xl:col-span-full @max-3xl:p-0 @max-3xl:text-xs @max-3xl:text-muted">{{ $m->modul_nummer }}</td>
                                        <td class="text-text @max-3xl:col-span-full @max-3xl:-mt-2 @max-3xl:p-0 @max-3xl:font-medium">{{ $m->titel }}</td>
                                        <td class="@max-3xl:col-span-full @max-3xl:p-0">
                                            <span class="hidden @max-3xl:mb-1 @max-3xl:block @max-3xl:text-2xs @max-3xl:font-medium @max-3xl:text-muted" aria-hidden="true">{{ __('Lernort') }}</span>
                                            <label for="lernort_{{ $m->modul_id }}" class="sr-only">{{ __('Lernort für :titel', ['titel' => $m->titel]) }}</label>
                                            <select id="lernort_{{ $m->modul_id }}" name="kategorie_id" form="{{ $formular }}"
                                                    onchange="this.form.dataset.sendet || (this.form.dataset.sendet = 1, this.form.requestSubmit())"
                                                    class="np-feld min-w-40 pl-2 pr-8 text-xs @max-3xl:w-full">
                                                @foreach($kategorien as $k)
                                                    <option value="{{ $k->kategorie_id }}" @selected($m->kategorie_id == $k->kategorie_id)>{{ $k->name }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="text-center @max-3xl:p-0 @max-3xl:text-left">
                                            <span class="hidden @max-3xl:mb-1 @max-3xl:block @max-3xl:text-2xs @max-3xl:font-medium @max-3xl:text-muted" aria-hidden="true">{{ __('Pflicht') }}</span>
                                            <input type="hidden" name="pflicht" value="0" form="{{ $formular }}">
                                            <label class="inline-flex items-center justify-center min-w-9 min-h-9 cursor-pointer"><input type="checkbox" name="pflicht" value="1" form="{{ $formular }}" @checked($m->pflicht) onchange="this.form.dataset.sendet || (this.form.dataset.sendet = 1, this.form.requestSubmit())"
                                                   aria-label="{{ __('Pflichtmodul :nummer', ['nummer' => $m->modul_nummer]) }}" class="np-haken"></label>
                                        </td>
                                        <td class="@max-3xl:p-0">
                                            <span class="hidden @max-3xl:mb-1 @max-3xl:block @max-3xl:text-2xs @max-3xl:font-medium @max-3xl:text-muted" aria-hidden="true">{{ __('Semester') }}</span>
                                            <input type="number" name="empfohlenes_lehrsemester_nr" min="1" max="12" value="{{ $m->empfohlenes_lehrsemester_nr }}" form="{{ $formular }}"
                                                   onchange="this.form.dataset.sendet || (this.form.dataset.sendet = 1, this.form.requestSubmit())" placeholder="–" aria-label="{{ __('Empfohlenes Semester :nummer', ['nummer' => $m->modul_nummer]) }}"
                                                   class="np-feld w-16 px-2 text-xs tabular-nums">
                                        </td>
                                        <td class="text-center @max-3xl:p-0 @max-3xl:text-left">
                                            <span class="hidden @max-3xl:mb-1 @max-3xl:block @max-3xl:text-2xs @max-3xl:font-medium @max-3xl:text-muted" aria-hidden="true">{{ __('Aktiv') }}</span>
                                            <input type="hidden" name="aktiv" value="0" form="{{ $formular }}">
                                            <label class="inline-flex items-center justify-center min-w-9 min-h-9 cursor-pointer"><input type="checkbox" name="aktiv" value="1" form="{{ $formular }}" @checked($m->aktiv) onchange="this.form.dataset.sendet || (this.form.dataset.sendet = 1, this.form.requestSubmit())"
                                                   aria-label="{{ __('Modul :nummer aktiv', ['nummer' => $m->modul_nummer]) }}" class="np-haken"></label>
                                        </td>
                                        <td class="text-right @max-3xl:col-span-full @max-3xl:-ml-3 @max-3xl:p-0 @max-3xl:text-left">
                                            <form id="{{ $formular }}" method="POST" class="hidden"
                                                  action="{{ route('admin.master-data.professions.modules.update', [$lehrberuf->lehrberuf_id, $m->modul_id]) }}">
                                                @csrf @method('PATCH')
                                            </form>
                                            <form method="POST"
                                                  action="{{ route('admin.master-data.professions.modules.remove', [$lehrberuf->lehrberuf_id, $m->modul_id]) }}"
                                                  data-bestaetigen="{{ __('Modul :nummer entfernen?', ['nummer' => $m->modul_nummer]) }}"
                                                  x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                                                @csrf @method('DELETE')
                                                <button :disabled="loading" class="np-knopf np-knopf-gefahr np-knopf-klein">{{ __('Entfernen') }}</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-sm text-muted">{{ __('Noch keine Module zugewiesen.') }}</p>
                @endif

                {{-- Modul zuweisen --}}
                @if($verfuegbareModule->isNotEmpty())
                    <form method="POST"
                          action="{{ route('admin.master-data.professions.modules.assign', $lehrberuf->lehrberuf_id) }}"
                          class="border-t border-border pt-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-3 items-end"
                          x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                        @csrf
                        <div class="lg:col-span-2">
                            <label for="modul_id" class="text-sm font-medium text-text">{{ __('Modul hinzufügen') }}</label>
                            <select id="modul_id" name="modul_id" required
                                    class="np-feld mt-1">
                                <option value="">{{ __('Bitte wählen…') }}</option>
                                @foreach($verfuegbareModule as $m)
                                    <option value="{{ $m->modul_id }}">{{ $m->modul_nummer }} – {{ $m->titel }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="kategorie_id" class="text-sm font-medium text-text">{{ __('Lernort *') }}</label>
                            <select id="kategorie_id" name="kategorie_id" required
                                    class="np-feld mt-1">
                                @foreach($kategorien as $k)
                                    <option value="{{ $k->kategorie_id }}" @selected($k->code === 'FACH')>{{ $k->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="empfohlenes_lehrsemester_nr" class="text-sm font-medium text-text">{{ __('Empfohlenes Semester') }}</label>
                            <input type="number" id="empfohlenes_lehrsemester_nr" name="empfohlenes_lehrsemester_nr" min="1" max="12"
                                   class="np-feld mt-1">
                        </div>
                        <div class="flex items-end gap-2">
                            <label class="flex items-center gap-2 text-sm text-text">
                                <input type="checkbox" name="pflicht" value="1" checked
                                       class="np-haken">
                                {{ __('Pflichtmodul') }}
                            </label>
                        </div>
                        <div>
                            <button type="submit" :disabled="loading"
                                    class="np-knopf np-knopf-primaer">
                                {{ __('Zuweisen') }}
                            </button>
                        </div>
                    </form>
                @endif
            </div>

            {{-- ========== FÄCHER ========== --}}
            <div class="np-karte p-5 space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="font-semibold text-text">{{ __('Fächer (berufsspezifisch)') }}</h3>
                    <span class="text-xs text-muted">{{ __(':anzahl zugewiesen', ['anzahl' => $zugewieseneFaecher->count()]) }}</span>
                </div>

                @if($zugewieseneFaecher->isNotEmpty())
                    <div class="@container -mx-3 overflow-x-auto">
                        <table class="np-tabelle text-sm">
                            <thead>
                                <tr>
                                    <th class="hidden @sm:table-cell">{{ __('Kürzel') }}</th>
                                    <th>{{ __('Name') }}</th>
                                    <th>{{ __('Track') }}</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($zugewieseneFaecher as $f)
                                    <tr>
                                        <td class="hidden text-text @sm:table-cell">{{ $f->kurzname }}</td>
                                        <td class="text-text">
                                            <span class="block tabular-nums text-xs text-muted @sm:hidden">{{ $f->kurzname }}</span>
                                            {{ $f->name }}
                                        </td>
                                        <td>
                                            @if($f->track_typ)
                                                <span class="np-marke bg-accent/12 text-accent-text">
                                                    {{ $f->track_typ }}
                                                </span>
                                            @else
                                                <span class="text-xs text-muted">–</span>
                                            @endif
                                        </td>
                                        <td class="text-right">
                                            <form method="POST"
                                                  action="{{ route('admin.master-data.professions.subjects.remove', [$lehrberuf->lehrberuf_id, $f->fach_id]) }}"
                                                  data-bestaetigen="{{ __('Fach :name entfernen?', ['name' => $f->name]) }}"
                                                  x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                                                @csrf @method('DELETE')
                                                <button :disabled="loading" class="np-knopf np-knopf-gefahr np-knopf-klein">{{ __('Entfernen') }}</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-sm text-muted">{{ __('Noch keine Fächer zugewiesen.') }}</p>
                @endif

                {{-- Fach zuweisen --}}
                @if($verfuegbareFaecher->isNotEmpty())
                    <form method="POST"
                          action="{{ route('admin.master-data.professions.subjects.assign', $lehrberuf->lehrberuf_id) }}"
                          class="border-t border-border pt-4 flex gap-3 items-end"
                          x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                        @csrf
                        <div class="flex-1">
                            <label for="fach_id" class="text-sm font-medium text-text">{{ __('Fach hinzufügen') }}</label>
                            <select id="fach_id" name="fach_id" required
                                    class="np-feld mt-1">
                                <option value="">{{ __('Bitte wählen…') }}</option>
                                @foreach($verfuegbareFaecher as $f)
                                    <option value="{{ $f->fach_id }}">{{ $f->kurzname }} – {{ $f->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" :disabled="loading"
                                class="np-knopf np-knopf-primaer">
                            {{ __('Zuweisen') }}
                        </button>
                    </form>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
