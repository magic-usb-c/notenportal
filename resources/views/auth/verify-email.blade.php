<x-guest-layout>
    <div class="mb-4 text-sm text-muted">
        Bitte bestätige deine E-Mail-Adresse, indem du auf den Link klickst, den wir dir zugeschickt haben.
        Falls du keine E-Mail erhalten hast, schicken wir dir gerne eine neue.
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 font-medium text-sm text-green-600 dark:text-green-400">
            Ein neuer Bestätigungslink wurde an deine E-Mail-Adresse geschickt.
        </div>
    @endif

    <div class="mt-4 flex items-center justify-between gap-4">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit"
                    class="px-4 py-2 rounded-xl bg-accent text-white hover:opacity-90 text-sm font-medium">
                Bestätigungs-E-Mail erneut senden
            </button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit"
                    class="text-sm text-muted hover:text-text underline">
                Abmelden
            </button>
        </form>
    </div>
</x-guest-layout>
