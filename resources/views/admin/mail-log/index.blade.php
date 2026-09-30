<x-app-layout>
    <x-slot name="title">{{ __('Versandprotokoll') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :titel="__('Versandprotokoll')" />
    </x-slot>

    @php
        $statusMap = ['sent' => 'gruen', 'failed' => 'rot', 'skipped' => 'neutral', 'queued' => 'gelb'];
        $kacheln = [
            ['label' => __('Verschickt (7 Tage)'), 'wert' => $kennzahlen['verschickt']],
            ['label' => __('Fehlgeschlagen (7 Tage)'), 'wert' => $kennzahlen['fehlgeschlagen']],
            ['label' => __('Nicht zustellbar (7 Tage)'), 'wert' => $kennzahlen['nicht_zustellbar']],
            ['label' => __('In Warteschlange'), 'wert' => $kennzahlen['warteschlange']],
        ];
    @endphp

    <div class="py-6">
        <div class="mx-auto np-seite px-4 sm:px-6 lg:px-8 flex flex-col gap-4">

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                @foreach($kacheln as $k)
                    <x-kachel :label="$k['label']" :wert="$k['wert']" />
                @endforeach
            </div>

            @php($aktiveFilter = collect([$status, $type, $q])->filter()->count())
            <x-filterleiste :action="route('admin.mail-log.index')" suche-name="q" :suche-wert="$q"
                             suche-platzhalter="{{ __('Empfänger (E-Mail)') }}" :zaehler="$eintraege->total()" zaehler-label="{{ __('Einträge') }}"
                             :zurueck="route('admin.mail-log.index')" :aktive-filter="$aktiveFilter">
                <label for="status" class="sr-only">{{ __('Status') }}</label>
                <select name="status" id="status" x-on:change="$el.form.requestSubmit()"
                        class="np-feld px-2.5 sm:w-40">
                    <option value="" @selected($status === '')>{{ __('Status: alle') }}</option>
                    @foreach(\App\Models\MailLog::STATUS as $value => $label)
                        <option value="{{ $value }}" @selected($status === $value)>{{ __($label) }}</option>
                    @endforeach
                </select>

                <label for="type" class="sr-only">{{ __('Anlass') }}</label>
                <select name="type" id="type" x-on:change="$el.form.requestSubmit()"
                        class="np-feld px-2.5 sm:w-48">
                    <option value="" @selected($type === '')>{{ __('Anlass: alle') }}</option>
                    @foreach($anlaesse as $value => $label)
                        <option value="{{ $value }}" @selected($type === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-filterleiste>

            <div class="np-karte overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm tabular-nums">
                        <thead class="sticky top-0 z-10 bg-surface-2">
                            <tr>
                                <th scope="col" class="hidden lg:table-cell h-9 px-3 text-left text-2xs font-medium text-muted whitespace-nowrap">{{ __('Zeit') }}</th>
                                <th scope="col" class="h-9 px-3 text-left text-2xs font-medium text-muted whitespace-nowrap lg:w-1/3">{{ __('Empfänger') }}</th>
                                <th scope="col" class="hidden lg:table-cell h-9 px-3 text-left text-2xs font-medium text-muted lg:w-2/3">{{ __('Betreff') }}</th>
                                <th scope="col" class="hidden lg:table-cell h-9 px-3 text-left text-2xs font-medium text-muted whitespace-nowrap">{{ __('Status') }}</th>
                                <th scope="col" class="h-9 px-3 text-right"><span class="sr-only">{{ __('Aktion') }}</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @forelse($eintraege as $e)
                                @php($zeitpunkt = $e->created_at?->timezone(config('app.timezone')))
                                @php($zeit = $zeitpunkt?->format('d.m.Y H:i'))
                                @php($anlass = $anlaesse[$e->type] ?? $e->type)
                                @php($statusText = __(\App\Models\MailLog::STATUS[$e->status] ?? $e->status))
                                <tr class="group hover:bg-surface-2/60">
                                    <td class="hidden lg:table-cell px-3 py-2.5 text-muted whitespace-nowrap align-top">
                                        <div>{{ $zeitpunkt?->format('d.m.Y') }}</div>
                                        <div class="text-xs">{{ $zeitpunkt?->format('H:i') }}</div>
                                    </td>
                                    {{-- max-w-0: Empfänger und Betreff teilen sich den Rest der Breite und kürzen statt die Tabelle zu verbreitern --}}
                                    <td class="px-3 py-2.5 align-top text-left lg:max-w-0">
                                        <div class="hidden lg:block truncate" title="{{ $e->recipient }}">{{ $e->recipient }}</div>
                                        @if($e->redirected_to)
                                            <div class="hidden lg:block truncate text-xs text-muted">{{ __('→ umgeleitet an :ziel', ['ziel' => $e->redirected_to]) }}</div>
                                        @endif
                                        <div class="flex flex-col gap-1 lg:hidden">
                                            <div class="break-all">{{ $e->recipient }}</div>
                                            @if($e->redirected_to)
                                                <div class="text-xs text-muted break-all">{{ __('→ umgeleitet an :ziel', ['ziel' => $e->redirected_to]) }}</div>
                                            @endif
                                        </div>
                                        <div class="flex flex-col gap-1 pt-1 text-xs text-muted lg:hidden">
                                            <span>{{ $zeit }} · {{ $anlass }}</span>
                                            <span class="wrap-anywhere">{{ $e->subject }}</span>
                                            <span class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                                <x-status :status="$statusMap[$e->status] ?? 'neutral'" :text="$statusText" />
                                                @if($e->attempts > 1)<span>{{ __('Versuche') }} {{ $e->attempts }}</span>@endif
                                            </span>
                                            @if($e->error)<span class="wrap-anywhere">{{ $e->error }}</span>@endif
                                        </div>
                                    </td>
                                    <td class="hidden lg:table-cell max-w-0 px-3 py-2.5 align-top">
                                        <div class="truncate" title="{{ $e->subject }}">{{ $e->subject }}</div>
                                        <div class="truncate text-xs text-muted">{{ $anlass }}</div>
                                        @if($e->error)
                                            <div class="line-clamp-2 break-words pt-0.5 text-xs text-muted" title="{{ $e->error }}">{{ $e->error }}</div>
                                        @endif
                                    </td>
                                    <td class="hidden lg:table-cell px-3 py-2.5 whitespace-nowrap align-top">
                                        <x-status :status="$statusMap[$e->status] ?? 'neutral'" :text="$statusText" />
                                        @if($e->attempts > 1)<div class="pt-1 text-xs text-muted">{{ __('Versuche') }} {{ $e->attempts }}</div>@endif
                                    </td>
                                    <td class="px-3 py-2.5 text-right align-top whitespace-nowrap">
                                        @if(in_array($e->status, [\App\Models\MailLog::FAILED, \App\Models\MailLog::SKIPPED], true))
                                            <form method="POST" action="{{ route('admin.mail-log.retry', $e->id) }}"
                                                  x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true"
                                                  class="opacity-0 group-hover:opacity-100 group-focus-within:opacity-100 max-lg:opacity-100">
                                                @csrf
                                                <button type="submit" :disabled="loading" aria-label="{{ __('Erneut senden') }}" title="{{ __('Erneut senden') }}"
                                                        class="np-knopf np-knopf-sekundaer np-knopf-rund np-ziel">
                                                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="p-6 text-center text-muted">{{ __('Keine Einträge gefunden.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($eintraege->hasPages())
                    <div class="px-4 py-3 border-t border-border">
                        {{ $eintraege->links() }}
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
