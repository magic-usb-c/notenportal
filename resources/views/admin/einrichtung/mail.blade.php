<x-einrichtung schritt="mail" :stand="$stand" titel="E-Mail">
    <form method="POST" action="{{ route('admin.einrichtung.mail') }}" class="flex flex-col gap-5"
          x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
        @csrf
        <section class="glass rounded-2xl p-6">
            <h3 class="text-sm font-semibold text-text mb-4">Mailversand</h3>
            @include('admin.betrieb._mail', ['werte' => $werte])
        </section>

        @include('admin.einrichtung._fuss', ['schritt' => 'mail'])
    </form>

    <section class="glass rounded-2xl p-6">
        <h3 class="text-sm font-semibold text-text mb-4">Testmail</h3>
        @include('admin.betrieb._testmail', ['testTo' => $testTo])
    </section>
</x-einrichtung>
