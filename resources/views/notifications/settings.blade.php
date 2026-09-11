<x-app-layout>
    <x-slot name="title">Benachrichtigungen</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-text">Benachrichtigungen</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8 space-y-5">

            <p class="text-sm text-muted px-1">Du erhältst diese Mails an <span class="text-text font-medium">{{ auth()->user()->email }}</span>.</p>

            <form method="POST" action="{{ route('notifications.settings.update') }}" class="space-y-5"
                  x-data="{ loading: false }" @submit="loading = true">
                @csrf
                @method('PUT')

                @foreach($gruppenLabels as $gruppeKey => $gruppeLabel)
                    @continue(empty($gruppen[$gruppeKey]))
                    <div class="glass rounded-2xl p-5">
                        <h3 class="font-semibold text-text text-sm mb-1">{{ $gruppeLabel }}</h3>
                        <div class="flex flex-col divide-y divide-border">
                            @foreach($gruppen[$gruppeKey] as $a)
                                <div class="py-4 first:pt-3 last:pb-0 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-1.5">
                                            <span class="text-sm font-medium text-text">{{ $a['label'] }}</span>
                                            @if($a['mandatory'])
                                                <svg class="w-3.5 h-3.5 text-muted shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                    <title>Vom Betrieb festgelegt</title>
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                                </svg>
                                            @endif
                                        </div>
                                        <p class="text-xs text-muted mt-0.5">{{ $a['description'] }}</p>
                                        @if($a['mandatory'])
                                            <p class="text-xs text-muted mt-0.5">Vom Betrieb festgelegt.</p>
                                        @endif
                                    </div>

                                    <div class="inline-flex rounded-xl border border-border bg-input p-1 shrink-0" role="radiogroup" aria-label="Häufigkeit für {{ $a['label'] }}">
                                        @foreach($a['frequencies'] as $f)
                                            <label class="cursor-pointer">
                                                <input type="radio" name="frequenz[{{ $a['type'] }}]" value="{{ $f }}" class="peer sr-only"
                                                       @checked($a['aktuell'] === $f) @disabled($a['mandatory'])>
                                                <span class="block px-3 min-h-9 leading-9 rounded-lg text-xs sm:text-sm font-medium text-muted transition-colors
                                                             peer-checked:bg-accent peer-checked:text-white
                                                             peer-disabled:cursor-not-allowed peer-disabled:opacity-70
                                                             peer-focus-visible:ring-2 peer-focus-visible:ring-ring">
                                                    {{ $frequenzen[$f] }}
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                <div>
                    <button type="submit" :disabled="loading"
                            class="h-11 px-6 rounded-xl bg-accent text-white font-semibold np-btn-primary disabled:opacity-60 disabled:cursor-not-allowed">
                        Speichern
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
