<x-app-layout>
    <x-slot name="title">{{ __('Mein Profil') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :titel="__('Mein Profil')" schmal />
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-4 sm:px-6 lg:px-8">
        <div class="max-w-3xl">

            @include('settings._tabs')

            <div class="mt-5 space-y-5">

            @if($lernender)
                <div class="rounded-xl border border-border bg-card p-5">
                    <h3 class="font-semibold text-text text-sm">{{ __('Lehrausbildung') }}</h3>
                    <dl class="mt-3 grid grid-cols-2 gap-x-6 gap-y-2 text-sm">
                        <div>
                            <dt class="text-xs text-muted">{{ __('Lehrberuf') }}</dt>
                            <dd class="text-text font-medium">
                                {{ $lehrberuf->name ?? '–' }}
                                @if($lehrberuf?->kuerzel)
                                    <span class="text-muted font-mono text-xs">({{ $lehrberuf->kuerzel }})</span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted">{{ __('Lehrbeginn') }}</dt>
                            <dd class="text-text">{{ $lernender->lehrbeginn?->format('d.m.Y') ?? '–' }}</dd>
                        </div>
                        @if($lernender->lehrende)
                            <div>
                                <dt class="text-xs text-muted">{{ __('Lehrende') }}</dt>
                                <dd class="text-text">{{ $lernender->lehrende->format('d.m.Y') }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            @endif

            {{-- Profil bearbeiten --}}
            <div class="rounded-xl border border-border bg-card p-6">
                @include('profile.partials.update-profile-information-form')
            </div>

            {{-- Tastenkürzel: bewusst hier statt in einem eigenen Darstellung-Tab (siehe docs/audit-backlog.md) --}}
            @if($tastenkuerzelAktiv)
                <div class="rounded-xl border border-border bg-card p-6">
                    <div class="flex items-center justify-between gap-4">
                        <div class="min-w-0">
                            <h3 class="font-semibold text-text text-sm">{{ __('Tastenkürzel') }}</h3>
                        </div>
                        <button type="button" onclick="window.dispatchEvent(new CustomEvent('open-tastenkuerzel'))"
                                class="shrink-0 inline-flex items-center gap-1.5 h-9 px-4 rounded-xl glass-btn text-text text-sm font-medium">
                            {{ __('Tastenkürzel anzeigen') }}
                        </button>
                    </div>
                </div>
            @endif

            {{-- Sprache (nur mit eingeschalteter Sprachwahl), Passwort ändern --}}
            @if(\App\Http\Middleware\SetLocale::wahlAktiv())@include('profile.partials.sprache')@endif<div class="rounded-xl border border-border bg-card p-6">
                @include('profile.partials.update-password-form')
            </div>

            </div>
        </div>
        </div>
    </div>
</x-app-layout>
