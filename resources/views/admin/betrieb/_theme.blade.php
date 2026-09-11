{{-- Farbthema des Betriebs: Auswahl zeigt die Seite sofort im gewählten Theme, gespeichert wird erst mit «Speichern» --}}
@php
    $noten = ['bg-note-gut', 'bg-note-genuegend', 'bg-note-knapp', 'bg-note-ungenuegend'];
    $serien = ['bg-chart-1', 'bg-chart-2', 'bg-chart-3', 'bg-chart-4', 'bg-chart-5'];
@endphp
<section class="rounded-xl border border-border bg-card p-6 mt-5">
    <h3 class="text-sm font-semibold text-text">Farbthema</h3>
    <form method="POST" action="{{ route('admin.operations.theme.update') }}" class="mt-4 flex flex-col gap-5"
          x-data="{ loading: false, theme: @js($theme), dunkel: document.documentElement.classList.contains('dark') }"
          x-init="new MutationObserver(() => dunkel = document.documentElement.classList.contains('dark'))
                      .observe(document.documentElement, { attributes: true, attributeFilter: ['class'] })"
          @submit="if (!$event.defaultPrevented) loading = true">
        @csrf
        @method('PUT')
        <fieldset>
            <legend class="sr-only">Farbthema</legend>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                @foreach(\App\Support\Theme::THEMES as $wert => $name)
                    <label class="cursor-pointer rounded-xl border border-border p-2 transition-colors duration-100 hover:border-border-strong/60
                                  has-checked:border-accent has-checked:ring-2 has-checked:ring-accent/30 has-focus-visible:outline-2 has-focus-visible:outline-ring">
                        <input type="radio" name="theme" value="{{ $wert }}" class="sr-only" x-model="theme"
                               @change="document.documentElement.dataset.theme = theme" @checked($theme === $wert)>
                        <div data-theme="{{ $wert }}" :class="{ 'dark': dunkel }" class="rounded-lg border border-border bg-bg p-2" aria-hidden="true">
                            <div class="rounded-md border border-border bg-card p-2">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-sm font-semibold text-text">Aa</span>
                                    <span class="h-4 w-8 rounded-sm bg-accent"></span>
                                </div>
                                <div class="mt-1.5 h-1.5 w-3/4 rounded-full bg-muted/50"></div>
                                <div class="mt-2 flex gap-1">
                                    @foreach($noten as $klasse)<span class="h-2.5 flex-1 rounded-xs {{ $klasse }}"></span>@endforeach
                                </div>
                                <div class="mt-1 flex gap-1">
                                    @foreach($serien as $klasse)<span class="h-1.5 flex-1 rounded-full {{ $klasse }}"></span>@endforeach
                                </div>
                            </div>
                        </div>
                        <span class="mt-2 block px-0.5 text-sm font-medium text-text">{{ $name }}</span>
                    </label>
                @endforeach
            </div>
            @error('theme')<p class="mt-2 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
        </fieldset>
        <div class="flex justify-end">
            <button type="submit" :disabled="loading" class="inline-flex h-10 items-center rounded-lg bg-accent px-5 text-sm font-medium text-accent-contrast np-btn-primary disabled:opacity-50">Speichern</button>
        </div>
    </form>
</section>
