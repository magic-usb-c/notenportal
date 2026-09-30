{{-- Testmail – in «Betrieb» (Bereich E-Mail) und im Einrichtungsschritt «E-Mail» ($herkunft = 'einrichtung'). --}}
<form method="POST" action="{{ route('admin.mail.test').(isset($herkunft) ? '' : '#email') }}"
      x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
    @csrf
    @isset($herkunft)<input type="hidden" name="herkunft" value="{{ $herkunft }}">@endisset
    <h2 class="mb-2 px-1 text-sm font-semibold text-text">{{ __('Testmail') }}</h2>
    <div class="np-karte">
        <x-einstellung :label="__('Testmail an')" fuer="test_to" name="test_to">
            <input id="test_to" name="test_to" type="email" required maxlength="190" value="{{ old('test_to', $testTo) }}" autocomplete="off"
                   class="np-feld w-72" @error('test_to') aria-invalid="true" @enderror>
            <button type="submit" :disabled="loading" class="np-knopf np-knopf-sekundaer">
                <span x-show="loading" x-cloak class="h-4 w-4 animate-spin rounded-full border-2 border-accent border-t-transparent" aria-hidden="true"></span>
                {{ __('Testmail senden') }}
            </button>
        </x-einstellung>
    </div>
</form>
