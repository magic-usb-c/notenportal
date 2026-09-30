<x-app-layout>
    <x-slot name="title">{{ __('Benachrichtigungen') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :titel="__('Benachrichtigungen')" schmal>
            @include('settings._tabs')
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-4 sm:px-6 lg:px-8">
        <div class="max-w-3xl">

            <form method="POST" action="{{ route('notifications.settings.update') }}" class="flex flex-col gap-8"
                  x-data="{ loading: false }" @submit="loading = true">
                @csrf
                @method('PUT')

                @foreach($gruppenLabels as $gruppeKey => $gruppeLabel)
                    @continue(empty($gruppen[$gruppeKey]))
                    <section>
                        <h2 class="mb-2 px-1 text-sm font-semibold text-text">{{ __($gruppeLabel) }}</h2>
                        <div class="np-karte np-gruppe">
                            @foreach($gruppen[$gruppeKey] as $a)
                                <x-einstellung :label="$a['label']" :name="'frequenz-'.$a['type']" :fehler="'frequenz.'.$a['type']"
                                               :hinweis="$a['mandatory'] ? $a['description'].' '.__('Vom Betrieb festgelegt.') : $a['description']">
                                    @if($a['mandatory'])
                                        {{-- vom Betrieb festgelegt: keine Wahl, deshalb kein (deaktiviertes) Bedienelement --}}
                                        <span class="inline-flex items-center gap-1.5 text-sm text-muted">
                                            <x-symbol name="lock-closed" class="size-3.5" />{{ __($frequenzen[$a['aktuell']] ?? $a['aktuell']) }}
                                        </span>
                                    @else
                                        <div class="np-segment" role="radiogroup" aria-labelledby="frequenz-{{ $a['type'] }}-bez">
                                            @foreach($a['frequencies'] as $f)
                                                <label><input type="radio" name="frequenz[{{ $a['type'] }}]" value="{{ $f }}" class="sr-only"
                                                              @checked($a['aktuell'] === $f)>{{ __($frequenzen[$f]) }}</label>
                                            @endforeach
                                        </div>
                                    @endif
                                </x-einstellung>
                            @endforeach
                        </div>
                    </section>
                @endforeach

                <p class="-mt-6 px-1 text-xs text-muted">{{ __('Du erhältst diese Mails an') }} <span class="font-medium text-text">{{ auth()->user()->email }}</span>.</p>

                <div class="flex justify-end">
                    <button type="submit" :disabled="loading" class="np-knopf np-knopf-primaer min-w-24">{{ __('Speichern') }}</button>
                </div>
            </form>

        </div>
        </div>
    </div>
</x-app-layout>
