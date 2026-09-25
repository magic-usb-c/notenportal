@props([
    'name',
    'titel' => null,
    'breite' => 'md',
    'offen' => false,   // beim Laden geöffnet (z. B. ?planen=1) – ein open-drawer aus einem äusseren init() käme vor dem Listener
])

@php
    $breiten = ['sm' => 'sm:w-[22rem]', 'md' => 'sm:w-[28rem]', 'lg' => 'sm:w-[36rem]'][$breite] ?? 'sm:w-[28rem]';
@endphp

{{--
    Generischer Drawer (Erfassen, Bearbeiten, Planen): schwebt rechts über der Seite, Scrim schliesst,
    Esc schliesst, Fokus bleibt innerhalb und kehrt danach zum Auslöser zurück. Öffnen per Alpine-Event: $dispatch('open-drawer', 'name').
--}}
<div
    x-data="{
        offen: @js((bool) $offen),
        ausloeser: null,
        focusables() {
            let selector = 'a, button, input:not([type=\'hidden\']), textarea, select, [tabindex]:not([tabindex=\'-1\'])'
            return [...$el.querySelectorAll(selector)].filter(el => ! el.hasAttribute('disabled') && el.getClientRects().length > 0)
        },
        ersterFokus() { return this.focusables()[0] },
        letzterFokus() { return this.focusables().slice(-1)[0] },
        naechsterFokus() { return this.focusables()[(this.focusables().indexOf(document.activeElement) + 1) % (this.focusables().length + 1)] || this.ersterFokus() },
        vorherigerFokus() { return this.focusables()[Math.max(0, this.focusables().indexOf(document.activeElement)) - 1] || this.letzterFokus() },
    }"
    x-init="offen && $nextTick(() => ersterFokus()?.focus()); $watch('offen', v => { if (! v && ausloeser?.isConnected) { ausloeser.focus(); ausloeser = null } })"
    x-on:open-drawer.window="if ($event.detail === '{{ $name }}') { ausloeser = document.activeElement; offen = true; $nextTick(() => ersterFokus()?.focus()) }"
    x-on:close-drawer.window="$event.detail === '{{ $name }}' ? offen = false : null"
    x-on:keydown.escape.window="offen = false"
    x-on:keydown.tab.prevent="$event.shiftKey || naechsterFokus().focus()"
    x-on:keydown.shift.tab.prevent="vorherigerFokus().focus()"
    x-show="offen"
    x-cloak
    class="fixed inset-0 z-[70]"
    style="display: none;"
>
    <div
        x-show="offen"
        x-transition:enter="transition-opacity ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition-opacity ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        x-on:click="offen = false"
        class="absolute inset-0 glass-scrim"
        aria-hidden="true"
    ></div>

    <aside
        x-show="offen"
        x-transition:enter="transition-transform ease-out duration-200"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition-transform ease-in duration-150"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        role="dialog"
        aria-modal="true"
        @if($titel) aria-label="{{ $titel }}" @endif
        class="absolute inset-y-0 right-0 w-full {{ $breiten }} bg-card border-l border-border shadow-e3 flex flex-col"
    >
        @if($titel || isset($kopf))
            <div class="flex items-center justify-between gap-3 px-5 h-14 shrink-0 border-b border-border">
                <h2 class="font-semibold text-text truncate">{{ $kopf ?? $titel }}</h2>
                <button type="button" @click="offen = false" aria-label="{{ __('Schliessen') }}"
                        class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-muted hover:text-text hover:bg-surface-2 shrink-0">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        @endif

        <div class="flex-1 overflow-y-auto px-5 py-5">
            {{ $slot }}
        </div>

        @isset($footer)
            <div class="shrink-0 border-t border-border px-5 py-4">
                {{ $footer }}
            </div>
        @endisset
    </aside>
</div>
