<x-guest-layout>
    <div class="text-center">
        <h1 class="text-2xl font-bold text-text">{{ __('Abbestellt') }}</h1>
        <p class="mt-2 text-sm text-muted">{{ __('Du erhältst keine Mails mehr zu «:label».', ['label' => $label]) }}</p>
        <p class="mt-6 text-sm">
            <a href="{{ route('notifications.settings') }}" class="text-accent-text font-medium hover:underline">{{ __('Zu den Benachrichtigungen anmelden') }}</a>
        </p>
    </div>
</x-guest-layout>
