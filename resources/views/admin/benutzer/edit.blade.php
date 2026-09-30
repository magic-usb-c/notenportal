<x-app-layout>
    <x-slot name="title">{{ __('Benutzer bearbeiten') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :zurueck="route('admin.users.index')" :titel="__('Benutzer bearbeiten')" :untertitel="$user->nachname.' '.$user->vorname" schmal>
            <x-slot:aktionen>
                <a href="{{ route('admin.users.data-export', $user->benutzer_id) }}" class="np-knopf np-knopf-sekundaer">
                    <x-symbol name="arrow-down-tray" class="size-4" />{{ __('Daten herunterladen') }}
                </a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('admin.users.update', $user->benutzer_id) }}" class="flex max-w-3xl flex-col gap-8"
                  x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                @csrf
                @method('PUT')

                @include('admin.benutzer._person', ['user' => $user])

                <section>
                    <h2 id="rollen-bez" class="mb-2 px-1 text-sm font-semibold text-text">{{ __('Rollen') }}</h2>
                    <div class="np-karte np-gruppe" role="group" aria-labelledby="rollen-bez">
                        @foreach(['Berufsbildner' => __('Betreut Lernende'), 'Admin' => __('Volle Verwaltung')] as $rolle => $beschreibung)
                            <x-einstellung :label="__($rolle)" :fuer="'rolle-'.$rolle" :hinweis="$beschreibung">
                                <input type="checkbox" role="switch" name="rollen[]" value="{{ $rolle }}" id="rolle-{{ $rolle }}" class="np-schalter"
                                       @checked(in_array($rolle, old('rollen', $rollen->all()), true))>
                            </x-einstellung>
                        @endforeach
                    </div>
                    @error('rollen')<p class="mt-2 px-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                </section>

                @include('admin.benutzer._passwort', ['pflicht' => false])

                <x-formular-aktionen :abbrechen="route('admin.users.index')">{{ __('Änderungen speichern') }}</x-formular-aktionen>
            </form>
        </div>
    </div>
</x-app-layout>
