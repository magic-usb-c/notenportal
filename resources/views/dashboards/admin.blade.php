<x-app-layout>
    <x-slot name="title">{{ __('Übersicht') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :titel="__('Betrieb')" :untertitel="\App\Support\Format::date(now())">
            <x-slot:aktionen>
                <a href="{{ route('admin.users.create') }}" class="inline-flex h-9 items-center gap-2 rounded-lg glass-btn px-3.5 text-sm font-medium text-text">{{ __('Benutzer anlegen') }}</a>
                <a href="{{ route('admin.learners.create') }}" class="inline-flex h-9 items-center gap-2 rounded-lg bg-accent px-3.5 text-sm font-medium text-accent-contrast np-btn-primary">
                    <span class="text-lg leading-none">+</span> {{ __('Lernende') }}
                </a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="np-raster np-seite mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-12 gap-5">

            {{-- Statuszeile --}}
            <div class="lg:col-span-12 flex flex-wrap items-baseline gap-x-6 gap-y-2 rounded-xl border border-border bg-card px-5 py-3">
                <a href="{{ route('admin.learners.index') }}" class="flex items-baseline gap-1.5 hover:opacity-80">
                    <span class="text-2xl font-semibold tabular-nums text-text">{{ $kennzahlen['lernende'] }}</span>
                    <span class="text-xs text-muted">{{ __('Lernende') }}</span>
                </a>
                <a href="{{ route('admin.trainers.index') }}" class="flex items-baseline gap-1.5 hover:opacity-80">
                    <span class="text-2xl font-semibold tabular-nums text-text">{{ $kennzahlen['berufsbildner'] }}</span>
                    <span class="text-xs text-muted">{{ __('Berufsbildner') }}</span>
                </a>
                <a href="{{ route('admin.reports.grades') }}" class="flex items-baseline gap-1.5 hover:opacity-80">
                    <span class="text-2xl font-semibold tabular-nums text-text">{{ $kennzahlen['noten_semester'] }}</span>
                    <span class="text-xs text-muted">{{ __('Noten :semester', ['semester' => $kennzahlen['semester']]) }}</span>
                </a>
                <span class="flex items-baseline gap-1.5">
                    <span class="text-2xl font-semibold tabular-nums {{ $kennzahlen['rot'] ? 'text-note-ungenuegend' : 'text-text' }}">{{ $kennzahlen['rot'] }}</span>
                    <span class="text-xs text-muted">{{ __('kritisch · :gelb beobachten', ['gelb' => $kennzahlen['gelb']]) }}</span>
                </span>
                <a href="{{ route('admin.feedback.index') }}" class="flex items-baseline gap-1.5 hover:opacity-80">
                    <span class="text-2xl font-semibold tabular-nums {{ $kennzahlen['feedback'] ? 'text-accent-text' : 'text-text' }}">{{ $kennzahlen['feedback'] }}</span>
                    <span class="text-xs text-muted">{{ __('offene Meldungen') }}</span>
                </a>
            </div>

            {{-- Handlungsbedarf: eine Liste statt mehrerer Karten (Einrichtungslücken, Sicherung, Meldungen, kritische Lernende) --}}
            @if(($sichtbar['handlungsbedarf'] ?? true) && $handlungsbedarf)
                <section class="lg:col-span-12 rounded-xl border border-border bg-card overflow-hidden">
                    <h3 class="px-5 pt-4 pb-2 text-sm font-semibold text-text">{{ __('Handlungsbedarf') }}</h3>
                    <div class="divide-y divide-border/70">
                        @foreach($handlungsbedarf as $h)
                            <a href="{{ $h['link'] }}" class="px-5 py-2.5 flex items-center justify-between gap-3 hover:bg-accent/5 transition-colors">
                                <div class="min-w-0">
                                    <div class="text-sm text-text break-words sm:truncate">{{ $h['text'] }}</div>
                                    @if($h['meta'])
                                        <div class="text-xs text-muted break-words sm:truncate">{{ $h['meta'] }}</div>
                                    @endif
                                </div>
                                <span class="flex shrink-0 items-center gap-3">
                                    @if($h['note'] !== null)
                                        <x-note :wert="$h['note']" variante="badge" />
                                    @elseif($h['badge'] !== null)
                                        <span @class(['px-2 py-0.5 rounded-full text-[11px] font-bold tabular-nums',
                                            'bg-note-ungenuegend/15 text-note-ungenuegend' => $h['ton'] === 'rot',
                                            'bg-note-knapp/15 text-note-knapp' => $h['ton'] === 'gelb',
                                            'bg-accent/15 text-accent-text' => $h['ton'] === 'accent',
                                        ])>{{ $h['badge'] }}</span>
                                    @endif
                                    <span class="text-xs text-accent-text">{{ __('Beheben ›') }}</span>
                                </span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- Berufsbildner --}}
            @if($sichtbar['berufsbildner'] ?? true)
                <x-karte :titel="__('Berufsbildner')" class="lg:col-span-7" :polster="false" :link="route('admin.trainers.index')">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm tabular-nums">
                            <thead class="sticky top-0 bg-surface-2">
                                <tr>
                                    <th scope="col" class="h-9 px-2 sm:px-3 sm:px-5 text-left text-2xs font-medium text-muted">{{ __('Name') }}</th>
                                    <th scope="col" class="hidden sm:table-cell h-9 px-2 sm:px-3 text-right text-2xs font-medium text-muted">{{ __('Lernende') }}</th>
                                    <th scope="col" class="h-9 px-2 sm:px-3 text-right text-2xs font-medium text-muted">{{ __('Kritisch') }}</th>
                                    <th scope="col" class="h-9 px-2 sm:px-3 text-right text-2xs font-medium text-muted">{{ __('Beobachten') }}</th>
                                    <th scope="col" class="h-9 px-2 sm:px-3 sm:px-5 text-right text-2xs font-medium text-muted">{{ __('Ungesehen') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                @forelse($proBb as $bb)
                                    <tr class="h-11">
                                        <td class="px-2 sm:px-3 sm:px-5 text-text">
                                            {{ $bb->name }}
                                            <span class="block text-xs text-muted sm:hidden">{{ $bb->lernende }} {{ __('Lernende') }}</span>
                                        </td>
                                        <td class="hidden sm:table-cell px-2 sm:px-3 text-right text-muted">{{ $bb->lernende }}</td>
                                        <td class="px-2 sm:px-3 text-right {{ $bb->rot ? 'text-note-ungenuegend font-semibold' : 'text-muted' }}">{{ $bb->rot }}</td>
                                        <td class="px-2 sm:px-3 text-right {{ $bb->gelb ? 'text-note-knapp font-semibold' : 'text-muted' }}">{{ $bb->gelb }}</td>
                                        <td class="px-2 sm:px-3 sm:px-5 text-right {{ $bb->neu > 20 ? 'text-accent-text font-semibold' : 'text-muted' }}">{{ $bb->neu }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="px-5 py-8 text-center text-muted">{{ __('Noch keine Berufsbildner') }}</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-karte>
            @endif

            @if($sichtbar['aktivitaet'] ?? true)
                <x-karte :titel="__('Erfasste Noten pro Woche')" class="lg:col-span-5">
                    <div class="h-52" x-data="npChart('saeulen', {{ \Illuminate\Support\Js::from(['labels' => $aktivitaet['labels'], 'werte' => $aktivitaet['werte'], 'name' => __('Noten')]) }})">
                        <canvas x-ref="canvas" role="img" aria-label="{{ __('Erfasste Noten pro Woche') }}"></canvas>
                    </div>
                </x-karte>
            @endif

            @if(($sichtbar['lehrende'] ?? true) && $lehrende->isNotEmpty())
                <x-karte :titel="__('Lehrende bald')" class="lg:col-span-12" :polster="false">
                    <div class="divide-y divide-border/70">
                        @foreach($lehrende as $l)
                            <a href="{{ route('admin.learners.show', $l->lernender_id) }}" class="px-5 py-2.5 flex items-center justify-between gap-3 hover:bg-accent/5 transition-colors">
                                <span class="text-sm text-text">{{ $l->benutzer->vorname }} {{ $l->benutzer->nachname }} <span class="text-muted">· {{ $l->lehrberuf?->kuerzel }}</span></span>
                                <span class="text-xs text-muted tabular-nums">{{ $l->lehrende->format('d.m.Y') }}</span>
                            </a>
                        @endforeach
                    </div>
                </x-karte>
            @endif
        </div>
    </div>
</x-app-layout>
