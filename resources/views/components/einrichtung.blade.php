@props(['schritt', 'stand', 'titel'])
@php
    $schritte = \App\Support\Einrichtung::SCHRITTE;
    $nummer = array_search($schritt, array_keys($schritte), true) + 1;
@endphp
<x-app-layout>
    <x-slot name="title">Einrichtung · {{ $titel }}</x-slot>
    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-text">Einrichtung <span class="text-muted font-normal">· {{ $titel }}</span></h2>
            <span class="text-sm text-muted tabular-nums">{{ $nummer }} / {{ count($schritte) }}</span>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-1 lg:grid-cols-[15rem_minmax(0,1fr)] gap-6">
            <nav aria-label="Schritte der Einrichtung" class="lg:sticky lg:top-24 self-start min-w-0">
                <ol class="relative flex lg:flex-col gap-1.5 overflow-x-auto pb-1 lg:pb-0">
                    @foreach($schritte as $key => $name)
                        @php
                            $aktiv = $key === $schritt;
                            $ok = $stand[$key]['erledigt'];
                        @endphp
                        <li class="shrink-0">
                            <a href="{{ route('admin.setup', $key) }}" @if($aktiv) aria-current="step" @endif
                               @class(['flex items-center gap-3 rounded-xl px-3 py-2 min-h-11 transition-colors',
                                   'glass text-text' => $aktiv,
                                   'text-muted hover:text-text hover:bg-accent/5' => ! $aktiv])>
                                <span @class(['w-7 h-7 shrink-0 rounded-full inline-flex items-center justify-center text-xs font-bold',
                                    'bg-accent text-white' => $aktiv,
                                    'bg-green-500/15 text-green-700 dark:text-green-400' => ! $aktiv && $ok,
                                    'border border-border' => ! $aktiv && ! $ok])>
                                    @if($ok && ! $aktiv)
                                        <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 1 1 1.4-1.4l3.8 3.8 6.8-6.8a1 1 0 0 1 1.4 0z" clip-rule="evenodd"/></svg>
                                        <span class="sr-only">erledigt</span>
                                    @else
                                        {{ $loop->iteration }}
                                    @endif
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-medium whitespace-nowrap">{{ $name }}</span>
                                    @if($stand[$key]['info'] !== '')
                                        <span class="hidden lg:block text-xs text-muted truncate max-w-40">{{ $stand[$key]['info'] }}</span>
                                    @endif
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ol>
            </nav>

            <div class="flex flex-col gap-5 min-w-0">
                {{ $slot }}
            </div>
        </div>
    </div>
</x-app-layout>
