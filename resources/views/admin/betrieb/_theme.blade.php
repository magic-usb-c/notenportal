{{-- Farbthema des Betriebs: Wahl gilt sofort (Seite wechselt vorab ins Theme, dann wird gespeichert). --}}
<form method="POST" action="{{ route('admin.operations.theme.update') }}#darstellung"
      x-data="{ dunkel: document.documentElement.classList.contains('dark') }"
      x-init="new MutationObserver(() => dunkel = document.documentElement.classList.contains('dark'))
                  .observe(document.documentElement, { attributes: true, attributeFilter: ['class'] })">
    @csrf
    @method('PUT')
    <fieldset>
        <legend class="mb-2 px-1 text-sm font-semibold text-text">{{ __('Farbthema') }}</legend>
        <div class="grid grid-cols-4 gap-3">
            @foreach(\App\Support\Theme::THEMES as $wert => $name)
                <label class="cursor-pointer">
                    <input type="radio" name="theme" value="{{ $wert }}" class="peer sr-only" data-sofort @checked($theme === $wert)
                           @error('theme') aria-invalid="true" aria-describedby="theme-fehler" @enderror
                           @change="document.documentElement.dataset.theme = $event.target.value">
                    <x-theme-vorschau :theme="$wert" x-bind:class="{ 'dark': dunkel }"
                                      class="ring-2 ring-transparent ring-offset-2 ring-offset-bg transition-shadow duration-150 peer-checked:ring-accent peer-focus-visible:outline-2 peer-focus-visible:outline-offset-4 peer-focus-visible:outline-ring" />
                    <span class="mt-1.5 block text-center text-xs text-muted peer-checked:font-medium peer-checked:text-text">{{ $name }}</span>
                </label>
            @endforeach
        </div>
    </fieldset>
    @error('theme')<p id="theme-fehler" class="mt-2 px-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
    <p class="mt-2 px-1 text-xs text-muted">{{ __('Gilt für alle, die im Profil kein eigenes Farbthema gewählt haben.') }}</p>
    <noscript><button type="submit" class="np-knopf np-knopf-sekundaer mt-3">{{ __('Speichern') }}</button></noscript>
</form>
