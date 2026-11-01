@props([
    'align' => 'right',
    'width' => '48',
    'contentClasses' => 'p-1 text-text',
])

@php
$alignmentClasses = match ($align) {
    'left' => 'inset-s-0',
    'top' => '',
    default => 'inset-e-0',
};

$width = match ($width) {
    '48' => 'w-48',
    '56' => 'w-56',
    '64' => 'w-64',
    default => $width,
};
@endphp

{{-- Menü (G9, B1): wächst aus seinem Auslöser. Das Öffnen läuft über @starting-style von glass-overlay, Startmass und
     Ursprung setzt window.npMorph (morphStarten in np.js, an window gehängt in app.js); nur das Ausblenden läuft über Alpine (Deckkraft, 150 ms).
     Escape schliesst und gibt den Fokus an den Auslöser zurück. Pfeil runter/hoch am Auslöser und Enter/Leertaste öffnen mit Fokus auf
     dem ersten (bzw. letzten) Eintrag; im Menü bewegen Pfeil hoch/runter, Pos1 und Ende den Fokus (Umlauf), Tab verlässt es über focusout. --}}
<div class="relative" x-data="{ open: false,
        eintraege() { return Array.from(this.$refs.panel.querySelectorAll('a[href], button:not([disabled]):not([role=radio]):not([tabindex^=\'-\'])')).filter((e) => e.getClientRects().length > 0) },
        fokus(wo) { this.$nextTick(() => { const liste = this.eintraege(); (wo === 'letzter' ? liste[liste.length - 1] : liste[0])?.focus() }) },
        menueTaste(e) {
            if (e.defaultPrevented || e.altKey || e.ctrlKey || e.metaKey || e.shiftKey || e.target.closest('input, textarea, select')) return;
            const liste = this.eintraege(); const i = liste.indexOf(e.target.closest('a[href], button'));
            const ziel = { ArrowDown: liste[(i + 1) % liste.length], ArrowUp: liste[i < 0 ? liste.length - 1 : (i - 1 + liste.length) % liste.length], Home: liste[0], End: liste[liste.length - 1] }[e.key];
            if (! ziel) return; e.preventDefault(); ziel.focus();
        } }"
     x-init="$watch('open', (v) => v && $nextTick(() => window.npMorph?.($refs.ausloeser.querySelector('button, a') ?? $refs.ausloeser, $refs.panel)))"
     @click.outside="open = false" @focusout="if ($event.relatedTarget && ! $el.contains($event.relatedTarget)) open = false" @keydown.escape.window="if (open) { open = false; $refs.ausloeser.querySelector('button, a')?.focus() }" @close.stop="open = false">
    <div x-ref="ausloeser" @click="open = ! open; if (open && $event.detail === 0) fokus('erster')"
         @keydown.arrow-down.prevent="open = true; fokus('erster')" @keydown.arrow-up.prevent="open = true; fokus('letzter')"
         x-init="$el.querySelector('button')?.setAttribute('aria-haspopup', 'true')" x-effect="$el.querySelector('button')?.setAttribute('aria-expanded', open)">
        {{ $trigger }}
    </div>

    <div x-show="open" x-ref="panel"
        x-transition:leave="transition-opacity ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="absolute z-50 mt-1.5 {{ $width }} rounded-xl glass-overlay {{ $alignmentClasses }}"
        style="display: none;"
        @click="open = false" @keydown="menueTaste($event)"
    >
        <div class="{{ $contentClasses }}">
            {{ $content }}
        </div>
    </div>
</div>
