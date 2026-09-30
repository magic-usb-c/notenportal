<x-einrichtung schritt="mail" :stand="$stand" :titel="__('E-Mail')">
    {{-- Testmail ist ein eigenes Formular zwischen Einstellungen und Fusszeile; der Knopf der Fusszeile gehört per «form» zu den Einstellungen. --}}
    <div class="flex flex-col gap-8" x-data="{ loading: false }">
        <form id="einrichtung-mail" method="POST" action="{{ route('admin.setup.mail') }}" class="flex flex-col gap-8"
              @submit="if (!$event.defaultPrevented) loading = true">
            @csrf
            @include('admin.betrieb._mail', ['werte' => $werte])
        </form>
        @include('admin.betrieb._testmail', ['testTo' => $testTo, 'herkunft' => 'einrichtung'])
        @include('admin.einrichtung._fuss', ['schritt' => 'mail', 'formular' => 'einrichtung-mail'])
    </div>
</x-einrichtung>
