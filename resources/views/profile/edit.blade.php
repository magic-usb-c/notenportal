<x-app-layout>
    <x-slot name="title">Mein Profil</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-text">Mein Profil</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-5">

            {{-- Lehrausbildung (nur für Lernende sichtbar) --}}
            @if($lernendeProfil)
                <div class="bg-card border border-border rounded-2xl shadow-sm p-5">
                    <h3 class="font-semibold text-text text-sm">Lehrausbildung</h3>
                    <dl class="mt-3 grid grid-cols-2 gap-x-6 gap-y-2 text-sm">
                        <div>
                            <dt class="text-xs text-muted">Lehrberuf</dt>
                            <dd class="text-text font-medium">
                                {{ $lernendeProfil->lehrberuf_name ?? '–' }}
                                @if($lernendeProfil->kuerzel)
                                    <span class="text-muted font-mono text-xs">({{ $lernendeProfil->kuerzel }})</span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted">Lehrbeginn</dt>
                            <dd class="text-text">
                                {{ $lernendeProfil->lehrbeginn ? \Carbon\Carbon::parse($lernendeProfil->lehrbeginn)->format('d.m.Y') : '–' }}
                            </dd>
                        </div>
                        @if($lernendeProfil->lehrende)
                            <div>
                                <dt class="text-xs text-muted">Lehrende</dt>
                                <dd class="text-text">
                                    {{ \Carbon\Carbon::parse($lernendeProfil->lehrende)->format('d.m.Y') }}
                                </dd>
                            </div>
                        @endif
                    </dl>
                </div>
            @endif

            {{-- Profil bearbeiten --}}
            <div class="bg-card border border-border rounded-2xl shadow-sm p-6">
                @include('profile.partials.update-profile-information-form')
            </div>

            {{-- Passwort ändern --}}
            <div class="bg-card border border-border rounded-2xl shadow-sm p-6">
                @include('profile.partials.update-password-form')
            </div>

        </div>
    </div>
</x-app-layout>
