{{-- Sitzungs-Timeout: Minuten Inaktivität bis zur automatischen Abmeldung. Leer = Standard aus der .env. --}}
<section class="rounded-xl border border-border bg-card p-6 mt-5">
    <h3 class="text-sm font-semibold text-text">{{ __('Sitzungsdauer') }}</h3>
    <form method="POST" action="{{ route('admin.operations.session.update') }}" class="mt-4 flex flex-col gap-5"
          x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
        @csrf
        @method('PUT')
        <div>
            <label for="sitzung_minuten" class="text-sm font-medium text-text">{{ __('Minuten bis zur automatischen Abmeldung') }}</label>
            <input id="sitzung_minuten" name="sitzung_minuten" type="number" min="15" max="480"
                   value="{{ old('sitzung_minuten', $sitzungMinuten) }}" placeholder="{{ $sitzungStandard }}"
                   aria-describedby="sitzung_minuten-fehler"
                   class="mt-1.5 h-10 w-full rounded-lg border border-border-strong/70 bg-input px-3 text-sm text-text focus:border-accent focus:ring-2 focus:ring-ring/30 sm:max-w-xs">
            @error('sitzung_minuten')<p id="sitzung_minuten-fehler" class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
        </div>
        <div class="flex justify-end">
            <button type="submit" :disabled="loading" class="inline-flex h-10 items-center rounded-lg bg-accent px-5 text-sm font-medium text-accent-contrast np-btn-primary disabled:opacity-50">{{ __('Speichern') }}</button>
        </div>
    </form>
</section>
