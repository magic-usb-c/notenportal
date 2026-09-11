{{-- Betriebslogo: PNG/JPG/WebP, max. 1 MB, max. 1024×1024 px. Erscheint in Navigation, Login-Seite, Mail-Kopf, Notenblatt. --}}
<section class="rounded-xl border border-border bg-card p-6 mt-5">
    <h3 class="text-sm font-semibold text-text">{{ __('Logo') }}</h3>
    <form method="POST" action="{{ route('admin.operations.logo.update') }}" enctype="multipart/form-data" class="mt-4 flex flex-col gap-5"
          x-data="{ loading: false, dateiname: '' }" @submit="if (!$event.defaultPrevented) setTimeout(() => loading = true)">
        @csrf
        @method('PUT')
        @if($logoVorhanden)
            <div class="flex items-center gap-4">
                <img src="{{ route('branding.logo') }}" alt="{{ __('Aktuelles Logo') }}" class="h-12 w-auto rounded-lg border border-border bg-surface-2 p-1.5">
                <p class="text-xs text-muted">{{ __('Aktuelles Logo') }}</p>
            </div>
        @endif
        <div>
            <label for="logo" class="text-sm font-medium text-text">{{ __('Bilddatei') }}</label>
            <div class="mt-1.5 flex items-center gap-3">
                <label for="logo" class="inline-flex h-9 items-center gap-2 rounded-lg glass-btn px-3.5 text-sm font-medium text-text cursor-pointer">
                    {{ __('Datei wählen') }}
                </label>
                <span class="text-sm text-muted" x-text="dateiname || @js(__('Keine Datei gewählt'))"></span>
            </div>
            <input id="logo" name="logo" type="file" class="sr-only" accept="image/png,image/jpeg,image/webp"
                   aria-describedby="logo-fehler" @change="dateiname = $event.target.files[0]?.name ?? ''">
            <p class="mt-1.5 text-xs text-muted">{{ __('PNG, JPG oder WebP, höchstens 1 MB und 1024 × 1024 Pixel.') }}</p>
            @error('logo')<p id="logo-fehler" class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
        </div>
        <div class="flex flex-row-reverse justify-start gap-3">
            <button type="submit" :disabled="loading" class="inline-flex h-10 items-center rounded-lg bg-accent px-5 text-sm font-medium text-accent-contrast np-btn-primary disabled:opacity-50">{{ __('Speichern') }}</button>
            @if($logoVorhanden)
                <button type="submit" name="logo_entfernen" value="1" formnovalidate :disabled="loading"
                        class="inline-flex h-10 items-center rounded-lg glass-btn px-5 text-sm font-medium text-text disabled:opacity-50">{{ __('Logo entfernen') }}</button>
            @endif
        </div>
    </form>
</section>
