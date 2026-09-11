<x-guest-layout>
    <div class="text-center">
        <h1 class="text-2xl font-bold text-text">Abbestellt</h1>
        <p class="mt-2 text-sm text-muted">Du erhältst keine Mails mehr zu «{{ $label }}».</p>
        <p class="mt-6 text-sm">
            <a href="{{ route('notifications.settings') }}" class="text-accent font-medium hover:underline">Zu den Benachrichtigungen anmelden</a>
        </p>
    </div>
</x-guest-layout>
