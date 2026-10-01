{{-- Personenangaben der Benutzerformulare. $user ist null beim Anlegen. --}}
<section>
    <h2 class="mb-2 px-1 text-sm font-semibold text-text">{{ __('Person') }}</h2>
    <div class="np-karte np-gruppe">
        @foreach(['vorname' => __('Vorname'), 'nachname' => __('Nachname')] as $feld => $bezeichnung)
            <x-einstellung :label="$bezeichnung" :fuer="$feld" :name="$feld">
                <input type="text" name="{{ $feld }}" id="{{ $feld }}" value="{{ old($feld, $user?->{$feld}) }}" required maxlength="100"
                       autocomplete="{{ $feld === 'vorname' ? 'given-name' : 'family-name' }}"
                       class="np-feld w-72" @error($feld) aria-invalid="true" aria-describedby="{{ $feld }}-fehler" @enderror>
            </x-einstellung>
        @endforeach
        <x-einstellung :label="__('E-Mail')" fuer="email" name="email">
            <input type="email" name="email" id="email" value="{{ old('email', $user?->email) }}" required maxlength="255" autocomplete="off"
                   class="np-feld w-72" @error('email') aria-invalid="true" aria-describedby="email-fehler" @enderror>
        </x-einstellung>
        <x-einstellung :label="__('Benutzername')" fuer="benutzername" name="benutzername"
                       :hinweis="__('Buchstaben, Ziffern und . _ -')">
            <input type="text" name="benutzername" id="benutzername" value="{{ old('benutzername', $user?->benutzername) }}" required
                   maxlength="50" pattern="[A-Za-z0-9._\-]+" autocomplete="off" spellcheck="false"
                   class="np-feld w-72" aria-describedby="benutzername-hinweis @error('benutzername') benutzername-fehler @enderror" @error('benutzername') aria-invalid="true" @enderror>
        </x-einstellung>
    </div>
</section>
