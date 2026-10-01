{{-- Versandprotokoll: Kennzahlen der letzten sieben Tage (antippbar = Filter), darunter jede verschickte E-Mail. --}}
@php
    $statusMap = ['sent' => 'gruen', 'failed' => 'rot', 'skipped' => 'neutral', 'queued' => 'gelb'];
    $kacheln = [
        ['label' => __('Verschickt (7 Tage)'), 'wert' => $kennzahlen['verschickt'], 'status' => \App\Models\MailLog::SENT, 'ton' => 'neutral'],
        ['label' => __('Fehlgeschlagen (7 Tage)'), 'wert' => $kennzahlen['fehlgeschlagen'], 'status' => \App\Models\MailLog::FAILED, 'ton' => $kennzahlen['fehlgeschlagen'] > 0 ? 'rot' : 'neutral'],
        ['label' => __('Nicht zustellbar (7 Tage)'), 'wert' => $kennzahlen['nicht_zustellbar'], 'status' => \App\Models\MailLog::SKIPPED, 'ton' => $kennzahlen['nicht_zustellbar'] > 0 ? 'gelb' : 'neutral'],
        ['label' => __('In Warteschlange'), 'wert' => $kennzahlen['warteschlange'], 'status' => null, 'ton' => 'neutral'],
    ];
    $aktiveFilter = collect([$status, $type, $q])->filter()->count();
@endphp
<x-app-layout>
    <x-slot name="title">{{ __('Versandprotokoll') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :titel="__('Versandprotokoll')" :zaehler="$leer ? null : $eintraege->total()" />
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-8 flex flex-col gap-4">
            @if($leer)
                <div class="np-karte">
                    <x-leer symbol="envelope" :titel="__('Noch keine E-Mails verschickt')" />
                </div>
            @else
                <div class="grid grid-cols-4 gap-4">
                    @foreach($kacheln as $k)
                        <x-kachel :label="$k['label']" :wert="$k['wert']" :ton="$k['ton']"
                                  :href="$k['status'] ? route('admin.mail-log.index', ['status' => $k['status']]) : null" />
                    @endforeach
                </div>

                <x-filterleiste :action="route('admin.mail-log.index')" suche-name="q" :suche-wert="$q"
                                 :suche-platzhalter="__('Empfänger (E-Mail)')"
                                 :zurueck="route('admin.mail-log.index')" :aktive-filter="$aktiveFilter">
                    <label for="status" class="sr-only">{{ __('Status') }}</label>
                    <select name="status" id="status" x-on:change="$el.form.requestSubmit()" class="np-feld np-feld-klein w-auto max-w-64">
                        <option value="" @selected($status === '')>{{ __('Status: alle') }}</option>
                        @foreach(\App\Models\MailLog::STATUS as $value => $label)
                            <option value="{{ $value }}" @selected($status === $value)>{{ __($label) }}</option>
                        @endforeach
                    </select>

                    <label for="type" class="sr-only">{{ __('Anlass') }}</label>
                    <select name="type" id="type" x-on:change="$el.form.requestSubmit()" class="np-feld np-feld-klein w-auto max-w-64">
                        <option value="" @selected($type === '')>{{ __('Anlass: alle') }}</option>
                        @foreach($anlaesse as $value => $label)
                            <option value="{{ $value }}" @selected($type === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </x-filterleiste>

                <div class="np-karte overflow-hidden">
                    <div class="p-2">
                        <table class="np-tabelle table-fixed text-sm">
                            <thead>
                                <tr>
                                    <th scope="col" class="w-40">{{ __('Zeit') }}</th>
                                    <th scope="col" class="w-1/4">{{ __('Empfänger') }}</th>
                                    <th scope="col">{{ __('Betreff') }}</th>
                                    <th scope="col" class="w-44">{{ __('Status') }}</th>
                                    <th scope="col" class="w-36 text-right"><span class="sr-only">{{ __('Aktion') }}</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($eintraege as $e)
                                    @php
                                        $zeitpunkt = $e->created_at?->timezone(config('app.timezone'));
                                        $statusText = __(\App\Models\MailLog::STATUS[$e->status] ?? $e->status);
                                    @endphp
                                    <tr>
                                        <td class="whitespace-nowrap align-top tabular-nums text-muted">
                                            {{ $zeitpunkt?->format('d.m.Y') }} <span class="text-faint" aria-hidden="true">·</span> {{ $zeitpunkt?->format('H:i') }}
                                        </td>
                                        <td class="align-top">
                                            <div class="truncate" title="{{ $e->recipient }}">{{ $e->recipient }}</div>
                                            @if($e->redirected_to)
                                                <div class="truncate text-xs text-muted" title="{{ $e->redirected_to }}">{{ __('→ umgeleitet an :ziel', ['ziel' => $e->redirected_to]) }}</div>
                                            @endif
                                        </td>
                                        <td class="align-top">
                                            <div class="truncate" title="{{ $e->subject }}">{{ $e->subject }}</div>
                                            <div class="truncate text-xs text-muted">{{ $anlaesse[$e->type] ?? $e->type }}</div>
                                            @if($e->error)
                                                <div class="line-clamp-2 break-words pt-0.5 text-xs text-note-ungenuegend" title="{{ $e->error }}">{{ $e->error }}</div>
                                            @endif
                                        </td>
                                        <td class="whitespace-nowrap align-top">
                                            <x-status :status="$statusMap[$e->status] ?? 'neutral'" :text="$statusText" />
                                            @if($e->attempts > 1)<div class="text-xs text-muted">{{ __('Versuche') }} {{ $e->attempts }}</div>@endif
                                        </td>
                                        <td class="align-top text-right">
                                            @if(in_array($e->status, [\App\Models\MailLog::FAILED, \App\Models\MailLog::SKIPPED], true))
                                                <form method="POST" action="{{ route('admin.mail-log.retry', $e->id) }}"
                                                      x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                                                    @csrf
                                                    <button type="submit" :disabled="loading" class="np-knopf np-knopf-schlicht np-knopf-klein"
                                                            aria-label="{{ __('Erneut senden an :empfaenger', ['empfaenger' => $e->recipient]) }}">{{ __('Erneut senden') }}</button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-3 py-6 text-center text-muted">{{ __('Keine Einträge passen zu den Filtern.') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                @if($eintraege->hasPages())
                    <div class="px-1">{{ $eintraege->links() }}</div>
                @endif
            @endif
        </div>
    </div>
</x-app-layout>
