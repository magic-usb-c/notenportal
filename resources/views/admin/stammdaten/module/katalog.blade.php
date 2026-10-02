{{-- Modulkatalog einlesen in zwei Schritten wie ein Import in macOS: Datei und Optionen wählen, dann die Vorschau
     prüfen und übernehmen oder verwerfen. Darunter der Export für eine zweite Installation. --}}
@php
    $gruppenTitel = 'mb-2 px-1 text-sm font-semibold text-text';
    $fussnote = 'mt-2 px-1 text-xs text-muted';
@endphp
<x-app-layout>
    <x-slot name="title">{{ __('Modulkatalog einlesen') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :zurueck="route('admin.master-data.modules.index')" :titel="__('Modulkatalog einlesen')" schmal />
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-8">
            <div class="np-spalte flex flex-col gap-8">

                @if(! $bereit)
                    <div class="np-karte">
                        <x-leer symbol="exclamation-triangle" :titel="__('Noch nicht bereit')"
                                :text="__('Der Katalog ist auf diesem Server noch nicht eingerichtet.')" />
                    </div>
                @elseif($vorschau === null)
                    <form method="POST" action="{{ route('admin.master-data.modules.catalog.read') }}" enctype="multipart/form-data"
                          class="flex flex-col gap-8" x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                        @csrf

                        <x-ablagezone accept=".json,application/json" required
                                      :titel="__('Katalogdatei wählen oder hierher ziehen')"
                                      :hinweis="__('JSON-Datei mit Modulen, Handlungszielen und Abschlüssen')" />

                        <section aria-labelledby="optionen-titel">
                            <h2 id="optionen-titel" class="{{ $gruppenTitel }}">{{ __('Optionen') }}</h2>
                            <div class="np-karte np-gruppe">
                                <x-einstellung :label="__('Keine neuen Lehrberufe anlegen')" fuer="ohne_berufe"
                                               :hinweis="__('Module werden dann nur bestehenden Lehrberufen zugeordnet.')">
                                    <input type="checkbox" role="switch" id="ohne_berufe" name="ohne_berufe" value="1" class="np-schalter"
                                           aria-describedby="ohne_berufe-hinweis" @checked(old('ohne_berufe'))>
                                </x-einstellung>
                                <x-einstellung :label="__('Eigene Module überschreiben')" fuer="eigene_uebernehmen"
                                               :hinweis="__('Sonst bleiben selbst erfasste Module mit derselben Nummer unberührt.')">
                                    <input type="checkbox" role="switch" id="eigene_uebernehmen" name="eigene_uebernehmen" value="1" class="np-schalter"
                                           aria-describedby="eigene_uebernehmen-hinweis" @checked(old('eigene_uebernehmen'))>
                                </x-einstellung>
                            </div>
                        </section>

                        <x-formular-aktionen :abbrechen="route('admin.master-data.modules.index')">{{ __('Vorschau erstellen') }}</x-formular-aktionen>
                    </form>
                @else
                    @php $zahlen = $vorschau['zahlen']; @endphp

                    <section aria-labelledby="vorschau-titel">
                        <div class="mb-2 flex items-baseline justify-between gap-4 px-1">
                            <h2 id="vorschau-titel" class="text-sm font-semibold text-text">{{ __('Vorschau') }}</h2>
                            <p class="min-w-0 truncate text-xs text-muted">{{ $vorschau['name'] }} · {{ __('Stand vom') }} {{ $vorschau['stand'] ?? __('unbekannt') }}</p>
                        </div>
                        <dl class="np-karte np-gruppe">
                            @foreach([
                                __('Module neu') => $zahlen['module_neu'],
                                __('Module aktualisiert') => $zahlen['module_alt'],
                                __('Handlungsziele') => $zahlen['ziele'],
                                __('LBV-Elemente') => $zahlen['lbv'],
                                __('Abschlüsse') => $zahlen['abschluesse'],
                                __('Lehrberufe neu') => $zahlen['berufe_neu'],
                                __('Zuordnungen Beruf zu Modul') => $zahlen['zuordnungen'],
                                __('Eigene Module übersprungen') => $zahlen['konflikte'],
                            ] as $was => $anzahl)
                                <div class="flex min-h-7 items-center justify-between gap-6 px-4 py-3 text-sm">
                                    <dt class="text-text">{{ $was }}</dt>
                                    <dd @class(['tabular-nums', 'text-text' => $anzahl > 0, 'text-muted' => ! ($anzahl > 0)])>{{ $anzahl }}</dd>
                                </div>
                            @endforeach
                        </dl>
                        <p class="{{ $fussnote }}">{{ __('Bestehende Module mit derselben Nummer werden aktualisiert, nichts wird gelöscht.') }}</p>
                    </section>

                    @if($vorschau['konflikte'] !== [])
                        <section aria-labelledby="konflikte-titel">
                            <h2 id="konflikte-titel" class="{{ $gruppenTitel }}">{{ __('Eigene Module bleiben unberührt') }}</h2>
                            <ul class="np-karte np-gruppe">
                                @foreach($vorschau['konflikte'] as $nummer => $titel)
                                    <li class="flex min-w-0 gap-3 px-4 py-2.5 text-sm">
                                        <span class="w-16 shrink-0 tabular-nums text-muted">{{ $nummer }}</span>
                                        <span class="truncate text-text">{{ $titel }}</span>
                                    </li>
                                @endforeach
                                @if($zahlen['konflikte'] > count($vorschau['konflikte']))
                                    <li class="px-4 py-2.5 text-sm text-muted">{{ __('… und :rest weitere.', ['rest' => $zahlen['konflikte'] - count($vorschau['konflikte'])]) }}</li>
                                @endif
                            </ul>
                            <p class="{{ $fussnote }}">{{ __('Dem eigenen Modul eine eigene Nummer geben (etwa ABU01 statt 801) oder die Datei mit «Eigene Module überschreiben» neu einlesen, wenn es dieselben Module sind.') }}</p>
                        </section>
                    @endif

                    @if($vorschau['berufe_neu'] !== [])
                        <section aria-labelledby="berufe-titel">
                            <h2 id="berufe-titel" class="{{ $gruppenTitel }}">{{ $vorschau['ohne_berufe'] ? __('Übersprungene Lehrberufe') : __('Neue Lehrberufe') }}</h2>
                            <ul class="np-karte np-gruppe">
                                @foreach($vorschau['berufe_neu'] as $beruf)
                                    <li class="px-4 py-2.5 text-sm text-text">{{ $beruf }}</li>
                                @endforeach
                            </ul>
                            @if($vorschau['ohne_berufe'])
                                <p class="{{ $fussnote }}">{{ __('Diese Lehrberufe gibt es im Portal nicht und sie werden auf Wunsch nicht angelegt – ihre Module bleiben ohne Zuordnung.') }}</p>
                            @endif
                        </section>
                    @endif

                    @if($vorschau['fehler'] !== [] || $vorschau['ohne_lernort'])
                        <section aria-labelledby="hinweise-titel">
                            <h2 id="hinweise-titel" class="{{ $gruppenTitel }}">{{ __('Hinweise') }}</h2>
                            <ul class="np-karte np-gruppe">
                                @if($vorschau['ohne_lernort'])
                                    <li class="px-4 py-2.5 text-sm text-text">{{ __('Kein Lernort erfasst – neue Zuordnungen bleiben ohne Lernort.') }}</li>
                                @endif
                                @foreach($vorschau['fehler'] as $hinweis)
                                    <li class="px-4 py-2.5 text-sm text-text">{{ $hinweis }}</li>
                                @endforeach
                            </ul>
                        </section>
                    @endif

                    {{-- Standardknopf ganz rechts, Verwerfen daneben (HIG «Buttons») --}}
                    <div class="flex items-center justify-end gap-2">
                        <form method="POST" action="{{ route('admin.master-data.modules.catalog.discard') }}"
                              x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                            @csrf
                            <button type="submit" :disabled="loading" class="np-knopf np-knopf-sekundaer min-w-24">{{ __('Verwerfen') }}</button>
                        </form>
                        <form method="POST" action="{{ route('admin.master-data.modules.catalog.apply') }}"
                              x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                            @csrf
                            <input type="hidden" name="token" value="{{ $vorschau['token'] }}">
                            <button type="submit" :disabled="loading" class="np-knopf np-knopf-primaer min-w-24">{{ __('Katalog übernehmen') }}</button>
                        </form>
                    </div>
                @endif

                @if($katalogModule > 0)
                    <section aria-labelledby="export-titel">
                        <h2 id="export-titel" class="{{ $gruppenTitel }}">{{ __('Katalog weitergeben') }}</h2>
                        <div class="np-karte np-gruppe">
                            <x-einstellung :label="__(':anzahl Module aus dem Katalog', ['anzahl' => $katalogModule])"
                                           :hinweis="__('Dieselbe Datei lässt sich in einer zweiten Installation wieder einlesen. Selbst erfasste Module sind nicht dabei.')">
                                <a href="{{ route('admin.master-data.modules.catalog.export') }}" class="np-knopf np-knopf-sekundaer">{{ __('Katalog herunterladen') }}</a>
                            </x-einstellung>
                        </div>
                    </section>
                @endif

            </div>
        </div>
    </div>
</x-app-layout>
