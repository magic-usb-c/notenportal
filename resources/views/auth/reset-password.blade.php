<x-guest-layout>
    <form method="POST" action="{{ route('password.store') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div>
            <label for="email" class="block text-sm font-medium text-muted">E-Mail</label>
            <input id="email" type="email" name="email" value="{{ old('email', $request->email) }}"
                   required autofocus autocomplete="username"
                   class="mt-1 block w-full rounded-xl border border-border bg-input text-text px-3 py-2
                          focus:ring-2 focus:ring-ring focus:border-ring @error('email') border-red-400 @enderror">
            @error('email')
                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-muted">Neues Passwort</label>
            <input id="password" type="password" name="password" required autocomplete="new-password"
                   class="mt-1 block w-full rounded-xl border border-border bg-input text-text px-3 py-2
                          focus:ring-2 focus:ring-ring focus:border-ring @error('password') border-red-400 @enderror">
            @error('password')
                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password_confirmation" class="block text-sm font-medium text-muted">Passwort bestätigen</label>
            <input id="password_confirmation" type="password" name="password_confirmation"
                   required autocomplete="new-password"
                   class="mt-1 block w-full rounded-xl border border-border bg-input text-text px-3 py-2
                          focus:ring-2 focus:ring-ring focus:border-ring @error('password_confirmation') border-red-400 @enderror">
            @error('password_confirmation')
                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
            @enderror
        </div>

        <div class="pt-1">
            <button type="submit"
                    class="w-full flex justify-center px-4 py-2 h-10 rounded-xl bg-accent text-white font-medium hover:opacity-90">
                Passwort zurücksetzen
            </button>
        </div>
    </form>
</x-guest-layout>
