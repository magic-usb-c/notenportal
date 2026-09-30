<x-app-layout>
    <x-slot name="title">{{ __('Neuer Benutzer') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :zurueck="route('admin.users.index')" :titel="__('Neuen Benutzer anlegen')" schmal />
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('admin.users.store') }}" class="flex max-w-3xl flex-col gap-8"
                  x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                @csrf

                @include('admin.benutzer._person', ['user' => null])

                <section>
                    <h2 class="mb-2 px-1 text-sm font-semibold text-text">{{ __('Rolle') }}</h2>
                    <div class="np-karte">
                        <x-einstellung :label="__('Rolle')" name="rolle_id">
                            <x-segment-auswahl name="rolle_id" required
                                               :optionen="$rollen->mapWithKeys(fn ($r) => [$r->rolle_id => __($r->name)])"
                                               :wert="old('rolle_id', $rollen->firstWhere('name', 'Berufsbildner')?->rolle_id)" />
                        </x-einstellung>
                    </div>
                    <p class="mt-2 px-1 text-xs text-muted">{{ __('Berufsbildner betreuen Lernende, Admins verwalten das ganze Portal.') }}</p>
                </section>

                @include('admin.benutzer._passwort', ['pflicht' => true])

                <x-formular-aktionen :abbrechen="route('admin.users.index')">{{ __('Benutzer anlegen') }}</x-formular-aktionen>
            </form>
        </div>
    </div>
</x-app-layout>
