@php
    $feld = 'np-feld mt-1';
    $label = 'text-sm font-medium text-text';
@endphp
<form method="POST" action="{{ route('admin.mail.test') }}" class="flex flex-wrap items-end gap-3"
      x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
    @csrf
    <div class="flex-1 min-w-48">
        <label for="test_to" class="{{ $label }}">{{ __('Testmail an') }}</label>
        <input id="test_to" name="test_to" type="email" required maxlength="190" value="{{ old('test_to', $testTo) }}" class="{{ $feld }}" autocomplete="off">
        @error('test_to')<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
    </div>
    <button type="submit" :disabled="loading" class="np-knopf np-knopf-sekundaer">
        <span x-show="loading" x-cloak class="w-4 h-4 rounded-full border-2 border-accent border-t-transparent animate-spin" aria-hidden="true"></span>
        {{ __('Testmail senden') }}
    </button>
</form>
