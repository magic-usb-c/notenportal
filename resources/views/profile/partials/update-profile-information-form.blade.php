{{-- Profil als gruppierte Einstellungsliste (macOS Systemeinstellungen). Das Konto hat ein eigenes Formular mit
     «Speichern»; die persönliche Darstellung gilt wie in den Systemeinstellungen sofort und wird je Änderung
     gespeichert (npPraeferenzen in resources/js/praeferenzen.js, ProfileController::preferences). --}}
@php
    $gruppe = 'np-karte np-gruppe';
    $kopf = 'mb-2 px-1 text-sm font-semibold text-text';
    $feld = 'np-feld w-72';
@endphp
<form method="POST" action="{{ route('profile.update') }}" class="flex flex-col gap-4"
      x-data="{ loading: false, geaendert: @js($errors->any()), email: @js(old('email', $user->email)), original: @js($user->email) }"
      @input="geaendert = true" @submit="if (!$event.defaultPrevented) loading = true">
    @csrf
    @method('patch')

    <section>
        <h2 class="{{ $kopf }}">{{ __('Konto') }}</h2>
        <div class="{{ $gruppe }}">
            @if($lernender)
                <x-einstellung :label="__('Name')">
                    <span class="text-sm text-muted">{{ $user->vorname }} {{ $user->nachname }}</span>
                </x-einstellung>
            @else
                <x-einstellung :label="__('Vorname')" fuer="vorname" name="vorname">
                    <input id="vorname" name="vorname" type="text" value="{{ old('vorname', $user->vorname) }}" required autocomplete="given-name"
                           class="{{ $feld }}" @error('vorname') aria-invalid="true" @enderror>
                </x-einstellung>
                <x-einstellung :label="__('Nachname')" fuer="nachname" name="nachname">
                    <input id="nachname" name="nachname" type="text" value="{{ old('nachname', $user->nachname) }}" required autocomplete="family-name"
                           class="{{ $feld }}" @error('nachname') aria-invalid="true" @enderror>
                </x-einstellung>
            @endif
            <x-einstellung :label="__('E-Mail')" fuer="email" name="email">
                <input id="email" name="email" type="email" x-model="email" required autocomplete="email"
                       class="{{ $feld }}" @error('email') aria-invalid="true" @enderror>
            </x-einstellung>
            <div x-show="email !== original" x-cloak>
                <x-einstellung :label="__('Aktuelles Passwort')" :hinweis="__('Zur Bestätigung der neuen Adresse')" fuer="current_password" name="current_password">
                    <input id="current_password" name="current_password" type="password" autocomplete="current-password"
                           class="{{ $feld }}" @error('current_password') aria-invalid="true" @enderror>
                </x-einstellung>
            </div>
            @if($lernender)
                <x-einstellung :label="__('Klasse Berufsfachschule')" fuer="klasse_schule" name="klasse_schule">
                    <input id="klasse_schule" name="klasse_schule" type="text" maxlength="30"
                           value="{{ old('klasse_schule', $lernender->klasse_schule) }}" placeholder="{{ __('z. B. INF24b') }}"
                           class="{{ $feld }}" @error('klasse_schule') aria-invalid="true" @enderror>
                </x-einstellung>
                @if($bmsAktiv)
                    <x-einstellung :label="__('Klasse BMS')" fuer="klasse_bms" name="klasse_bms">
                        <input id="klasse_bms" name="klasse_bms" type="text" maxlength="30"
                               value="{{ old('klasse_bms', $lernender->klasse_bms) }}"
                               class="{{ $feld }}" @error('klasse_bms') aria-invalid="true" @enderror>
                    </x-einstellung>
                @endif
            @endif
        </div>
    </section>

    @unless($praeferenzenOption ?? false)
        {{-- Ersatz ohne Spalte «praeferenzen»: Hell/Dunkel und Kontrast gehören zum Konto-Formular --}}
        <section class="mt-4">
            <h2 class="{{ $kopf }}">{{ __('Darstellung') }}</h2>
            <div class="{{ $gruppe }}">
                <x-einstellung :label="__('Erscheinungsbild')" name="darstellung">
                    <x-segment-auswahl name="darstellung" :wert="old('darstellung', $user->darstellung)"
                                       :optionen="['system' => __('Wie Gerät'), 'hell' => __('Hell'), 'dunkel' => __('Dunkel')]" />
                </x-einstellung>
                @if($kontrastOption ?? false)
                    <x-einstellung :label="__('Hoher Kontrast')" fuer="kontrast" name="kontrast">
                        <input id="kontrast" name="kontrast" type="checkbox" role="switch" value="1" @checked(old('kontrast', $user->kontrast))
                               class="np-schalter">
                    </x-einstellung>
                @endif
            </div>
        </section>
    @endunless

    <div class="flex justify-end">
        <button type="submit" :disabled="loading || !geaendert" class="np-knopf np-knopf-primaer min-w-24">
            {{ __('Speichern') }}
        </button>
    </div>
