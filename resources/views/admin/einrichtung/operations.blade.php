<x-einrichtung schritt="operations" :stand="$stand" :titel="__('Betrieb')">
    @php
        $feld = 'mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring';
        $label = 'text-sm font-medium text-text';
    @endphp
    <form method="POST" action="{{ route('admin.setup.operations') }}" class="flex flex-col gap-5"
          x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
        @csrf
        <section class="rounded-2xl border border-border bg-card p-6">
            <h3 class="text-sm font-semibold text-text mb-4">{{ __('Dein Konto') }}</h3>
            <div class="grid sm:grid-cols-3 gap-4">
                @foreach(['vorname' => [__('Vorname'), 'text', 'given-name'], 'nachname' => [__('Nachname'), 'text', 'family-name'], 'email' => [__('E-Mail'), 'email', 'email']] as $name => [$text, $typ, $auto])
                    <div>
                        <label for="{{ $name }}" class="{{ $label }}">{{ $text }} *</label>
                        <input id="{{ $name }}" name="{{ $name }}" type="{{ $typ }}" required maxlength="{{ $typ === 'email' ? 255 : 100 }}"
                               value="{{ old($name, $konto->$name) }}" autocomplete="{{ $auto }}" class="{{ $feld }}">
                        @error($name)<p class="mt-1 text-xs text-note-ungenuegend">{{ $message }}</p>@enderror
                    </div>
                @endforeach
            </div>
        </section>

        <section class="rounded-2xl border border-border bg-card p-6">
            <h3 class="text-sm font-semibold text-text mb-4">{{ __('Betrieb und Notengrenzen') }}</h3>
            @include('admin.betrieb._felder', ['werte' => $werte])
        </section>

        @include('admin.einrichtung._fuss', ['schritt' => 'operations'])
    </form>
</x-einrichtung>
