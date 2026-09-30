{{-- Abo-Link zum eigenen iCal-Export (Lernende: Agenda, BB/Admin: Prüfungstermine). Reset-Route kommt vom Aufrufer. --}}
@props(['token', 'resetRoute'])
@php
    $exportUrl = route('calendar.export', ['token' => $token]);
    $webcalUrl = preg_replace('#^https?://#', 'webcal://', $exportUrl);
@endphp
<div class="flex flex-col gap-4">
    <p class="text-sm text-muted">{{ __('Diese Adresse in Kalender-Apps (Google, Outlook, Apple) als Kalenderabo hinzufügen.') }}</p>

    <div class="flex items-center gap-2" x-data="{ kopiert: false }">
        <input type="text" readonly value="{{ $exportUrl }}" x-ref="link" onclick="this.select()" aria-label="{{ __('Abo-Link') }}"
               class="np-feld flex-1 min-w-0 font-mono text-xs">
        <button type="button" class="np-knopf np-knopf-sekundaer np-knopf-gross shrink-0"
                @click="if (await np.kopieren($refs.link.value)) { kopiert = true; setTimeout(() => kopiert = false, 2000) } else { $refs.link.select() }">
            <span x-show="!kopiert">{{ __('Kopieren') }}</span>
            <span x-show="kopiert" x-cloak>{{ __('Kopiert') }}</span>
        </button>
    </div>

    <a href="{{ $webcalUrl }}" class="np-knopf np-knopf-primaer np-knopf-gross">
        {{ __('In Kalender öffnen') }}
    </a>

    <p class="text-xs text-muted">{{ __('Der Link ist geheim – nicht weitergeben.') }}</p>

    <form method="POST" action="{{ $resetRoute }}" x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true"
          onsubmit="return confirm('{{ __('Neuen Abo-Link erzeugen? Der bisherige Link funktioniert danach nicht mehr.') }}');">
        @csrf
        <button :disabled="loading" class="text-sm text-accent-text hover:underline disabled:opacity-60">{{ __('Neuen Link erzeugen') }}</button>
    </form>
</div>
