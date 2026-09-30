<x-app-layout>
    <x-slot name="title">{{ $lernender->benutzer->vorname }} {{ $lernender->benutzer->nachname }}</x-slot>
    <x-slot name="header">
        <nav class="mb-1 flex items-center gap-1 text-xs text-muted" aria-label="{{ __('Brotkrumen') }}">
            <a href="{{ route("{$bereich}.learners.index") }}" class="transition-colors hover:text-text">{{ __('Lernende') }}</a>
            <span class="text-muted/40">›</span>
            <span class="text-text">{{ $lernender->benutzer->vorname }} {{ $lernender->benutzer->nachname }}</span>
        </nav>
        <x-seitenkopf :titel="$lernender->benutzer->vorname.' '.$lernender->benutzer->nachname"
                       :untertitel="collect([$lernender->lehrberuf?->name, $lernender->lehrjahr() ? __(':jahr. Lehrjahr', ['jahr' => $lernender->lehrjahr()]) : null, $lernender->lehrende ? __('Lehrende :datum', ['datum' => $lernender->lehrende->format('d.m.Y')]) : null])->filter()->implode(' · ')">
            <x-status :status="$stand->status" :title="$stand->gruende ? implode(', ', $stand->gruende) : __('Keine Auffälligkeiten')" />
            <x-slot:aktionen>
                <a href="{{ route("{$bereich}.learners.grades.index", $lernender->lernender_id) }}"
                   class="inline-flex h-9 items-center gap-2 rounded-lg bg-accent px-3.5 text-sm font-medium text-accent-contrast np-btn-primary">
                    {{ $neu ? __('Noten ansehen (:anzahl neu)', ['anzahl' => $neu]) : __('Noten ansehen') }}
                </a>
                <a href="{{ route("{$bereich}.learners.grades.print", $lernender->lernender_id) }}" target="_blank"
                   class="inline-flex h-9 items-center gap-2 rounded-lg glass-btn px-3.5 text-sm font-medium text-text">{{ __('Drucken') }}</a>
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button type="button" aria-label="{{ __('Weitere Aktionen') }}"
                                class="inline-flex h-9 w-9 items-center justify-center rounded-lg glass-btn text-text">
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
        <div class="np-seite mx-auto px-4 sm:px-6 lg:px-8 flex flex-col gap-5">

            @if(session('startpasswort'))
                <div class="rounded-xl border border-border bg-card p-5 flex flex-wrap items-center justify-between gap-4"
                     x-data="{ kopiert: false }">
                    <div>
                        <div class="text-xs font-medium text-muted">{{ __('Startpasswort · wird nur einmal angezeigt') }}</div>
                        <div class="mt-1 font-mono text-2xl font-bold tracking-wider text-text select-all" x-ref="pw">{{ session('startpasswort') }}</div>
                    </div>
                    <button type="button"
                            @click="if (await np.kopieren($refs.pw.textContent.trim())) { kopiert = true; setTimeout(() => kopiert = false, 2000) }"
                            class="px-4 h-10 rounded-xl glass-btn text-text text-sm">
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
                <nav aria-label="{{ __('Bereiche') }}" class="flex gap-6 overflow-x-auto border-b border-border text-sm">
                    <button type="button" :aria-current="tab === 'overview' ? 'page' : null" @click="wechsleTab('overview')"
                            class="-mb-px inline-flex h-10 shrink-0 items-center border-b-2 font-medium"
                            :class="tab === 'overview' ? 'border-accent text-text' : 'border-transparent text-muted hover:text-text'">{{ __('Übersicht') }}</button>
                    <a href="{{ route("{$bereich}.learners.grades.index", $lernender->lernender_id) }}"
                       class="-mb-px inline-flex h-10 shrink-0 items-center border-b-2 border-transparent text-muted hover:border-border-strong/50 hover:text-text">{{ __('Noten') }}</a>
                    <a href="{{ route("{$bereich}.learners.documents.index", $lernender->lernender_id) }}"
                       class="-mb-px inline-flex h-10 shrink-0 items-center border-b-2 border-transparent text-muted hover:border-border-strong/50 hover:text-text">{{ __('Dokumente') }}</a>
                    <a href="{{ route("{$bereich}.learners.calculator", $lernender->lernender_id) }}"
                       class="-mb-px inline-flex h-10 shrink-0 items-center border-b-2 border-transparent text-muted hover:border-border-strong/50 hover:text-text">{{ __('Rechner') }}</a>
                    <button type="button" :aria-current="tab === 'profil' ? 'page' : null" @click="wechsleTab('profil')"
                            class="-mb-px inline-flex h-10 shrink-0 items-center border-b-2 font-medium whitespace-nowrap"
                            :class="tab === 'profil' ? 'border-accent text-text' : 'border-transparent text-muted hover:text-text'">{{ __('Profil & Betreuung') }}</button>
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
