{{-- Inhalt einer Zeile im Tagesdrawer (Alpine-Scope «e»): mit Ziel als Link, ohne Ziel als gewöhnlicher Block --}}
<span class="size-2 shrink-0 rounded-full" :class="e.punkt" aria-hidden="true"></span>
<span class="min-w-0 flex-1">
    <span class="block truncate text-sm font-medium text-text" x-text="e.titel"></span>
    <span class="block truncate text-xs text-muted" x-text="(e.zeit ? e.zeit + ' · ' : '') + e.nebentext"></span>
</span>
<template x-if="e.href"><x-symbol name="chevron-right" strich="2" class="size-3.5 shrink-0 text-muted" /></template>
