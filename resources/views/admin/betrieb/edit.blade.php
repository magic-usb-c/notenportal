{{-- Betriebseinstellungen als Einstellungsfenster (macOS): Bereiche per Segment, gruppierte Listen,
     Schalter und Auswahlen gelten sofort, grosse Formulare haben je ein «Speichern». --}}
@php
    $bereiche = [
        'allgemein' => __('Allgemein'),
        'darstellung' => __('Darstellung'),
        'bedienung' => __('Bedienung'),
        'hinweis' => __('Systemhinweis'),
        'email' => __('E-Mail'),
        'sicherung' => __('Sicherung'),
    ];
    // Ohne Fragment (z. B. Aufruf von aussen) öffnet der Bereich mit dem ersten Validierungsfehler
    $standard = 'allgemein';
    foreach ($errors->keys() as $feld) {
        $standard = match (true) {
            str_starts_with($feld, 'mail_') || $feld === 'test_to' => 'email',
            str_starts_with($feld, 'hinweis_') => 'hinweis',
            str_starts_with($feld, 'sicherung_') => 'sicherung',
            in_array($feld, ['theme', 'logo'], true) => 'darstellung',
            in_array($feld, ['sitzung_minuten', 'sprache_standard', 'sprachwahl_aktiv', 'feedback_knopf'], true) => 'bedienung',
            default => 'allgemein',
        };
        break;
    }
    $veraltet = $letzteSicherung && $letzteSicherung->lt(now()->subDays(2));
    $groesse = fn (int $b) => $b >= 1048576 ? number_format($b / 1048576, 1).' MB' : max(1, (int) round($b / 1024)).' KB';
