{{-- Farbthema des Betriebs: Auswahl zeigt die Seite sofort im gewählten Theme, gespeichert wird erst mit «Speichern» --}}
<section class="np-karte p-6 mt-5">
    <h3 class="text-sm font-semibold text-text">{{ __('Farbthema') }}</h3>
    <form method="POST" action="{{ route('admin.operations.theme.update') }}" class="mt-4 flex flex-col gap-5"
          x-data="{ loading: false, theme: @js($theme), dunkel: document.documentElement.classList.contains('dark') }"
          x-init="new MutationObserver(() => dunkel = document.documentElement.classList.contains('dark'))
                      .observe(document.documentElement, { attributes: true, attributeFilter: ['class'] })"
          @submit="if (!$event.defaultPrevented) loading = true">
        @csrf
        @method('PUT')
        <fieldset>
            <legend class="sr-only">{{ __('Farbthema') }}</legend>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                @foreach(\App\Support\Theme::THEMES as $wert => $name)
                    <label class="cursor-pointer rounded-xl border border-border p-2 transition-colors duration-100 hover:border-border-strong/60
                                  has-checked:border-accent has-checked:ring-2 has-checked:ring-accent/30 has-focus-visible:outline-2 has-focus-visible:outline-ring">
                        <input type="radio" name="theme" value="{{ $wert }}" class="sr-only" x-model="theme"
                               @change="document.documentElement.dataset.theme = theme" @checked($theme === $wert)>
                        <x-theme-vorschau :theme="$wert" x-bind:class="{ 'dark': dunkel }" />
                        <span class="mt-2 block px-0.5 text-sm font-medium text-text">{{ $name }}</span>
                    </label>
                @endforeach
            </div>
            @error('theme')<p class="mt-2 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
        </fieldset>
        <div class="flex justify-end">
            <button type="submit" :disabled="loading" class="np-knopf np-knopf-primaer np-knopf-gross">{{ __('Speichern') }}</button>
        </div>
    </form>
</section>
