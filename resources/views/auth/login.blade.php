<x-guest-layout>
    <div class="text-center mb-6">
        <x-application-logo class="h-10 mx-auto mb-2" />
        <h1 class="text-2xl font-bold text-text">Notenportal</h1>
        @if($betriebName)<p class="mt-1 text-sm text-muted">{{ $betriebName }}</p>@endif
    </div>

    {{-- Fester Text, der Parameterinhalt selbst wird nie ausgegeben. --}}
    @if(request()->boolean('abgelaufen'))
        <div role="alert" class="mb-4 rounded-lg bg-note-ungenuegend/10 px-4 py-3 text-sm text-note-ungenuegend">
            {{ __('Du wurdest wegen Inaktivität abgemeldet.') }}
        </div>
    @endif

    @if($loginHinweis ?? null)
        @php($npLoginHinweisWarnung = ($loginHinweis['art'] ?? 'info') === 'warnung')
        <div role="{{ $npLoginHinweisWarnung ? 'alert' : 'status' }}"
             class="mb-4 whitespace-pre-line rounded-lg px-4 py-3 text-sm {{ $npLoginHinweisWarnung ? 'bg-note-ungenuegend/10 text-note-ungenuegend' : 'bg-accent/10 text-accent-text' }}">
            {{ $loginHinweis['text'] }}
        </div>
    @endif

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <div>
            <label for="email" class="block text-sm font-medium text-text mb-1.5">{{ __('E-Mail') }}</label>
            <div class="relative">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-muted pointer-events-none">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                </span>
                <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                       autocomplete="username" placeholder="{{ __('name@firma.ch') }}"
                       class="np-feld block h-11 pl-10 pr-3 @error('email') border-note-ungenuegend @enderror">
            </div>
            @error('email')
                <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-text mb-1.5">{{ __('Passwort') }}</label>
            <div class="relative">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-muted pointer-events-none">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                </span>
                <input id="password" type="password" name="password" required autocomplete="current-password"
                       placeholder="••••••••"
                       class="np-feld block h-11 pl-10 pr-3 @error('password') border-note-ungenuegend @enderror">
            </div>
            @error('password')
                <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
            @enderror
        </div>

        <div class="pt-2">
            <button type="submit"
                    class="np-knopf np-knopf-primaer np-knopf-gross w-full">
                {{ __('Anmelden') }}
            </button>
        </div>
    </form>

    <p class="mt-4 text-center text-sm">
        <a href="{{ route('password.request') }}" class="text-muted hover:text-text">{{ __('Passwort vergessen?') }}</a>
    </p>

</x-guest-layout>
