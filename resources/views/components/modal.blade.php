@props([
    'name',
    'show' => false,
    'maxWidth' => '2xl'
])

@php
$maxWidth = [
    'sm' => 'max-w-sm',
    'md' => 'max-w-md',
    'lg' => 'max-w-lg',
    'xl' => 'max-w-xl',
    '2xl' => 'max-w-2xl',
][$maxWidth];
@endphp

{{-- An <body> gehängt, damit kein Stapelkontext der Seite den Scrim unter die Navigation drückt --}}
<div x-data class="contents">
<template x-teleport="body">
<div
    x-data="{
        show: @js($show),
        ausloeser: null,
        focusables() {
            let selector = 'a, button, input:not([type=\'hidden\']), textarea, select, details, [tabindex]:not([tabindex=\'-1\'])'
            return [...$el.querySelectorAll(selector)]
                .filter(el => ! el.hasAttribute('disabled') && el.getClientRects().length > 0)
        },
        firstFocusable() { return this.focusables()[0] },
        lastFocusable() { return this.focusables().slice(-1)[0] },
        nextFocusable() { return this.focusables()[this.nextFocusableIndex()] || this.firstFocusable() },
        prevFocusable() { return this.focusables()[this.prevFocusableIndex()] || this.lastFocusable() },
        nextFocusableIndex() { return (this.focusables().indexOf(document.activeElement) + 1) % (this.focusables().length + 1) },
        prevFocusableIndex() { return Math.max(0, this.focusables().indexOf(document.activeElement)) -1 },
    }"
    x-init="if (show) { document.body.classList.add('overflow-y-hidden'); {{ $attributes->has('focusable') ? 'setTimeout(() => firstFocusable().focus(), 100)' : '' }} }
            $watch('show', value => {
        if (value) {
            ausloeser = document.activeElement;
            document.body.classList.add('overflow-y-hidden');
            {{ $attributes->has('focusable') ? 'setTimeout(() => firstFocusable().focus(), 100)' : '' }}
        } else {
            document.body.classList.remove('overflow-y-hidden');
            if (ausloeser?.isConnected) ausloeser.focus();
            ausloeser = null;
        }
    })"
    x-on:open-modal.window="$event.detail == '{{ $name }}' ? show = true : null"
    x-on:close-modal.window="$event.detail == '{{ $name }}' ? show = false : null"
    x-on:close.stop="show = false"
    x-on:keydown.escape.window="show = false"
    x-on:keydown.tab.prevent="$event.shiftKey || nextFocusable().focus()"
    x-on:keydown.shift.tab.prevent="prevFocusable().focus()"
    x-cloak
    :inert="! show"
    :class="show ? 'visible' : 'invisible pointer-events-none transition-[visibility] duration-200 ruhig:duration-150'"
    class="fixed inset-0 z-[70] overflow-y-auto px-4 pb-6 pt-[12vh]"
>
    {{-- Scrim über der ganzen Seite, auch über der Navigation (z-50). Bewegung (B3): CSS-Transition über Klassen,
         mitten im Lauf umkehrbar; öffnen 300 ms ease-out, schliessen 200 ms ease-in; unter ruhiger Bewegung nur Überblendung. --}}
    <div
        class="fixed inset-0 glass-scrim"
        :class="show ? 'opacity-100 transition-opacity duration-300 ease-out ruhig:duration-150' : 'opacity-0 transition-opacity duration-200 ease-in ruhig:duration-150'"
        x-on:click="show = false"
        aria-hidden="true"
    ></div>

    <div
        class="relative mb-6 overflow-hidden rounded-2xl border border-border-strong/30 bg-card text-text shadow-e3 mx-auto w-full {{ $maxWidth }}"
        :class="show
            ? 'translate-y-0 opacity-100 transition-[translate,opacity] duration-300 ease-out ruhig:duration-150'
            : 'translate-y-2 ruhig:translate-y-0 opacity-0 transition-[translate,opacity] duration-200 ease-in ruhig:duration-150'"
    >
        {{ $slot }}
    </div>
</div>
</template>
</div>
