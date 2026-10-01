<x-guest-layout>
    {{-- Fester Text, der Parameterinhalt selbst wird nie ausgegeben. --}}
    @if(request()->boolean('abgelaufen'))
        <div role="alert" class="mb-6 rounded-xl bg-note-ungenuegend/10 px-4 py-3 text-sm text-note-ungenuegend">
            {{ __('Du wurdest wegen Inaktivität abgemeldet.') }}
        </div>
    @endif

    @if($loginHinweis ?? null)
        @php($npLoginHinweisWarnung = ($loginHinweis['art'] ?? 'info') === 'warnung')
        <div role="{{ $npLoginHinweisWarnung ? 'alert' : 'status' }}"
             class="mb-6 whitespace-pre-line rounded-xl px-4 py-3 text-sm {{ $npLoginHinweisWarnung ? 'bg-note-ungenuegend/10 text-note-ungenuegend' : 'bg-accent/10 text-accent-text' }}">
            {{ $loginHinweis['text'] }}
        </div>
    @endif

    <x-auth-session-status class="mb-6" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="flex flex-col gap-5"
          x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
        @csrf
        @include('auth._feld', ['name' => 'email', 'typ' => 'email', 'label' => __('E-Mail'), 'autocomplete' => 'username', 'wert' => old('email'), 'autofocus' => true])
        @include('auth._feld', ['name' => 'password', 'typ' => 'password', 'label' => __('Passwort'), 'autocomplete' => 'current-password'])
        <button type="submit" :disabled="loading" class="np-knopf np-knopf-primaer np-knopf-gross mt-1 w-full">{{ __('Anmelden') }}</button>
    </form>

    <x-slot:fuss>
        <a href="{{ route('password.request') }}" class="inline-flex min-h-6 items-center text-accent-text hover:underline underline-offset-2">{{ __('Passwort vergessen?') }}</a>
    </x-slot:fuss>
</x-guest-layout>
