{{-- Eigene Meldungen: Tabelle mit Datum, Kategorie, Text, Status und der Antwort des Admins. Erfassen öffnet das
     Feedback-Fenster. Farbe nur beim Status mit Bedeutung: in Arbeit Akzent, erledigt grün, offen neutral. --}}
@use('App\Models\Feedback')
<x-app-layout>
    <x-slot name="title">{{ __('Meine Meldungen') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :titel="__('Meine Meldungen')" :zaehler="$meldungen->total() ?: null">
            <x-slot:aktionen>
                <button type="button" class="np-knopf np-knopf-primaer" x-data @click="$dispatch('open-modal', 'feedback')">
                    <x-symbol name="plus" strich="2" />{{ __('Meldung erfassen') }}
                </button>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite flex flex-col gap-4 px-4 sm:px-6 lg:px-8">
            @if($meldungen->isEmpty())
                <div class="np-karte">
                    <x-leer symbol="chat-bubble-left-ellipsis" :titel="__('Noch keine Meldungen')" />
                </div>
            @else
                <div class="np-karte p-2">
                    <table class="np-tabelle table-fixed text-sm">
                        <thead>
                            <tr>
                                <th scope="col" class="w-36">{{ __('Datum') }}</th>
                                <th scope="col" class="w-36">{{ __('Kategorie') }}</th>
                                <th scope="col">{{ __('Text') }}</th>
                                <th scope="col" class="w-32">{{ __('Status') }}</th>
                                <th scope="col" class="w-1/3">{{ __('Antwort') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($meldungen as $m)
                                @php
                                    $statusKlasse = match ($m->status) {
                                        Feedback::STATUS_ERLEDIGT => 'bg-note-gut/14 text-note-gut',
                                        Feedback::STATUS_IN_ARBEIT => 'bg-accent/12 text-accent-text',
                                        default => 'text-muted',
                                    };
                                    $gekuerzt = mb_strlen($m->text) > 240;
                                @endphp
                                <tr>
                                    <td class="align-top whitespace-nowrap text-muted">
                                        <time datetime="{{ $m->erstellt_am->toIso8601String() }}">{{ $m->erstellt_am->format('d.m.Y H:i') }}</time>
                                    </td>
                                    <td class="align-top">{{ __(Feedback::kategorieLabel($m->kategorie)) }}</td>
                                    <td class="align-top">
                                        @if($gekuerzt)
                                            <details class="np-details">
                                                <summary class="cursor-pointer list-none">
                                                    {{ Str::limit($m->text, 240) }}
                                                    <span class="whitespace-nowrap text-accent-text">{{ __('mehr') }}</span>
                                                </summary>
                                                <p class="mt-1 whitespace-pre-wrap">{{ $m->text }}</p>
                                            </details>
                                        @else
                                            <p class="whitespace-pre-wrap">{{ $m->text }}</p>
                                        @endif
                                        @if($m->istDuplikat())
                                            <p class="mt-1 text-xs text-muted">{{ __('Mit einer gleichen Meldung zusammengeführt') }}</p>
                                        @elseif($mitStimmen && ($m->stimmen_count ?? 0) > 0)
                                            <p class="mt-1 text-xs text-muted">{{ __(':n Personen betrifft das auch', ['n' => $m->stimmen_count]) }}</p>
                                        @endif
                                    </td>
                                    <td class="align-top">
                                        <span class="np-marke {{ $statusKlasse }}">{{ __(Feedback::STATUS[$m->status] ?? $m->status) }}</span>
                                    </td>
                                    <td class="align-top">
                                        @if($m->admin_notiz)
                                            <p class="whitespace-pre-wrap">{{ $m->admin_notiz }}</p>
                                        @else
                                            <span class="text-muted">–</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($meldungen->hasPages())
                    <div class="px-1">{{ $meldungen->links() }}</div>
                @endif
            @endif
        </div>
    </div>
</x-app-layout>
