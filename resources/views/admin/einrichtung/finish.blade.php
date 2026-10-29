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
                    <span class="flex size-5 shrink-0 items-center justify-center" aria-hidden="true">
                        @if($ok)
                            <x-symbol name="check-circle" class="size-5 text-note-gut" />
                        @else
                            <span class="size-2.5 rounded-full border-[1.5px] border-border-strong"></span>
                        @endif
                    </span>
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
        <a href="{{ route('admin.setup', 'mail') }}" class="np-knopf np-knopf-sekundaer">{{ __('Zurück') }}</a>
        @if(\App\Support\Einrichtung::offen())
            <form method="POST" action="{{ route('admin.setup.finish') }}" x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                @csrf
                <button type="submit" :disabled="loading" @class(['np-knopf', 'np-knopf-primaer' => $offen->isEmpty(), 'np-knopf-sekundaer' => $offen->isNotEmpty()])>
                    {{ $offen->isEmpty() ? __('Einrichtung abschliessen') : __('Trotzdem abschliessen') }}
                </button>
            </form>
        @else
            <a href="{{ route('admin.dashboard') }}" class="np-knopf np-knopf-primaer">{{ __('Zur Übersicht') }}</a>
        @endif
    </div>
</x-einrichtung>
