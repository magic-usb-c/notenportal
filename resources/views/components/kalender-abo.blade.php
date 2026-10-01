{{-- Abo-Link zum eigenen iCal-Export (Lernende: Agenda, BB/Admin: Prüfungstermine). Reset-Route kommt vom Aufrufer. --}}
@props(['token', 'resetRoute'])
@php
    $exportUrl = route('calendar.export', ['token' => $token]);
    $webcalUrl = preg_replace('#^https?://#', 'webcal://', $exportUrl);
@endphp
<div class="np-karte np-gruppe">
    <div class="flex items-center gap-2 px-4 py-3" x-data="{ kopiert: false }">
        <input type="text" readonly value="{{ $exportUrl }}" x-ref="link" x-on:click="$el.select()" aria-label="{{ __('Abo-Link') }}"
               class="np-feld min-w-0 flex-1 font-mono text-xs">
        <button type="button" class="np-knopf np-knopf-sekundaer min-w-24 shrink-0"
                @click="if (await np.kopieren($refs.link.value)) { kopiert = true; setTimeout(() => kopiert = false, 2000) } else { $refs.link.select() }">
            <span x-show="!kopiert">{{ __('Kopieren') }}</span>
            <span x-show="kopiert" x-cloak>{{ __('Kopiert') }}</span>
        </button>
    </div>
    <div class="flex items-center justify-between gap-3 px-4 py-3">
        <form method="POST" action="{{ $resetRoute }}" x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true"
              data-bestaetigen="{{ __('Neuen Abo-Link erzeugen?') }}" data-bestaetigen-text="{{ __('Der bisherige Link funktioniert danach nicht mehr.') }}">
            @csrf
            <button :disabled="loading" class="np-knopf np-knopf-schlicht">{{ __('Neuen Link erzeugen') }}</button>
        </form>
        <a href="{{ $webcalUrl }}" class="np-knopf np-knopf-sekundaer">{{ __('In Kalender öffnen') }}</a>
    </div>
</div>
<p class="mt-2 px-1 text-xs text-muted">{{ __('Diese Adresse in Kalender-Apps (Google, Outlook, Apple) als Kalenderabo hinzufügen.') }} {{ __('Der Link ist geheim – nicht weitergeben.') }}</p>
