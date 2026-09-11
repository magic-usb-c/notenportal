<section>
    <h2 class="text-base font-semibold text-text">Profildaten</h2>

    <form method="POST" action="{{ route('profile.update') }}" class="mt-5 flex flex-col gap-4"
          x-data="{ loading: false, email: @js(old('email', $user->email)), original: @js($user->email) }"
          @submit="if (!$event.defaultPrevented) loading = true">
        @csrf
        @method('patch')

        @if($lernender)
            <div>
                <span class="text-xs uppercase tracking-widest text-muted font-medium">Name</span>
                <p class="mt-1 text-text">{{ $user->vorname }} {{ $user->nachname }}</p>
            </div>
        @else
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="vorname" class="text-xs uppercase tracking-widest text-muted font-medium">Vorname *</label>
                    <input id="vorname" name="vorname" type="text"
                           value="{{ old('vorname', $user->vorname) }}" required autocomplete="given-name"
                           class="mt-1 block w-full rounded-xl border border-border bg-input text-text px-3 py-2
                                  focus:ring-2 focus:ring-ring focus:border-ring @error('vorname') border-note-ungenuegend! @enderror">
                    @error('vorname')
                        <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="nachname" class="text-xs uppercase tracking-widest text-muted font-medium">Nachname *</label>
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
            <label for="email" class="text-xs uppercase tracking-widest text-muted font-medium">E-Mail *</label>
            <input id="email" name="email" type="email" x-model="email" required autocomplete="email"
                   class="mt-1 block w-full rounded-xl border border-border bg-input text-text px-3 py-2
                          focus:ring-2 focus:ring-ring focus:border-ring @error('email') border-note-ungenuegend! @enderror">
            @error('email')
                <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
            @enderror
        </div>

        <div x-show="email !== original" x-cloak>
            <label for="current_password" class="text-xs uppercase tracking-widest text-muted font-medium">Aktuelles Passwort *</label>
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
                    <label for="klasse_schule" class="text-xs uppercase tracking-widest text-muted font-medium">Klasse Berufsfachschule</label>
                    <input id="klasse_schule" name="klasse_schule" type="text" maxlength="30"
                           value="{{ old('klasse_schule', $lernender->klasse_schule) }}" placeholder="z. B. INF24b"
                           class="mt-1 block w-full rounded-xl border border-border bg-input text-text px-3 py-2
                                  focus:ring-2 focus:ring-ring focus:border-ring @error('klasse_schule') border-note-ungenuegend! @enderror">
                    @error('klasse_schule')
                        <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
                    @enderror
                </div>
                @if($bmsAktiv)
                    <div>
                        <label for="klasse_bms" class="text-xs uppercase tracking-widest text-muted font-medium">Klasse BMS</label>
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
            <legend class="text-xs uppercase tracking-widest text-muted font-medium">Darstellung</legend>
            <div class="mt-2 grid grid-cols-3 gap-2">
                @foreach(['system' => 'Wie Gerät', 'hell' => 'Hell', 'dunkel' => 'Dunkel'] as $wert => $label)
                    <label class="flex items-center justify-center h-10 rounded-xl border border-border bg-input text-sm text-text cursor-pointer
                                  has-checked:border-accent has-checked:bg-accent/10 has-checked:text-accent-text has-focus-visible:ring-2 has-focus-visible:ring-ring">
                        <input type="radio" name="darstellung" value="{{ $wert }}" class="sr-only"
                               @checked(old('darstellung', $user->darstellung) === $wert)>
                        {{ $label }}
                    </label>
                @endforeach
            </div>
            @if($kontrastOption ?? false)
                <label for="kontrast" class="mt-3 flex min-h-9 w-fit cursor-pointer items-center gap-2.5 text-sm text-text">
                    <input id="kontrast" name="kontrast" type="checkbox" value="1" @checked(old('kontrast', $user->kontrast))
                           class="h-4 w-4 rounded border-border-strong/70 bg-input text-accent focus:ring-2 focus:ring-ring/30">
                    Hoher Kontrast
                </label>
            @endif
        </fieldset>

        <div class="pt-1">
            <button type="submit" :disabled="loading"
                    class="px-5 py-2 h-10 rounded-xl bg-accent text-accent-contrast font-medium np-btn-primary disabled:opacity-60 disabled:cursor-not-allowed">
                Speichern
            </button>
        </div>
    </form>
</section>
