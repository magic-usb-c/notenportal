<section>
    <h2 class="text-base font-semibold text-text">{{ __('Profildaten') }}</h2>

    <form method="POST" action="{{ route('profile.update') }}" class="mt-5 flex flex-col gap-4"
          x-data="{ loading: false, email: @js(old('email', $user->email)), original: @js($user->email) }"
          @submit="if (!$event.defaultPrevented) loading = true">
        @csrf
        @method('patch')

        @if($lernender)
            <div>
                <span class="text-sm font-medium text-text">{{ __('Name') }}</span>
                <p class="mt-1 text-text">{{ $user->vorname }} {{ $user->nachname }}</p>
            </div>
        @else
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="vorname" class="text-sm font-medium text-text">{{ __('Vorname') }} *</label>
                    <input id="vorname" name="vorname" type="text"
                           value="{{ old('vorname', $user->vorname) }}" required autocomplete="given-name"
                           class="mt-1 block w-full rounded-xl border border-border bg-input text-text px-3 py-2
                                  focus:ring-2 focus:ring-ring focus:border-ring @error('vorname') border-note-ungenuegend! @enderror">
                    @error('vorname')
                        <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="nachname" class="text-sm font-medium text-text">{{ __('Nachname') }} *</label>
                    <input id="nachname" name="nachname" type="text"
                           value="{{ old('nachname', $user->nachname) }}" required autocomplete="family-name"
                           class="mt-1 block w-full rounded-xl border border-border bg-input text-text px-3 py-2
                                  focus:ring-2 focus:ring-ring focus:border-ring @error('nachname') border-note-ungenuegend! @enderror">
                    @error('nachname')
                        <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        @endif

        <div>
            <label for="email" class="text-sm font-medium text-text">{{ __('E-Mail') }} *</label>
            <input id="email" name="email" type="email" x-model="email" required autocomplete="email"
                   class="mt-1 block w-full rounded-xl border border-border bg-input text-text px-3 py-2
                          focus:ring-2 focus:ring-ring focus:border-ring @error('email') border-note-ungenuegend! @enderror">
            @error('email')
                <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
            @enderror
        </div>

        <div x-show="email !== original" x-cloak>
            <label for="current_password" class="text-sm font-medium text-text">{{ __('Aktuelles Passwort') }} *</label>
            <input id="current_password" name="current_password" type="password" autocomplete="current-password"
                   class="mt-1 block w-full rounded-xl border border-border bg-input text-text px-3 py-2
                          focus:ring-2 focus:ring-ring focus:border-ring @error('current_password') border-note-ungenuegend! @enderror">
            @error('current_password')
                <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
            @enderror
        </div>

        @if($lernender)
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="klasse_schule" class="text-sm font-medium text-text">{{ __('Klasse Berufsfachschule') }}</label>
                    <input id="klasse_schule" name="klasse_schule" type="text" maxlength="30"
                           value="{{ old('klasse_schule', $lernender->klasse_schule) }}" placeholder="{{ __('z. B. INF24b') }}"
                           class="mt-1 block w-full rounded-xl border border-border bg-input text-text px-3 py-2
                                  focus:ring-2 focus:ring-ring focus:border-ring @error('klasse_schule') border-note-ungenuegend! @enderror">
                    @error('klasse_schule')
                        <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
                    @enderror
                </div>
                @if($bmsAktiv)
                    <div>
                        <label for="klasse_bms" class="text-sm font-medium text-text">{{ __('Klasse BMS') }}</label>
                        <input id="klasse_bms" name="klasse_bms" type="text" maxlength="30"
                               value="{{ old('klasse_bms', $lernender->klasse_bms) }}"
                               class="mt-1 block w-full rounded-xl border border-border bg-input text-text px-3 py-2
                                      focus:ring-2 focus:ring-ring focus:border-ring @error('klasse_bms') border-note-ungenuegend! @enderror">
                        @error('klasse_bms')
                            <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
                        @enderror
                    </div>
                @endif
            </div>
        @endif

        <fieldset>
            <legend class="text-sm font-medium text-text">{{ __('Darstellung') }}</legend>
            <div class="mt-2 grid grid-cols-3 gap-2">
                @foreach(['system' => __('Wie Gerät'), 'hell' => __('Hell'), 'dunkel' => __('Dunkel')] as $wert => $label)
                    <label class="flex items-center justify-center h-10 rounded-xl border border-border bg-input text-sm text-text cursor-pointer
                                  has-checked:border-accent has-checked:bg-accent/10 has-checked:text-accent-text has-focus-visible:ring-2 has-focus-visible:ring-ring">
                        <input type="radio" name="darstellung" value="{{ $wert }}" class="sr-only"
                               @checked(old('darstellung', $user->darstellung) === $wert)>
                        {{ $label }}
                    </label>
                @endforeach
            </div>

            @if($praeferenzenOption ?? false)
                @php
                    // Abgewiesene Werte (Validierungsfehler) zeigen wieder die gespeicherte Wahl statt gar keine
                    $altTheme = old('theme', $praeferenzen['theme'] ?? '') ?: null;
                    $altTheme = $altTheme === null || array_key_exists($altTheme, \App\Support\Theme::THEMES) ? $altTheme : ($praeferenzen['theme'] ?? null);
                    $altAkzent = old('akzent', $praeferenzen['akzent'] ?? '') ?: null;
                    $altAkzent = $altAkzent === null || array_key_exists($altAkzent, \App\Support\Darstellung::AKZENTE) ? $altAkzent : ($praeferenzen['akzent'] ?? null);
                    $altSchrift = old('schrift', $praeferenzen['schrift'] ?? 'normal');
                    $altSchrift = in_array($altSchrift, \App\Support\Darstellung::SCHRIFTGROESSEN, true) ? $altSchrift : ($praeferenzen['schrift'] ?? 'normal');
                    $altBewegungReduziert = (bool) old('bewegung_reduziert', ($praeferenzen['bewegung'] ?? 'normal') === 'reduziert');
                @endphp
                <div class="mt-5 flex flex-col gap-5"
                     x-data="{
                         theme: @js($altTheme ?? ''),
                         akzent: @js($altAkzent ?? ''),
                         schrift: @js($altSchrift),
                         bewegungReduziert: @js($altBewegungReduziert),
                         dunkel: document.documentElement.classList.contains('dark'),
                         betriebTheme: @js($betriebTheme),
                         effektivTheme() { return this.theme || this.betriebTheme; },
                         anwenden() {
                             document.documentElement.dataset.theme = this.effektivTheme();
                             if (this.effektivTheme() === 'kontrast' || !this.akzent) {
                                 delete document.documentElement.dataset.akzent;
                             } else {
                                 document.documentElement.dataset.akzent = this.akzent;
                             }
                             if (this.schrift === 'normal') delete document.documentElement.dataset.schrift;
                             else document.documentElement.dataset.schrift = this.schrift;
                             if (this.bewegungReduziert) document.documentElement.dataset.bewegung = 'reduziert';
                             else delete document.documentElement.dataset.bewegung;
                         },
                     }"
                     x-init="new MutationObserver(() => dunkel = document.documentElement.classList.contains('dark'))
                                 .observe(document.documentElement, { attributes: true, attributeFilter: ['class'] })">

                    {{-- Theme --}}
                    <fieldset>
                        <legend class="text-sm font-medium text-text">{{ __('Farbthema') }}</legend>
                        <div class="mt-2 grid grid-cols-2 sm:grid-cols-3 gap-3">
                            <label class="cursor-pointer rounded-xl border border-border p-2 transition-colors duration-100 hover:border-border-strong/60
                                          has-checked:border-accent has-checked:ring-2 has-checked:ring-accent/30 has-focus-visible:outline-2 has-focus-visible:outline-ring">
                                <input type="radio" name="theme" value="" class="sr-only" x-model="theme" @change="anwenden()" @checked($altTheme === null)>
                                <x-theme-vorschau :theme="$betriebTheme" x-bind:class="{ 'dark': dunkel }" />
                                <span class="mt-2 block px-0.5 text-sm font-medium text-text">{{ __('Wie Betrieb (:name)', ['name' => \App\Support\Theme::THEMES[$betriebTheme] ?? $betriebTheme]) }}</span>
                            </label>
                            @foreach(\App\Support\Theme::THEMES as $wert => $name)
                                <label class="cursor-pointer rounded-xl border border-border p-2 transition-colors duration-100 hover:border-border-strong/60
                                              has-checked:border-accent has-checked:ring-2 has-checked:ring-accent/30 has-focus-visible:outline-2 has-focus-visible:outline-ring">
                                    <input type="radio" name="theme" value="{{ $wert }}" class="sr-only" x-model="theme" @change="anwenden()" @checked($altTheme === $wert)>
                                    <x-theme-vorschau :theme="$wert" x-bind:class="{ 'dark': dunkel }" />
                                    <span class="mt-2 block px-0.5 text-sm font-medium text-text">{{ $name }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('theme')<p class="mt-2 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                    </fieldset>

                    {{-- Akzentfarbe: ohne Wirkung beim Theme «Kontrast» --}}
                    <fieldset :class="{ 'opacity-40': effektivTheme() === 'kontrast' }">
                        <legend class="text-sm font-medium text-text">{{ __('Akzentfarbe') }}</legend>
                        <div class="mt-2 flex flex-wrap gap-3">
                            <label class="flex cursor-pointer flex-col items-center gap-1.5 has-focus-visible:outline-2 has-focus-visible:outline-ring rounded-lg p-1">
                                <input type="radio" name="akzent" value="" class="sr-only" x-model="akzent" @change="anwenden()"
                                       x-bind:disabled="effektivTheme() === 'kontrast'" @checked($altAkzent === null)>
                                <span x-bind:class="{ 'dark': dunkel, 'ring-2 ring-accent ring-offset-2 ring-offset-bg': akzent === '' }"
                                      class="block h-8 w-8 rounded-full border border-border-strong/40 bg-accent"></span>
                                <span class="sr-only">{{ __('Theme-Farbe') }}</span>
                            </label>
                            @foreach(\App\Support\Darstellung::AKZENTE as $wert => $name)
                                <label class="flex cursor-pointer flex-col items-center gap-1.5 has-focus-visible:outline-2 has-focus-visible:outline-ring rounded-lg p-1">
                                    <input type="radio" name="akzent" value="{{ $wert }}" class="sr-only" x-model="akzent" @change="anwenden()"
                                           x-bind:disabled="effektivTheme() === 'kontrast'" @checked($altAkzent === $wert)>
                                    <span data-akzent="{{ $wert }}" x-bind:class="{ 'dark': dunkel, 'ring-2 ring-accent ring-offset-2 ring-offset-bg': akzent === @js($wert) }"
                                          class="block h-8 w-8 rounded-full border border-border-strong/40 bg-accent"></span>
                                    <span class="sr-only">{{ __($name) }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('akzent')<p class="mt-2 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                    </fieldset>

                    {{-- Schriftgrösse --}}
                    <fieldset>
                        <legend class="text-sm font-medium text-text">{{ __('Schriftgrösse') }}</legend>
                        <div class="mt-2 grid grid-cols-3 gap-2">
                            @foreach(['normal' => __('Normal'), 'gross' => __('Gross'), 'sehr-gross' => __('Sehr gross')] as $wert => $label)
                                <label class="flex items-center justify-center h-10 rounded-xl border border-border bg-input text-sm text-text cursor-pointer
                                              has-checked:border-accent has-checked:bg-accent/10 has-checked:text-accent-text has-focus-visible:ring-2 has-focus-visible:ring-ring">
                                    <input type="radio" name="schrift" value="{{ $wert }}" class="sr-only" x-model="schrift" @change="anwenden()"
                                           @checked($altSchrift === $wert)>
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                        @error('schrift')<p class="mt-2 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                    </fieldset>

                    <label for="bewegung_reduziert" class="flex min-h-9 w-fit cursor-pointer items-center gap-2.5 text-sm text-text">
                        <input id="bewegung_reduziert" name="bewegung_reduziert" type="checkbox" value="1" x-model="bewegungReduziert" @change="anwenden()"
                               class="h-4 w-4 rounded border-border-strong/70 bg-input text-accent focus:ring-2 focus:ring-ring/30">
                        {{ __('Bewegungen reduzieren') }}
                    </label>
                </div>
            @elseif($kontrastOption ?? false)
                <label for="kontrast" class="mt-3 flex min-h-9 w-fit cursor-pointer items-center gap-2.5 text-sm text-text">
                    <input id="kontrast" name="kontrast" type="checkbox" value="1" @checked(old('kontrast', $user->kontrast))
                           class="h-4 w-4 rounded border-border-strong/70 bg-input text-accent focus:ring-2 focus:ring-ring/30">
                    {{ __('Hoher Kontrast') }}
                </label>
            @endif
        </fieldset>

        <div class="pt-1">
            <button type="submit" :disabled="loading"
                    class="px-5 py-2 h-10 rounded-xl bg-accent text-accent-contrast font-medium np-btn-primary disabled:opacity-60 disabled:cursor-not-allowed">
                {{ __('Speichern') }}
            </button>
        </div>
    </form>
</section>
