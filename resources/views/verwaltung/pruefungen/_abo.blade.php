@php
    $exportUrl = route('calendar.export', ['token' => $exportToken]);
    $webcalUrl = preg_replace('#^https?://#', 'webcal://', $exportUrl);
@endphp
<div class="flex flex-col gap-4">
    <p class="text-sm text-muted">{{ __('Diese Adresse in Kalender-Apps (Google, Outlook, Apple) als Kalenderabo hinzufügen.') }}</p>

    <div class="flex items-center gap-2" x-data="{ kopiert: false }">
        <input type="text" readonly value="{{ $exportUrl }}" x-ref="link" onclick="this.select()"
               class="flex-1 min-w-0 h-10 rounded-lg border border-border-strong/70 bg-input px-3 font-mono text-xs text-text">
        <button type="button" class="h-10 shrink-0 rounded-lg glass-btn px-3 text-sm text-text"
                @click="navigator.clipboard.writeText($refs.link.value); kopiert = true; setTimeout(() => kopiert = false, 2000)">
            <span x-show="!kopiert">{{ __('Kopieren') }}</span>
            <span x-show="kopiert" x-cloak>{{ __('Kopiert') }}</span>
        </button>
    </div>

    <a href="{{ $webcalUrl }}" class="inline-flex h-10 items-center justify-center rounded-lg bg-accent px-3.5 text-sm font-medium text-accent-contrast np-btn-primary">
        {{ __('In Kalender öffnen') }}
    </a>

    <p class="text-xs text-muted">{{ __('Der Link ist geheim – nicht weitergeben.') }}</p>

    <form method="POST" action="{{ route($bereich.'.calendar.token.reset') }}" x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true"
          onsubmit="return confirm('{{ __('Neuen Abo-Link erzeugen? Der bisherige Link funktioniert danach nicht mehr.') }}');">
        @csrf
        <button :disabled="loading" class="text-sm text-accent-text hover:underline disabled:opacity-60">{{ __('Neuen Link erzeugen') }}</button>
    </form>
</div>
