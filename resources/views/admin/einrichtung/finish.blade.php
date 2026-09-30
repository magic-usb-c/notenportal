<x-einrichtung schritt="finish" :stand="$stand" :titel="__('Abschluss')">
    @php
        $offen = collect($stand)->except('finish')->reject(fn ($s) => $s['erledigt']);
    @endphp
    <section class="np-karte overflow-hidden print:hidden">
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
                    <a href="{{ route('admin.setup', $key) }}" class="np-knopf np-knopf-schlicht">{{ $ok ? __('Bearbeiten') : __('Erledigen') }}</a>
                </li>
            @endforeach
        </ul>
    </section>

    @include('admin.einrichtung._zugaenge')

    <div class="flex flex-wrap items-center justify-between gap-3 print:hidden">
        <a href="{{ route('admin.setup', 'people') }}" class="np-knopf np-knopf-sekundaer np-knopf-gross">{{ __('← Zurück') }}</a>
        @if(\App\Support\Einrichtung::offen())
            <form method="POST" action="{{ route('admin.setup.finish') }}" x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                @csrf
                <button type="submit" :disabled="loading" @class(['inline-flex items-center px-5 h-10 rounded-xl text-sm font-semibold disabled:opacity-60',
                    'np-knopf np-knopf-primaer' => $offen->isEmpty(), 'np-knopf np-knopf-sekundaer' => $offen->isNotEmpty()])>
                    {{ $offen->isEmpty() ? __('Einrichtung abschliessen') : __('Trotzdem abschliessen') }}
                </button>
            </form>
        @else
            <a href="{{ route('admin.dashboard') }}" class="np-knopf np-knopf-primaer np-knopf-gross">{{ __('Zur Übersicht') }}</a>
        @endif
    </div>
</x-einrichtung>
