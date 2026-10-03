{{-- Betriebslogo (Navigation, Anmeldeseite, Mail-Kopf, Notenblatt): die gewählte Datei wird sofort hochgeladen. --}}
<form method="POST" action="{{ route('admin.operations.logo.update') }}#darstellung" enctype="multipart/form-data"
      x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) setTimeout(() => loading = true)">
    @csrf
    @method('PUT')
    <h2 class="mb-2 px-1 text-sm font-semibold text-text">{{ __('Logo') }}</h2>
    <div class="np-karte px-4 py-3">
        <div class="flex items-center gap-4">
            <div class="flex h-14 w-32 shrink-0 items-center justify-center rounded-lg bg-fill p-2">
                @if($logoVorhanden)
                    <img src="{{ route('branding.logo') }}" alt="{{ __('Aktuelles Logo') }}" class="np-logo max-h-full max-w-full object-contain">
                @else
                    <x-symbol name="photo" class="size-6 text-faint" />
                @endif
            </div>
            <p class="min-w-0 flex-1 text-xs text-muted">{{ __('PNG, JPG oder WebP, höchstens 1 MB und 1024 × 1024 Pixel.') }}</p>
            @if($logoVorhanden)
                <button type="submit" name="logo_entfernen" value="1" formnovalidate :disabled="loading" class="np-knopf np-knopf-schlicht">{{ __('Entfernen') }}</button>
            @endif
            <input id="logo" name="logo" type="file" class="peer sr-only" accept="image/png,image/jpeg,image/webp"
                   @error('logo') aria-invalid="true" aria-describedby="logo-fehler" @enderror
                   @change="if ($event.target.files.length) $event.target.form.requestSubmit()">
            <label for="logo" class="np-knopf np-knopf-sekundaer peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-ring"
                   :class="loading && 'pointer-events-none opacity-50'">
                <span x-show="loading" x-cloak class="h-4 w-4 animate-spin rounded-full border-2 border-accent border-t-transparent" aria-hidden="true"></span>
                {{ $logoVorhanden ? __('Ersetzen …') : __('Datei wählen …') }}
            </label>
        </div>
        @error('logo')<p id="logo-fehler" class="mt-1.5 text-right text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
    </div>
    <noscript><button type="submit" class="np-knopf np-knopf-sekundaer mt-3">{{ __('Speichern') }}</button></noscript>
</form>
