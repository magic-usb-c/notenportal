{{-- $eintraege aus layouts/navigation übernehmen: ein zweiter Navigation::fuer()-Aufruf kostet Abfragen (AbfragenAnzahlTest) --}}
@props(['eintraege' => null, 'aktiv' => null])
@auth
    @php
        $u = auth()->user();
        // Persönlich abgeschaltet (Profil «Darstellung»): weder Listener noch Dialog rendern.
        $tastenkuerzelAktiv = $aktiv ?? \App\Support\Darstellung::fuer($u)['tastenkuerzel'] === \App\Support\Darstellung::TASTENKUERZEL_AN;
    @endphp
    @if($tastenkuerzelAktiv)
    @php
        $istLernender = $u->hasRole('Lernender');
        $eintraege ??= \App\Support\Navigation::fuer($u);
        $zielFuer = fn (string $icon) => collect($eintraege)->first(fn ($e) => ($e['icon'] ?? null) === $icon)['url'] ?? null;

        $ziele = [
            'd' => $zielFuer('start'),
            'n' => $zielFuer('noten'),
            'a' => $zielFuer('kalender'),
            'l' => $zielFuer('personen'),
            'p' => route('settings.profile'),
        ];

        $neueNoteUrl = $istLernender ? route('learner.grades.create') : null;

        $agendaLabel = $istLernender ?__('Zur Agenda') : __('Zu den Prüfungsterminen');

        $zeilen = collect([
            ['tasten' => ['?'], 'label' => __('Diese Übersicht öffnen')],
            ['tasten' => ['/'], 'label' => __('Suche öffnen')],
            $neueNoteUrl ? ['tasten' => ['n'], 'label' => __('Neue Note erfassen')] : null,
            $ziele['d'] ? ['tasten' => ['g', 'd'], 'label' => __('Zur Übersicht')] : null,
            $ziele['n'] ? ['tasten' => ['g', 'n'], 'label' => __('Zu den Noten')] : null,
            $ziele['a'] ? ['tasten' => ['g', 'a'], 'label' => $agendaLabel] : null,
            $ziele['l'] ? ['tasten' => ['g', 'l'], 'label' => __('Zu den Lernenden')] : null,
            $ziele['p'] ? ['tasten' => ['g', 'p'], 'label' => __('Zum Profil')] : null,
        ])->filter()->values();
    @endphp

    <div x-data="npTastenkuerzel({{ \Illuminate\Support\Js::from(['ziele' => $ziele, 'neueNote' => $neueNoteUrl]) }})"
         x-on:keydown.escape.window="offen && schliessen()">
        <template x-teleport="body">
            <div x-show="offen" x-cloak class="fixed inset-0 z-[100] flex items-start justify-center px-4 pt-[12vh]">
                <div class="absolute inset-0 glass-scrim" @click="schliessen()" x-show="offen" x-transition.opacity.duration.200ms aria-hidden="true"></div>

                <div x-ref="dialog"
                     x-show="offen" x-transition.opacity.duration.200ms
                     x-on:keydown.tab="haltFokusImDialog($event)"
                     role="dialog" aria-modal="true" aria-labelledby="tastenkuerzel-titel"
                     class="relative w-full max-w-md overflow-hidden rounded-2xl glass-overlay shadow-e3">
                    <div class="flex items-center justify-between border-b border-border px-5 py-3.5">
                        <h2 id="tastenkuerzel-titel" class="text-sm font-semibold text-text">{{ __('Tastenkürzel') }}</h2>
                        <button type="button" x-ref="schliessenKnopf" @click="schliessen()" aria-label="{{ __('Schliessen') }}"
                                class="np-knopf np-knopf-symbol -mr-1.5">
                            <svg class="size-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <ul class="max-h-[60vh] overflow-y-auto p-1.5">
                        @foreach($zeilen as $zeile)
                            <li class="flex items-center justify-between gap-3 rounded-lg px-3.5 py-2 text-sm text-text">
                                <span>{{ $zeile['label'] }}</span>
                                <span class="flex shrink-0 items-center gap-1">
                                    @foreach($zeile['tasten'] as $taste)
                                        @if(!$loop->first)<span class="text-2xs text-muted" aria-hidden="true">{{ __('dann') }}</span>@endif
                                        <kbd class="rounded-md border border-border px-1.5 py-0.5 text-2xs text-muted">{{ $taste }}</kbd>
                                    @endforeach
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </template>
    </div>
    @endif
@endauth
