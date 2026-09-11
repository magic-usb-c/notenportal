<x-app-layout>
    <x-slot name="title">Mein Profil</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-text">Mein Profil</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-5">

            @if($lernender)
                <div class="glass rounded-2xl p-5">
                    <h3 class="font-semibold text-text text-sm">Lehrausbildung</h3>
                    <dl class="mt-3 grid grid-cols-2 gap-x-6 gap-y-2 text-sm">
                        <div>
                            <dt class="text-xs text-muted">Lehrberuf</dt>
                            <dd class="text-text font-medium">
                                {{ $lehrberuf->name ?? '–' }}
                                @if($lehrberuf?->kuerzel)
                                    <span class="text-muted font-mono text-xs">({{ $lehrberuf->kuerzel }})</span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted">Lehrbeginn</dt>
                            <dd class="text-text">{{ $lernender->lehrbeginn?->format('d.m.Y') ?? '–' }}</dd>
                        </div>
                        @if($lernender->lehrende)
                            <div>
                                <dt class="text-xs text-muted">Lehrende</dt>
                                <dd class="text-text">{{ $lernender->lehrende->format('d.m.Y') }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            @endif

            {{-- Profil bearbeiten --}}
            <div class="glass rounded-2xl p-6">
                @include('profile.partials.update-profile-information-form')
            </div>

            {{-- Benachrichtigungen --}}
            <div class="glass rounded-2xl p-6">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h3 class="font-semibold text-text text-sm">Benachrichtigungen</h3>
                        <p class="mt-1 text-sm text-muted">Wähle, welche Mails du erhältst und wie oft.</p>
                    </div>
                    <a href="{{ route('notifications.settings') }}"
                       class="shrink-0 inline-flex items-center gap-1.5 h-9 px-4 rounded-xl glass-btn text-text text-sm font-medium">
                        Einstellen
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </div>
            </div>

            {{-- Passwort ändern --}}
            <div class="glass rounded-2xl p-6">
                @include('profile.partials.update-password-form')
            </div>

        </div>
    </div>
</x-app-layout>
