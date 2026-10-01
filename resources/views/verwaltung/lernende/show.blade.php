<x-app-layout>
    <x-slot name="title">{{ $lernender->benutzer->vorname }} {{ $lernender->benutzer->nachname }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :zurueck="route($bereich.'.learners.index')" :titel="$lernender->benutzer->vorname.' '.$lernender->benutzer->nachname"
                       :untertitel="collect([$lernender->lehrberuf?->name, $lernender->lehrjahr() ? __(':jahr. Lehrjahr', ['jahr' => $lernender->lehrjahr()]) : null, $lernender->lehrende ? __('Lehre endet :datum', ['datum' => $lernender->lehrende->format('d.m.Y')]) : null])->filter()->implode(' · ')">
            <x-status :status="$stand->status" :title="$stand->gruende ? implode(', ', $stand->gruende) : __('Keine Auffälligkeiten')" />
            <x-slot:aktionen>
                <a href="{{ route("{$bereich}.learners.grades.index", $lernender->lernender_id) }}"
                   class="np-knopf np-knopf-primaer">
                    {{ $neu ? __('Noten ansehen (:anzahl neu)', ['anzahl' => $neu]) : __('Noten ansehen') }}
                </a>
                <a href="{{ route("{$bereich}.learners.grades.print", $lernender->lernender_id) }}" target="_blank"
                   class="np-knopf np-knopf-sekundaer">{{ __('Drucken') }}</a>
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button type="button" aria-label="{{ __('Weitere Aktionen') }}"
                                class="np-knopf np-knopf-sekundaer np-knopf-rund">
                            <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M4 10a1.5 1.5 0 113 0 1.5 1.5 0 01-3 0zm5 0a1.5 1.5 0 113 0 1.5 1.5 0 01-3 0zm5 0a1.5 1.5 0 113 0 1.5 1.5 0 01-3 0z"/></svg>
                        </button>
                    </x-slot>
                    <x-slot name="content">
                        <x-dropdown-link href="{{ route($bereich.'.learners.grades.export', $lernender->lernender_id) }}">CSV</x-dropdown-link>
                        @can('update', $lernender)
                            <x-dropdown-link href="{{ route($bereich.'.learners.edit', $lernender->lernender_id) }}">{{ __('Bearbeiten') }}</x-dropdown-link>
                        @endcan
                        <x-dropdown-link href="{{ route($bereich.'.learners.calculator', $lernender->lernender_id) }}">{{ __('Rechner') }}</x-dropdown-link>
                        @if($stand->auswertung->baeume)
                            <x-dropdown-link href="{{ route($bereich.'.learners.qualification', $lernender->lernender_id) }}">{{ __('Abschluss') }}</x-dropdown-link>
                        @endif
                    </x-slot>
                </x-dropdown>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="np-seite mx-auto px-8 flex flex-col gap-5">

            @if(session('startpasswort'))
                <div class="np-karte p-5 flex flex-wrap items-center justify-between gap-4"
                     x-data="{ kopiert: false }">
                    <div>
                        <div class="text-xs font-medium text-muted">{{ __('Startpasswort · wird nur einmal angezeigt') }}</div>
                        <div class="mt-1 font-mono text-2xl font-semibold tracking-wider text-text select-all" x-ref="pw">{{ session('startpasswort') }}</div>
                    </div>
                    <button type="button"
                            @click="if (await np.kopieren($refs.pw.textContent.trim())) { kopiert = true; setTimeout(() => kopiert = false, 2000) }"
                            class="np-knopf np-knopf-sekundaer">
                        <span x-show="!kopiert">{{ __('Kopieren') }}</span>
                        <span x-show="kopiert" x-cloak>{{ __('Kopiert') }}</span>
                    </button>
                </div>
            @endif

            <div x-data="{
                    tab: {{ \Illuminate\Support\Js::from(request('tab') === 'profil' ? 'profil' : 'overview') }},
                    wechsleTab(t) {
                        this.tab = t;
                        const u = new URL(window.location);
                        u.searchParams.set('tab', t);
                        history.replaceState(null, '', u);
                    },
                 }">
                <nav aria-label="{{ __('Bereiche') }}" class="np-segment">
                    <button type="button" :aria-current="tab === 'overview' ? 'page' : null" @click="wechsleTab('overview')">{{ __('Übersicht') }}</button>
                    <a href="{{ route("{$bereich}.learners.grades.index", $lernender->lernender_id) }}">{{ __('Noten') }}</a>
                    <a href="{{ route("{$bereich}.learners.documents.index", $lernender->lernender_id) }}">{{ __('Dokumente') }}</a>
                    <a href="{{ route("{$bereich}.learners.calculator", $lernender->lernender_id) }}">{{ __('Rechner') }}</a>
                    <button type="button" :aria-current="tab === 'profil' ? 'page' : null" @click="wechsleTab('profil')">{{ __('Profil & Betreuung') }}</button>
                </nav>

                <div class="pt-5" x-show="tab === 'overview'">
                    @include('verwaltung.lernende._cockpit.uebersicht')
                </div>
                <div class="pt-5" x-show="tab === 'profil'" x-cloak>
                    @include('verwaltung.lernende._cockpit.profil')
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
