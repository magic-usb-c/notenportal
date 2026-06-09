<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-text">Mein Profil</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-5">

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