@endphp
<x-app-layout>
    <x-slot name="title">{{ __('Einstellungen') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :titel="__('Einstellungen')" schmal>
            <nav class="np-segment" aria-label="{{ __('Bereiche') }}" x-data="npBereiche(@js($standard), @js(array_keys($bereiche)))">
                @foreach($bereiche as $schluessel => $text)
                    <a href="#{{ $schluessel }}" @click.prevent="wechseln(@js($schluessel))"
                       :aria-current="bereich === @js($schluessel) ? 'true' : null" @if($schluessel === $standard) aria-current="true" @endif>{{ $text }}</a>
                @endforeach
            </nav>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-4 sm:px-6 lg:px-8">
        <div class="max-w-3xl" x-data="npBereiche(@js($standard), @js(array_keys($bereiche)))">

            <div x-show="bereich === 'allgemein'" x-cloak>
                <form method="POST" action="{{ route('admin.operations.update') }}#allgemein" class="flex flex-col gap-8"
                      x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                    @csrf
                    @method('PUT')
                    @include('admin.betrieb._felder', ['werte' => $werte])
                    <div class="flex justify-end">
                        <button type="submit" :disabled="loading" class="np-knopf np-knopf-primaer">{{ __('Speichern') }}</button>
                    </div>
                </form>
            </div>

            <div x-show="bereich === 'darstellung'" x-cloak class="flex flex-col gap-8">
                @include('admin.betrieb._theme', ['theme' => old('theme', $theme)])
                @include('admin.betrieb._logo', ['logoVorhanden' => $logoVorhanden])
            </div>

            <div x-show="bereich === 'bedienung'" x-cloak class="flex flex-col gap-8">
                @include('admin.betrieb._sprache')
                @include('admin.betrieb._sitzung')
            </div>

            <div x-show="bereich === 'hinweis'" x-cloak>
                @include('admin.betrieb._hinweis')
            </div>

            <div x-show="bereich === 'email'" x-cloak class="flex flex-col gap-8">
                <form method="POST" action="{{ route('admin.mail.update') }}#email" class="flex flex-col gap-8"
                      x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                    @csrf
                    @method('PUT')
                    @include('admin.betrieb._mail', ['werte' => $mailWerte])
                    <div class="flex justify-end">
                        <button type="submit" :disabled="loading" class="np-knopf np-knopf-primaer">{{ __('Speichern') }}</button>
                    </div>
                </form>

                @include('admin.betrieb._testmail', ['testTo' => $testTo])

                <nav aria-label="{{ __('Mehr zu E-Mail') }}" class="np-karte np-gruppe overflow-hidden">
                    @foreach([[route('admin.mail-log.index'), __('Versandprotokoll')], [route('admin.notifications.index'), __('Benachrichtigungen')]] as [$url, $text])
                        <a href="{{ $url }}" class="flex items-center justify-between gap-4 px-4 py-3 text-sm text-text transition-colors duration-100 hover:bg-surface-2/60">
                            {{ $text }}
                            <x-symbol name="chevron-right" class="size-4 text-faint" />
                        </a>
                    @endforeach
                </nav>
            </div>

            <div x-show="bereich === 'sicherung'" x-cloak class="flex flex-col gap-8">
                <section>
                    <div class="mb-2 flex items-end justify-between gap-4 px-1">
                        <div class="min-w-0">
                            <h2 class="text-sm font-semibold text-text">{{ __('Sicherungen') }}</h2>
                            <p @class(['mt-0.5 text-xs', 'text-muted' => ! $veraltet && ! $sicherungFehler, 'text-note-ungenuegend' => $veraltet || $sicherungFehler])>
                                @if($sicherungFehler)
                                    {{ __('Letzter Versuch fehlgeschlagen: :fehler', ['fehler' => $sicherungFehler]) }}
                                @elseif($letzteSicherung)
                                    {{ __('Letzte Sicherung :datum', ['datum' => $letzteSicherung->timezone(config('app.timezone'))->format('d.m.Y H:i')]) }}@if($veraltet) {{ __('– älter als zwei Tage') }} @endif
                                @else
                                    {{ __('Noch keine Sicherung') }}
                                @endif
                            </p>
                        </div>
                        <form method="POST" action="{{ route('admin.operations.backups.store') }}#sicherung" class="shrink-0"
                              x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                            @csrf
                            <button type="submit" :disabled="loading" class="np-knopf np-knopf-sekundaer np-knopf-klein">
                                <span x-show="loading" x-cloak class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-accent border-t-transparent" aria-hidden="true"></span>
                                {{ __('Jetzt sichern') }}
                            </button>
                        </form>
                    </div>
                    @if($sicherungen)
                        <ul class="np-karte np-gruppe">
                            @foreach($sicherungen as $s)
                                @php $datum = $s['datum']->format('d.m.Y H:i'); @endphp
                                <li class="flex items-center gap-3 py-1.5 pr-2 pl-4">
                                    <span class="min-w-0 flex-1 text-sm text-text tabular-nums">{{ $datum }}</span>
                                    <span class="w-20 text-right text-xs text-muted tabular-nums">{{ $groesse($s['groesse']) }}</span>
                                    <a href="{{ route('admin.operations.backups.show', $s['name']) }}" aria-label="{{ __('Sicherung :datum herunterladen', ['datum' => $datum]) }}"
                                       title="{{ __('Herunterladen') }}" class="np-knopf np-knopf-symbol"><x-symbol name="arrow-down-tray" class="size-4" /></a>
                                    <form method="POST" action="{{ route('admin.operations.backups.destroy', $s['name']) }}#sicherung" data-bestaetigen="{{ __('Sicherung löschen?') }}" data-bestaetigen-knopf="{{ __('Löschen') }}"
                                          x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                                        @csrf
                                        @method('DELETE')
                                        <button :disabled="loading" aria-label="{{ __('Sicherung :datum löschen', ['datum' => $datum]) }}" title="{{ __('Löschen') }}"
                                                class="np-knopf np-knopf-symbol np-knopf-symbol-gefahr"><x-symbol name="trash" class="size-4" /></button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>

                @include('admin.betrieb._kopie', ['kopie' => $kopie, 'werte' => $kopieWerte, 'schluessel_oeffentlich' => $kopieSchluessel])
            </div>
        </div>
        </div>
    </div>
</x-app-layout>
