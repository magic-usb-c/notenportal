<x-einrichtung schritt="mail" :stand="$stand" :titel="__('E-Mail')">
    <form method="POST" action="{{ route('admin.setup.mail') }}" class="flex flex-col gap-5"
          x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
        @csrf
        <section class="np-karte p-6">
            <h3 class="text-sm font-semibold text-text mb-4">{{ __('Mailversand') }}</h3>
            @include('admin.betrieb._mail', ['werte' => $werte])
        </section>

        @include('admin.einrichtung._fuss', ['schritt' => 'mail'])
    </form>

    <section class="np-karte p-6">
        <h3 class="text-sm font-semibold text-text mb-4">{{ __('Testmail') }}</h3>
        @include('admin.betrieb._testmail', ['testTo' => $testTo])
    </section>
</x-einrichtung>
