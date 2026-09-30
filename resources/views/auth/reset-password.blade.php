<x-guest-layout>
    <div class="text-center mb-6">
        <h1 class="text-2xl font-bold text-text">{{ __('Neues Passwort festlegen') }}</h1>
    </div>

    <form method="POST" action="{{ route('password.store') }}" class="space-y-4"
          x-data="{ loading: false }" @submit="loading = true">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div>
            <label for="email" class="block text-sm font-medium text-text mb-1.5">{{ __('E-Mail') }}</label>
            <input id="email" type="email" name="email" value="{{ old('email', $email) }}" required autofocus
                   autocomplete="username"
                   class="np-feld block h-11 @error('email') border-note-ungenuegend @enderror">
            @error('email')
                <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-text mb-1.5">{{ __('Neues Passwort') }}</label>
            <input id="password" type="password" name="password" required autocomplete="new-password"
                   class="np-feld block h-11 @error('password') border-note-ungenuegend @enderror">
            @error('password')
                <p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password_confirmation" class="block text-sm font-medium text-text mb-1.5">{{ __('Passwort bestätigen') }}</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"
                   class="np-feld block h-11">
        </div>

        <div class="pt-2">
            <button type="submit" :disabled="loading"
                    class="np-knopf np-knopf-primaer np-knopf-gross w-full">
                {{ __('Passwort speichern') }}
            </button>
        </div>
    </form>
</x-guest-layout>
