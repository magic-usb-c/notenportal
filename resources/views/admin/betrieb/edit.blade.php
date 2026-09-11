<x-app-layout>
    <x-slot name="title">{{ __('Betrieb') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf titel="{{ __('Betrieb') }}" schmal>
            <x-slot:aktionen>
                <a href="{{ route('admin.setup') }}" class="inline-flex h-9 items-center gap-2 rounded-lg glass-btn px-3.5 text-sm font-medium text-text">{{ __('Einrichtung') }}</a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="max-w-3xl">
            <form method="POST" action="{{ route('admin.operations.update') }}" class="rounded-xl border border-border bg-card p-6 flex flex-col gap-6"
                  x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                @csrf
                @method('PUT')
                @include('admin.betrieb._felder', ['werte' => $werte])
                <div class="flex justify-end">
                    <button type="submit" :disabled="loading" class="inline-flex items-center px-5 h-10 rounded-xl bg-accent text-accent-contrast text-sm font-semibold np-btn-primary disabled:opacity-60">{{ __('Speichern') }}</button>
                </div>
            </form>

            @include('admin.betrieb._theme', ['theme' => old('theme', $theme)])
            @include('admin.betrieb._sprache')
            @include('admin.betrieb._logo', ['logoVorhanden' => $logoVorhanden])
            @include('admin.betrieb._hinweis')
            @include('admin.betrieb._sitzung')

            <section class="rounded-xl border border-border bg-card p-6 flex flex-col gap-5 mt-5">
                <h3 class="text-sm font-semibold text-text">{{ __('E-Mail') }}</h3>
                <form method="POST" action="{{ route('admin.mail.update') }}" class="flex flex-col gap-5"
                      x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                    @csrf
                    @method('PUT')
                    @include('admin.betrieb._mail', ['werte' => $mailWerte])
                    <div class="flex justify-end">
                        <button type="submit" :disabled="loading" class="inline-flex items-center px-5 h-10 rounded-xl bg-accent text-accent-contrast text-sm font-semibold np-btn-primary disabled:opacity-60">{{ __('Speichern') }}</button>
                    </div>
                </form>

                <div class="pt-5 border-t border-border flex flex-col gap-3">
                    @include('admin.betrieb._testmail', ['testTo' => $testTo])
                </div>

                <div class="pt-1 flex flex-wrap gap-4 text-sm">
                    <a href="{{ route('admin.mail-log.index') }}" class="text-accent hover:underline">{{ __('Versandprotokoll') }}</a>
                    <a href="{{ route('admin.notifications.index') }}" class="text-accent hover:underline">{{ __('Benachrichtigungen') }}</a>
                </div>
            </section>

            @php
                $veraltet = $letzteSicherung && $letzteSicherung->lt(now()->subDays(2));
                $groesse = fn (int $b) => $b >= 1048576 ? number_format($b / 1048576, 1).' MB' : max(1, (int) round($b / 1024)).' KB';
            @endphp
            <section class="rounded-xl border border-border bg-card overflow-hidden mt-5">
                <div class="px-6 pt-5 pb-4 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-semibold text-text">{{ __('Sicherungen') }}</h3>
                        <p @class(['text-xs mt-0.5', 'text-muted' => ! $veraltet && ! $sicherungFehler, 'text-note-ungenuegend' => $veraltet || $sicherungFehler])>
                            @if($sicherungFehler)
                                {{ __('Letzter Versuch fehlgeschlagen: :fehler', ['fehler' => $sicherungFehler]) }}
                            @elseif($letzteSicherung)
                                {{ __('Letzte Sicherung :datum', ['datum' => $letzteSicherung->timezone(config('app.timezone'))->format('d.m.Y H:i')]) }}@if($veraltet) {{ __('– älter als zwei Tage') }} @endif
                            @else
                                {{ __('Noch keine Sicherung') }}
                            @endif
                        </p>
                    </div>
                    <form method="POST" action="{{ route('admin.operations.backups.store') }}" x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                        @csrf
                        <button type="submit" :disabled="loading" class="inline-flex items-center gap-2 px-4 h-10 rounded-xl glass-btn text-text text-sm disabled:opacity-60">
                            <span x-show="loading" x-cloak class="w-4 h-4 rounded-full border-2 border-accent border-t-transparent animate-spin" aria-hidden="true"></span>
                            {{ __('Jetzt sichern') }}
                        </button>
                    </form>
                </div>
                @if($sicherungen)
                    <ul class="divide-y divide-border border-t border-border">
                        @foreach($sicherungen as $s)
                            <li class="flex items-center gap-3 px-6 py-2.5">
                                <span class="flex-1 min-w-0 text-sm text-text tabular-nums">{{ $s['datum']->format('d.m.Y H:i') }}</span>
                                <span class="text-xs text-muted tabular-nums">{{ $groesse($s['groesse']) }}</span>
                                <a href="{{ route('admin.operations.backups.show', $s['name']) }}" aria-label="{{ __('Sicherung :datum herunterladen', ['datum' => $s['datum']->format('d.m.Y H:i')]) }}"
                                   class="w-9 h-9 inline-flex items-center justify-center rounded-lg text-muted hover:text-text hover:bg-accent/10">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v12m0 0l-4-4m4 4l4-4M4 20h16"/></svg>
                                </a>
                                <form method="POST" action="{{ route('admin.operations.backups.destroy', $s['name']) }}" onsubmit="return confirm(@js(__('Sicherung löschen?')))"
                                      x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                                    @csrf
                                    @method('DELETE')
                                    <button :disabled="loading" aria-label="{{ __('Sicherung :datum löschen', ['datum' => $s['datum']->format('d.m.Y H:i')]) }}"
                                            class="w-9 h-9 inline-flex items-center justify-center rounded-lg text-muted hover:text-note-ungenuegend hover:bg-note-ungenuegend/10 disabled:opacity-60">×</button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @endif
                @include('admin.betrieb._kopie', ['kopie' => $kopie, 'werte' => $kopieWerte, 'schluessel_oeffentlich' => $kopieSchluessel])
            </section>
        </div>
        </div>
    </div>
</x-app-layout>