</form>

@if($praeferenzenOption ?? false)
    @php
        $p = $praeferenzen;
        $startseitenOptionen = \App\Support\Darstellung::STARTSEITEN[$dashboardRolle ?? null] ?? null;
        $kartenSichtbar = array_values(array_diff(array_keys($dashboardKarten ?? []), $p['karten_ausgeblendet']));
        $swatch = 'block size-6 rounded-full ring-1 ring-inset ring-border-strong/40';
        $swatchWahl = 'flex cursor-pointer rounded-full p-0.5 ring-2 ring-transparent transition-shadow duration-150 has-checked:ring-accent has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-ring';
        $vorschau = 'ring-2 ring-transparent ring-offset-2 ring-offset-card transition-shadow duration-150 peer-checked:ring-accent peer-focus-visible:outline-2 peer-focus-visible:outline-offset-4 peer-focus-visible:outline-ring';
    @endphp
    <div class="flex flex-col gap-8"
         x-data="npPraeferenzen({{ \Illuminate\Support\Js::from([
             'url' => route('profile.preferences'),
             'betriebTheme' => $betriebTheme,
             'werte' => [
                 'darstellung' => $user->darstellung ?? 'system',
                 'theme' => $p['theme'] ?? '',
                 'akzent' => $p['akzent'] ?? '',
                 'akzentEigen' => $p['akzent_eigen'] ?? '',
                 'schrift' => $p['schrift'],
                 'schriftart' => $p['schriftart'],
                 'dichte' => $p['dichte'],
                 'diagramm' => $p['diagramm'],
                 'ecken' => $p['ecken'],
                 'transparenz' => $p['transparenz'],
                 'bewegungReduziert' => $p['bewegung'] === \App\Support\Darstellung::BEWEGUNG_REDUZIERT,
                 'navigation' => $p['navigation'],
                 'tastenkuerzel' => $p['tastenkuerzel'],
                 'notenanzeige' => $p['notenanzeige'],
                 'startseite' => $p['startseite'],
                 'karten' => $kartenSichtbar,
             ],
         ]) }})">

        <section>
            <h2 class="{{ $kopf }}">{{ __('Darstellung') }}</h2>
            <div class="{{ $gruppe }}">
                <x-einstellung :label="__('Erscheinungsbild')" name="darstellung">
                    <x-segment-auswahl name="darstellung" :wert="$user->darstellung ?? 'system'" x-model="darstellung" x-on:change="darstellungSetzen()"
                                       :optionen="['system' => __('Wie Gerät'), 'hell' => __('Hell'), 'dunkel' => __('Dunkel')]" />
                </x-einstellung>

                {{-- Farbthema: Miniaturen wie «Erscheinungsbild» in den Systemeinstellungen --}}
                <div class="px-4 py-3">
                    <span id="theme-bez" class="text-sm text-text">{{ __('Farbthema') }}</span>
                    <div class="mt-3 grid grid-cols-5 gap-x-3 gap-y-4" role="radiogroup" aria-labelledby="theme-bez">
                        <label class="group cursor-pointer text-center">
                            <input type="radio" name="theme" value="" class="peer sr-only" x-model="theme" @change="setzen({ theme })" @checked($p['theme'] === null)>
                            <x-theme-vorschau :theme="$betriebTheme" x-bind:class="{ 'dark': dunkel }" :class="$vorschau" />
                            <span class="mt-1.5 block truncate text-xs text-muted peer-checked:font-medium peer-checked:text-text">{{ __('Wie Betrieb (:name)', ['name' => \App\Support\Theme::THEMES[$betriebTheme] ?? $betriebTheme]) }}</span>
                        </label>
                        @foreach(\App\Support\Theme::THEMES as $wert => $name)
                            <label class="group cursor-pointer text-center">
                                <input type="radio" name="theme" value="{{ $wert }}" class="peer sr-only" x-model="theme" @change="setzen({ theme })" @checked($p['theme'] === $wert)>
                                <x-theme-vorschau :theme="$wert" x-bind:class="{ 'dark': dunkel }" :class="$vorschau" />
                                <span class="mt-1.5 block truncate text-xs text-muted peer-checked:font-medium peer-checked:text-text">{{ $name }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                {{-- Akzentfarbe: ohne Wirkung beim Theme «Kontrast» --}}
                <x-einstellung :label="__('Akzentfarbe')" name="akzent" x-bind:class="{ 'opacity-40': effektivTheme() === 'kontrast' }">
                    <div class="flex items-center gap-1.5" role="radiogroup" aria-labelledby="akzent-bez">
                        <label class="{{ $swatchWahl }}" title="{{ __('Theme-Farbe') }}">
                            <input type="radio" name="akzent" value="" class="sr-only" x-model="akzent" @change="setzen({ akzent })"
                                   x-bind:disabled="effektivTheme() === 'kontrast'" @checked($p['akzent'] === null)>
                            <span x-bind:class="{ 'dark': dunkel }" class="{{ $swatch }} bg-accent"></span>
                            <span class="sr-only">{{ __('Theme-Farbe') }}</span>
                        </label>
                        @foreach(\App\Support\Darstellung::AKZENTE as $wert => $name)
                            <label class="{{ $swatchWahl }}" title="{{ __($name) }}">
                                <input type="radio" name="akzent" value="{{ $wert }}" class="sr-only" x-model="akzent" @change="setzen({ akzent })"
                                       x-bind:disabled="effektivTheme() === 'kontrast'" @checked($p['akzent'] === $wert)>
                                <span data-akzent="{{ $wert }}" x-bind:class="{ 'dark': dunkel }" class="{{ $swatch }} bg-accent"></span>
                                <span class="sr-only">{{ __($name) }}</span>
                            </label>
                        @endforeach
                        <label class="{{ $swatchWahl }}" title="{{ __('Eigene Farbe') }}">
                            <input type="radio" name="akzent" value="eigen" class="sr-only" x-model="akzent" @change="eigeneFarbeWaehlen()"
                                   x-bind:disabled="effektivTheme() === 'kontrast'" @checked($p['akzent'] === 'eigen')>
                            <input type="color" x-model="akzentEigen" @input="akzent = 'eigen'; anwenden()" @change="setzen({ akzent: 'eigen', akzent_eigen: akzentEigen })"
                                   x-bind:disabled="effektivTheme() === 'kontrast'" x-bind:class="{ 'np-farbrad': akzent !== 'eigen' }"
                                   class="{{ $swatch }} cursor-pointer border-0 bg-transparent p-0 @if($p['akzent'] !== 'eigen') np-farbrad @endif"
                                   aria-label="{{ __('Eigene Farbe wählen') }}">
                        </label>
                    </div>
                </x-einstellung>

                <x-einstellung :label="__('Schriftgrösse')" name="schrift">
                    <x-segment-auswahl name="schrift" :wert="$p['schrift']" x-model="schrift" x-on:change="setzen({ schrift })"
                                       :optionen="['normal' => __('Normal'), 'gross' => __('Gross'), 'sehr-gross' => __('Sehr gross')]" />
                </x-einstellung>
                {{-- Schriftart: reine System-Schriftstapel (kein Fremdladen) --}}
                <x-einstellung :label="__('Schriftart')" name="schriftart">
                    <x-segment-auswahl name="schriftart" :wert="$p['schriftart']" x-model="schriftart" x-on:change="setzen({ schriftart })"
                                       :optionen="['standard' => __('Standard'), 'serif' => __('Serif'), 'lesefreundlich' => __('Lesefreundlich')]" />
                </x-einstellung>
                {{-- Dichte: Tabellenzeilen, Karten-Padding und -Abstände --}}
                <x-einstellung :label="__('Dichte')" name="dichte">
                    <x-segment-auswahl name="dichte" :wert="$p['dichte']" x-model="dichte" x-on:change="setzen({ dichte })"
                                       :optionen="['normal' => __('Normal'), 'kompakt' => __('Kompakt')]" />
                </x-einstellung>
                {{-- Ecken: Rundungen der Flächen (Karten, Buttons, Felder) --}}
                <x-einstellung :label="__('Ecken')" name="ecken">
                    <x-segment-auswahl name="ecken" :wert="$p['ecken']" x-model="ecken" x-on:change="setzen({ ecken })"
                                       :optionen="['rund' => __('Rund'), 'eckig' => __('Eckig')]" />
                </x-einstellung>
                {{-- Diagrammfarben: «Farbenblind» ersetzt nur --chart-1..6 (Okabe-Ito-Palette) --}}
                <x-einstellung :label="__('Diagrammfarben')" name="diagramm">
                    <x-segment-auswahl name="diagramm" :wert="$p['diagramm']" x-model="diagramm" x-on:change="setzen({ diagramm })"
                                       :optionen="['standard' => __('Standard'), 'farbenblind' => __('Farbenblind')]" />
                </x-einstellung>
                {{-- Notenanzeige: Nachkommastellen der Durchschnitte auf interaktiven Seiten (<x-note>), wirkt ab der nächsten Seite --}}
                <x-einstellung :label="__('Notenanzeige')" name="notenanzeige"
                               :hinweis="__('Nachkommastellen bei Notendurchschnitten (nur Anzeige, nicht bei Notenblatt und Exporten).')">
                    <x-segment-auswahl name="notenanzeige" :wert="$p['notenanzeige']" x-model="notenanzeige" x-on:change="setzen({ notenanzeige })"
                                       :optionen="['1' => __('1 Nachkommastelle'), '2' => __('2 Nachkommastellen')]" />
                </x-einstellung>
            </div>
        </section>

        <section>
            <h2 class="{{ $kopf }}">{{ __('Bedienung') }}</h2>
            <div class="{{ $gruppe }}">
                {{-- Navigation: Seitenleiste (Standard) oder Tableiste oben, wechselt ohne Neuladen --}}
                <x-einstellung :label="__('Navigation')" name="navigation">
                    <x-segment-auswahl name="navigation" :wert="$p['navigation']" x-model="navigation" x-on:change="setzen({ navigation })"
                                       :optionen="['seite' => __('Seitenleiste'), 'oben' => __('Oben')]" />
                </x-einstellung>
                @if($startseitenOptionen)
                    {{-- Startseite: nur die für die eigene Rolle gültigen Ziele (App\Support\Darstellung::STARTSEITEN) --}}
                    <x-einstellung :label="__('Startseite')" name="startseite"
                                   :hinweis="__('Ziel nach der Anmeldung, sofern kein Link direkt auf eine andere Seite führte.')">
                        <x-segment-auswahl name="startseite" :wert="$p['startseite']" x-model="startseite" x-on:change="setzen({ startseite })"
                                           :optionen="collect($startseitenOptionen)->mapWithKeys(fn ($_, $w) => [$w => __(\App\Support\Darstellung::STARTSEITE_BEZEICHNUNG[$w] ?? $w)])->all()" />
                    </x-einstellung>
                @endif
                {{-- Transparenz: schaltet Blur auf Leiste und Overlay ab (deckende Fläche) --}}
                <x-einstellung :label="__('Transparenz reduzieren')" fuer="transparenz">
                    <input id="transparenz" type="checkbox" role="switch" class="np-schalter" @checked($p['transparenz'] === 'reduziert') x-bind:checked="transparenz === 'reduziert'"
                           @change="transparenz = $event.target.checked ? 'reduziert' : 'normal'; setzen({ transparenz })">
                </x-einstellung>
                <x-einstellung :label="__('Bewegungen reduzieren')" fuer="bewegung_reduziert">
                    <input id="bewegung_reduziert" type="checkbox" role="switch" class="np-schalter" x-model="bewegungReduziert"
                           @change="setzen({ bewegung: bewegungReduziert ? 'reduziert' : 'normal' })">
                </x-einstellung>
                {{-- Tastenkürzel: schaltet Dialog und Listener ab der nächsten Seite ab; «Anzeigen» nur, solange sie hier geladen sind --}}
                <x-einstellung :label="__('Tastenkürzel')" fuer="tastenkuerzel">
                    @if($p['tastenkuerzel'] === \App\Support\Darstellung::TASTENKUERZEL_AN)
                        <button type="button" x-show="tastenkuerzel === 'an'" x-on:click="$dispatch('open-tastenkuerzel')"
                                class="np-knopf np-knopf-schlicht np-knopf-klein">{{ __('Anzeigen') }}</button>
                    @endif
                    <input id="tastenkuerzel" type="checkbox" role="switch" class="np-schalter" @checked($p['tastenkuerzel'] === 'an') x-bind:checked="tastenkuerzel === 'an'"
                           @change="tastenkuerzel = $event.target.checked ? 'an' : 'aus'; setzen({ tastenkuerzel })">
                </x-einstellung>
            </div>
        </section>

        {{-- Übersicht: Dashboard-Karten der eigenen Rolle ein-/ausblenden (App\Support\DashboardKarten); die letzte bleibt an --}}
        @if(($dashboardRolle ?? null) && ($dashboardKarten ?? []))
            <section>
                <h2 class="{{ $kopf }}">{{ __('Übersicht') }}</h2>
                <div class="{{ $gruppe }}">
                    @foreach($dashboardKarten as $schluessel => $bezeichnung)
                        <x-einstellung :label="__($bezeichnung)" :fuer="'karte-'.$schluessel">
                            <input id="karte-{{ $schluessel }}" type="checkbox" role="switch" name="karten[]" value="{{ $schluessel }}" class="np-schalter"
                                   aria-label="{{ __(':karte anzeigen', ['karte' => __($bezeichnung)]) }}" aria-describedby="karten-hinweis"
                                   x-model="karten" x-bind:disabled="karten.length === 1 && karten.includes(@js($schluessel))" @change="setzen({ karten: [...karten] })"
                                   @checked(in_array($schluessel, $kartenSichtbar, true))>
                        </x-einstellung>
                    @endforeach
                </div>
                <p id="karten-hinweis" class="mt-2 px-1 text-xs text-muted">{{ __('Karten auf dem Dashboard ein- oder ausblenden. Eine bleibt immer sichtbar.') }}</p>
            </section>
        @endif

        <div>
            <button type="submit" form="praeferenzen-zuruecksetzen" class="np-knopf np-knopf-schlicht">
                {{ __('Auf Standard zurücksetzen') }}
            </button>
        </div>
    </div>

    <form id="praeferenzen-zuruecksetzen" method="POST" action="{{ route('profile.preferences.reset') }}" class="hidden"
          data-bestaetigen="{{ __('Darstellung auf Standard zurücksetzen?') }}" data-bestaetigen-knopf="{{ __('Zurücksetzen') }}">
        @csrf
        @method('delete')
    </form>
@endif
