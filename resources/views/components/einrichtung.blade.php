{{-- Rahmen der Einrichtung (HIG «Setup assistant», macOS-Installationsprogramm): Schritte links als ruhige
     Liste mit Punkt (aktuell), Haken (erledigt) oder Ring (offen), Inhalt rechts in Formularbreite. --}}
@props(['schritt', 'stand', 'titel'])
@php
    $schritte = \App\Support\Einrichtung::SCHRITTE;
    $nummer = array_search($schritt, array_keys($schritte), true) + 1;
@endphp
<x-app-layout>
    <x-slot name="title">{{ __('Einrichtung') }} · {{ $titel }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf class="mx-auto max-w-5xl" :titel="$titel" :untertitel="__('Einrichtung · Schritt :nummer von :anzahl', ['nummer' => $nummer, 'anzahl' => count($schritte)])" />
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-8">
            <div class="mx-auto grid max-w-5xl grid-cols-[14rem_minmax(0,1fr)] gap-10">
                <nav aria-label="{{ __('Schritte der Einrichtung') }}" class="sticky top-24 self-start">
                    <ol class="flex flex-col gap-0.5">
                        @foreach($schritte as $key => $name)
                            @php
                                $aktiv = $key === $schritt;
                                $ok = $stand[$key]['erledigt'];
                            @endphp
                            <li>
                                <a href="{{ route('admin.setup', $key) }}" @if($aktiv) aria-current="step" @endif
                                   @class(['flex items-start gap-3 rounded-lg px-2.5 py-2 transition-colors duration-100',
                                       'bg-fill' => $aktiv,
                                       'hover:bg-fill-2' => ! $aktiv])>
                                    <span class="mt-0.5 flex size-5 shrink-0 items-center justify-center" aria-hidden="true">
                                        @if($aktiv)
                                            <span class="size-2.5 rounded-full bg-accent"></span>
                                        @elseif($ok)
                                            <x-symbol name="check-circle" class="size-5 text-note-gut" />
                                        @else
                                            <span class="size-2.5 rounded-full border-[1.5px] border-border-strong"></span>
                                        @endif
                                    </span>
                                    <span class="min-w-0">
                                        <span @class(['block text-sm', 'font-semibold text-text' => $aktiv, 'text-text' => ! $aktiv && $ok, 'text-muted' => ! $aktiv && ! $ok])>{{ __($name) }}</span>
                                        @if($stand[$key]['info'] !== '')
                                            <span class="block truncate text-xs text-muted">{{ $stand[$key]['info'] }}</span>
                                        @endif
                                        @if($ok && ! $aktiv)<span class="sr-only">{{ __('erledigt') }}</span>@endif
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ol>
                </nav>

                <div class="flex min-w-0 flex-col gap-8">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
