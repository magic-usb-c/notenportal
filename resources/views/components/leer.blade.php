@props(['symbol' => null, 'titel', 'text' => null])
{{-- Leerzustand einer ganzen Ansicht (HIG «Content unavailable»): grosses Symbol, Titel, ein Satz, darunter
     höchstens eine Aktion im Slot. Für leere Filterergebnisse in Tabellen reicht eine Zeile in der Tabelle. --}}
<div {{ $attributes->class('flex flex-col items-center px-6 py-16 text-center') }}>
    @if($symbol)
        <x-symbol :name="$symbol" strich="1.25" class="mb-4 size-12 text-faint" />
    @endif
    <p class="text-lg font-semibold text-text">{{ $titel }}</p>
    @if($text)
        <p class="mt-1 max-w-md text-sm text-muted">{{ $text }}</p>
    @endif
    @if($slot->isNotEmpty())
        <div class="mt-5 flex items-center gap-2">{{ $slot }}</div>
    @endif
</div>
