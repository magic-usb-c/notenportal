<x-guest-layout :titel="__('Abbestellt')">
    <p class="text-center text-sm text-muted">{{ __('Du erhältst keine Mails mehr zu «:label».', ['label' => $label]) }}</p>

    <x-slot:fuss>
        <a href="{{ route('notifications.settings') }}" class="inline-flex min-h-6 items-center text-accent-text hover:underline underline-offset-2">{{ __('Zu den Benachrichtigungen anmelden') }}</a>
    </x-slot:fuss>
</x-guest-layout>
