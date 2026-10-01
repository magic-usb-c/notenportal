<x-einrichtung schritt="operations" :stand="$stand" :titel="__('Betrieb')">
    <form method="POST" action="{{ route('admin.setup.operations') }}" class="flex flex-col gap-8"
          x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
        @csrf
        <section>
            <h2 class="mb-2 px-1 text-sm font-semibold text-text">{{ __('Dein Konto') }}</h2>
            <div class="np-karte np-gruppe">
                @foreach(['vorname' => [__('Vorname'), 'text', 'given-name', 100], 'nachname' => [__('Nachname'), 'text', 'family-name', 100], 'email' => [__('E-Mail'), 'email', 'email', 255]] as $name => [$text, $typ, $auto, $max])
                    <x-einstellung :label="$text" :fuer="$name" :name="$name">
                        <input id="{{ $name }}" name="{{ $name }}" type="{{ $typ }}" required maxlength="{{ $max }}" value="{{ old($name, $konto->$name) }}"
                               autocomplete="{{ $auto }}" class="np-feld w-72" @error($name) aria-invalid="true" aria-describedby="{{ $name }}-fehler" @enderror>
                    </x-einstellung>
                @endforeach
            </div>
        </section>

        @include('admin.betrieb._felder', ['werte' => $werte])

        @include('admin.einrichtung._fuss', ['schritt' => 'operations'])
    </form>
</x-einrichtung>
