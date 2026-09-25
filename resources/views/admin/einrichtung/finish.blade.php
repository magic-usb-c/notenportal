<x-einrichtung schritt="finish" :stand="$stand" :titel="__('Abschluss')">
    @php
        $offen = collect($stand)->except('finish')->reject(fn ($s) => $s['erledigt']);
    @endphp
    <section class="rounded-2xl border border-border bg-card overflow-hidden print:hidden">
        <ul class="divide-y divide-border">
            @foreach(\App\Support\Einrichtung::SCHRITTE as $key => $name)
                @continue($key === 'finish')
                @php $ok = $stand[$key]['erledigt']; @endphp
                <li class="flex items-center gap-3 px-5 py-3">
                    <span @class(['w-7 h-7 rounded-full inline-flex items-center justify-center shrink-0 text-xs font-bold',
                        'bg-note-gut/15 text-note-gut' => $ok,
                        'bg-note-knapp/15 text-note-knapp' => ! $ok])>{{ $ok ? '✓' : '!' }}</span>
                    <span class="flex-1 min-w-0">
                        <span class="block text-sm font-medium text-text">{{ __($name) }}</span>
                        <span class="block text-xs text-muted truncate">{{ $stand[$key]['info'] !== '' ? $stand[$key]['info'] : __('offen') }}</span>
                    </span>
                    <a href="{{ route('admin.setup', $key) }}" class="inline-flex items-center px-3 min-h-9 rounded-lg text-sm text-accent-text hover:bg-accent/10">{{ $ok ? __('Bearbeiten') : __('Erledigen') }}</a>
                </li>
            @endforeach
        </ul>
    </section>

    @include('admin.einrichtung._zugaenge')

    <div class="flex flex-wrap items-center justify-between gap-3 print:hidden">
        <a href="{{ route('admin.setup', 'people') }}" class="inline-flex items-center px-4 h-10 rounded-xl glass-btn text-text text-sm">{{ __('← Zurück') }}</a>
        @if(\App\Support\Einrichtung::offen())
            <form method="POST" action="{{ route('admin.setup.finish') }}" x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                @csrf
                <button type="submit" :disabled="loading" @class(['inline-flex items-center px-5 h-10 rounded-xl text-sm font-semibold disabled:opacity-60',
                    'bg-accent text-accent-contrast np-btn-primary' => $offen->isEmpty(), 'glass-btn text-text' => $offen->isNotEmpty()])>
                    {{ $offen->isEmpty() ? __('Einrichtung abschliessen') : __('Trotzdem abschliessen') }}
                </button>
            </form>
        @else
            <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center px-5 h-10 rounded-xl bg-accent text-accent-contrast text-sm font-semibold np-btn-primary">{{ __('Zur Übersicht') }}</a>
        @endif
    </div>
</x-einrichtung>
