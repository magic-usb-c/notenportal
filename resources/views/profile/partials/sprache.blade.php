{{-- Sprache der Oberfläche und der Mails (nur mit eingeschalteter Sprachwahl); wirkt sofort wie in den Systemeinstellungen --}}
<section>
    <h2 class="mb-2 px-1 text-sm font-semibold text-text">{{ __('Sprache') }}</h2>
    <form method="POST" action="{{ route('profile.locale') }}" class="np-karte">
        @csrf
        @method('PUT')
        <x-einstellung :label="__('Sprache')" name="locale">
            <div class="np-segment" role="radiogroup" aria-labelledby="locale-bez">
                @foreach(['de' => 'Deutsch', 'en' => 'English'] as $wert => $name)
                    <label lang="{{ $wert }}"><input type="radio" name="locale" value="{{ $wert }}" class="sr-only" onchange="this.form.requestSubmit()"
                                                     @checked(app()->getLocale() === $wert)>{{ $name }}</label>
                @endforeach
            </div>
            <noscript><button type="submit" class="np-knopf np-knopf-sekundaer np-knopf-klein">{{ __('Sprache speichern') }}</button></noscript>
        </x-einstellung>
    </form>
</section>
