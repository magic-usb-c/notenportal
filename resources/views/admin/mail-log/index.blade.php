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
                        class="h-9 rounded-lg border border-border-strong/60 bg-input px-2.5 text-sm text-text focus:border-accent focus:ring-2 focus:ring-ring/30 sm:w-40">
                    <option value="" @selected($status === '')>{{ __('Status: alle') }}</option>
                    @foreach(\App\Models\MailLog::STATUS as $value => $label)
                        <option value="{{ $value }}" @selected($status === $value)>{{ __($label) }}</option>
                    @endforeach
                </select>

                <label for="type" class="sr-only">{{ __('Anlass') }}</label>
                <select name="type" id="type" x-on:change="$el.form.requestSubmit()"
                        class="h-9 rounded-lg border border-border-strong/60 bg-input px-2.5 text-sm text-text focus:border-accent focus:ring-2 focus:ring-ring/30 sm:w-48">
                    <option value="" @selected($type === '')>{{ __('Anlass: alle') }}</option>
                    @foreach($anlaesse as $value => $label)
                        <option value="{{ $value }}" @selected($type === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </x-filterleiste>

            <div class="rounded-xl border border-border bg-card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm tabular-nums">
                        <thead class="sticky top-0 z-10 bg-surface-2">
                            <tr>
                                <th scope="col" class="h-9 px-3 text-left text-2xs font-medium text-muted whitespace-nowrap">{{ __('Zeit') }}</th>
                                <th scope="col" class="h-9 px-3 text-left text-2xs font-medium text-muted whitespace-nowrap">{{ __('Empfänger') }}</th>
                                <th scope="col" class="h-9 px-3 text-left text-2xs font-medium text-muted whitespace-nowrap">{{ __('Anlass') }}</th>
                                <th scope="col" class="h-9 px-3 text-left text-2xs font-medium text-muted">{{ __('Betreff') }}</th>
                                <th scope="col" class="h-9 px-3 text-left text-2xs font-medium text-muted whitespace-nowrap">{{ __('Status') }}</th>
                                <th scope="col" class="h-9 px-3 text-right text-2xs font-medium text-muted whitespace-nowrap">{{ __('Versuche') }}</th>
                                <th scope="col" class="h-9 px-3 text-left text-2xs font-medium text-muted">{{ __('Fehler') }}</th>
                                <th scope="col" class="h-9 px-3 text-right"><span class="sr-only">{{ __('Aktion') }}</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @forelse($eintraege as $e)
                                <tr class="group hover:bg-surface-2/60">
                                    <td class="px-3 py-2.5 text-muted whitespace-nowrap align-top">{{ $e->created_at?->timezone(config('app.timezone'))->format('d.m.Y H:i') }}</td>
                                    <td class="px-3 py-2.5 align-top text-left">
                                        <div>{{ $e->recipient }}</div>
                                        @if($e->redirected_to)
                                            <div class="text-xs text-muted">{{ __('→ umgeleitet an :ziel', ['ziel' => $e->redirected_to]) }}</div>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2.5 whitespace-nowrap align-top">{{ $anlaesse[$e->type] ?? $e->type }}</td>
                                    <td class="px-3 py-2.5 align-top max-w-sm truncate">{{ $e->subject }}</td>
                                    <td class="px-3 py-2.5 whitespace-nowrap align-top">
                                        <x-status :status="$statusMap[$e->status] ?? 'neutral'" :text="__(\App\Models\MailLog::STATUS[$e->status] ?? $e->status)" />
                                    </td>
                                    <td class="px-3 py-2.5 text-right align-top">{{ $e->attempts }}</td>
                                    <td class="px-3 py-2.5 align-top max-w-xs truncate" @if($e->error) title="{{ $e->error }}" @endif>{{ $e->error }}</td>
                                    <td class="px-3 py-2.5 text-right align-top whitespace-nowrap">
                                        @if(in_array($e->status, [\App\Models\MailLog::FAILED, \App\Models\MailLog::SKIPPED], true))
                                            <form method="POST" action="{{ route('admin.mail-log.retry', $e->id) }}"
                                                  x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true"
                                                  class="opacity-0 group-hover:opacity-100 group-focus-within:opacity-100 max-md:opacity-100">
                                                @csrf
                                                <button type="submit" :disabled="loading"
                                                        class="inline-flex items-center px-3 h-9 rounded-lg glass-btn text-text text-xs disabled:opacity-60">{{ __('Erneut senden') }}</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="p-6 text-center text-muted">{{ __('Keine Einträge gefunden.') }}</td>
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
