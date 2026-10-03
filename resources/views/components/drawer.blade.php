@props([
    'name',
    'titel' => null,
    'breite' => 'md',
    'offen' => false,   // beim Laden geöffnet (z. B. ?planen=1) – ein open-drawer aus einem äusseren init() käme vor dem Listener
])

@php
    $breiten = ['sm' => 'w-[22rem]', 'md' => 'w-[28rem]', 'lg' => 'w-[36rem]'][$breite] ?? 'w-[28rem]';
@endphp

{{--
    Generischer Drawer (Erfassen, Bearbeiten, Planen): schwebt rechts mit Abstand zum Fensterrand wie die
    Seitenleiste links, Scrim schliesst, Esc schliesst. Der Fokus springt ins erste Eingabefeld (wie ein
    macOS-Sheet), bleibt innerhalb und kehrt danach zum Auslöser zurück. Öffnen: $dispatch('open-drawer', 'name').
    Bewegung (B3): reine CSS-Transition über Klassen am Zustand «offen», darum mitten im Lauf umkehrbar – öffnen
    300 ms ease-out, schliessen 200 ms ease-in, keine Feder. Sichtbarkeit wechselt beim Öffnen sofort (der Fokus
    greift gleich), beim Schliessen erst nach der Bewegung. Unter ruhiger Bewegung nur Überblendung.
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
        startFokus() { return this.focusables().find(el => el.matches('input, select, textarea')) || this.ersterFokus() },
        letzterFokus() { return this.focusables().slice(-1)[0] },
        naechsterFokus() { return this.focusables()[(this.focusables().indexOf(document.activeElement) + 1) % (this.focusables().length + 1)] || this.ersterFokus() },
        vorherigerFokus() { return this.focusables()[Math.max(0, this.focusables().indexOf(document.activeElement)) - 1] || this.letzterFokus() },
    }"
    x-init="offen && $nextTick(() => startFokus()?.focus()); $watch('offen', v => { if (! v && ausloeser?.isConnected) { ausloeser.focus(); ausloeser = null } })"
    x-on:open-drawer.window="if ($event.detail === '{{ $name }}') { ausloeser = document.activeElement; offen = true; $nextTick(() => startFokus()?.focus()) }"
    x-on:close-drawer.window="$event.detail === '{{ $name }}' ? offen = false : null"
    x-on:keydown.escape.window="if (offen && window.np.escapeGilt($el, $event)) offen = false"
    x-on:keydown.tab.prevent="$event.shiftKey || naechsterFokus().focus()"
    x-on:keydown.shift.tab.prevent="vorherigerFokus().focus()"
    x-cloak
    :inert="! offen"
    :class="offen ? 'visible' : 'invisible pointer-events-none transition-[visibility] duration-200 ruhig:duration-150'"
    class="fixed inset-0 z-[70]"
>
    <div
        x-on:click="offen = false"
        :class="offen ? 'opacity-100 transition-opacity duration-300 ease-out ruhig:duration-150' : 'opacity-0 transition-opacity duration-200 ease-in ruhig:duration-150'"
        class="absolute inset-0 glass-scrim"
        aria-hidden="true"
    ></div>

    <aside
        :class="offen
            ? 'translate-x-0 opacity-100 transition-[translate,opacity] duration-300 ease-out ruhig:duration-150'
            : 'translate-x-[calc(100%+1rem)] ruhig:translate-x-0 opacity-100 ruhig:opacity-0 transition-[translate,opacity] duration-200 ease-in ruhig:duration-150'"
        role="dialog"
        aria-modal="true"
        @if($titel) aria-label="{{ $titel }}" @endif
        class="absolute inset-y-2 right-2 {{ $breiten }} flex flex-col overflow-hidden rounded-2xl border border-border bg-card shadow-e3"
    >
        @if($titel || isset($kopf))
            <div class="flex items-center justify-between gap-3 px-5 h-14 shrink-0 border-b border-border">
                <h2 class="truncate text-base font-semibold text-text">{{ $kopf ?? $titel }}</h2>
                <button type="button" @click="offen = false" aria-label="{{ __('Schliessen') }}"
                        class="np-knopf np-knopf-symbol shrink-0">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        @endif

        {{-- Die eine Scrollkante dieser Ansicht: Inhalt blendet unter Kopf und Fuss aus (G7) --}}
        <div class="np-scroll-edge flex-1 overflow-y-auto px-5 py-5">
            {{ $slot }}
        </div>

        @isset($footer)
            <div class="shrink-0 border-t border-border px-5 py-4">
                {{ $footer }}
            </div>
        @endisset
    </aside>
</div>
