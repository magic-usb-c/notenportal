@php
    $feld = 'mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring';
    $label = 'text-xs uppercase tracking-widest text-muted font-medium';
@endphp
<form method="POST" action="{{ route('admin.mail.test') }}" class="flex flex-wrap items-end gap-3"
      x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
    @csrf
    <div class="flex-1 min-w-48">
        <label for="test_to" class="{{ $label }}">Testmail an</label>
        <input id="test_to" name="test_to" type="email" required maxlength="190" value="{{ old('test_to', $testTo) }}" class="{{ $feld }}" autocomplete="off">
        @error('test_to')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
    </div>
    <button type="submit" :disabled="loading" class="inline-flex items-center gap-2 px-4 h-10 rounded-xl glass-btn text-text text-sm disabled:opacity-60">
        <span x-show="loading" x-cloak class="w-4 h-4 rounded-full border-2 border-accent border-t-transparent animate-spin" aria-hidden="true"></span>
        Testmail senden
    </button>
</form>
