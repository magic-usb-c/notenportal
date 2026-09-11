<x-app-layout>
    <x-slot name="title">Versandprotokoll</x-slot>
    <x-slot name="header">
        <x-seitenkopf titel="Versandprotokoll" />
    </x-slot>

    @php
        $statusMap = ['sent' => 'gruen', 'failed' => 'rot', 'skipped' => 'neutral', 'queued' => 'gelb'];
        $kacheln = [
            ['label' => 'Verschickt (7 Tage)', 'wert' => $kennzahlen['verschickt']],
            ['label' => 'Fehlgeschlagen (7 Tage)', 'wert' => $kennzahlen['fehlgeschlagen']],
            ['label' => 'Nicht zustellbar (7 Tage)', 'wert' => $kennzahlen['nicht_zustellbar']],
            ['label' => 'In Warteschlange', 'wert' => $kennzahlen['warteschlange']],
        ];
    @endphp

    <div class="py-6">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 flex flex-col gap-4">

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                @foreach($kacheln as $k)
                    <x-kachel :label="$k['label']" :wert="$k['wert']" />
                @endforeach
            </div>

            <div class="rounded-xl border border-border bg-card p-4">
                <form method="GET" action="{{ route('admin.mail-log.index') }}"
                      class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end">
                    <div>
                        <label for="status" class="text-xs uppercase tracking-widest text-muted font-medium">Status</label>
                        <select name="status" id="status" onchange="this.form.submit()"
                                class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring text-sm">
                            <option value="" @selected($status === '')>Alle</option>
                            @foreach(\App\Models\MailLog::STATUS as $value => $label)
                                <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="type" class="text-xs uppercase tracking-widest text-muted font-medium">Anlass</label>
                        <select name="type" id="type" onchange="this.form.submit()"
                                class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring text-sm">
                            <option value="" @selected($type === '')>Alle</option>
                            @foreach($anlaesse as $value => $label)
                                <option value="{{ $value }}" @selected($type === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="q" class="text-xs uppercase tracking-widest text-muted font-medium">Empfänger</label>
                        <input type="text" name="q" id="q" value="{{ $q }}" placeholder="E-Mail-Adresse"
                               class="mt-1 w-full rounded-xl border border-border bg-input text-text px-3 py-2 focus:ring-2 focus:ring-ring focus:border-ring text-sm">
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="submit" class="inline-flex px-4 h-10 rounded-xl bg-accent text-accent-contrast text-sm font-semibold np-btn-primary">Suchen</button>
                        @if($status || $type || $q)
                            <a href="{{ route('admin.mail-log.index') }}" class="inline-flex px-3 h-10 rounded-xl glass-btn text-text items-center text-sm">Zurücksetzen</a>
                        @endif
                    </div>
                </form>
            </div>

            <div class="rounded-xl border border-border bg-card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm text-text">
                        <thead class="sticky top-0 z-10 bg-bg text-muted shadow-xs">
                            <tr>
                                <th class="text-left p-3 whitespace-nowrap">Zeit</th>
                                <th class="text-left p-3 whitespace-nowrap">Empfänger</th>
                                <th class="text-left p-3 whitespace-nowrap">Anlass</th>
                                <th class="text-left p-3">Betreff</th>
                                <th class="text-left p-3 whitespace-nowrap">Status</th>
                                <th class="text-right p-3 whitespace-nowrap">Versuche</th>
                                <th class="text-left p-3">Fehler</th>
                                <th class="text-right p-3 whitespace-nowrap">Aktion</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @forelse($eintraege as $e)
                                <tr class="even:bg-bg/30">
                                    <td class="p-3 text-muted whitespace-nowrap align-top tabular-nums">{{ $e->created_at?->timezone(config('app.timezone'))->format('d.m.Y H:i') }}</td>
                                    <td class="p-3 align-top">
                                        <div>{{ $e->recipient }}</div>
                                        @if($e->redirected_to)
                                            <div class="text-xs text-muted">→ umgeleitet an {{ $e->redirected_to }}</div>
                                        @endif
                                    </td>
                                    <td class="p-3 whitespace-nowrap align-top">{{ $anlaesse[$e->type] ?? $e->type }}</td>
                                    <td class="p-3 align-top max-w-sm truncate">{{ $e->subject }}</td>
                                    <td class="p-3 whitespace-nowrap align-top">
                                        <x-status :status="$statusMap[$e->status] ?? 'neutral'" :text="\App\Models\MailLog::STATUS[$e->status] ?? $e->status" />
                                    </td>
                                    <td class="p-3 text-right align-top tabular-nums">{{ $e->attempts }}</td>
                                    <td class="p-3 align-top max-w-xs truncate" @if($e->error) title="{{ $e->error }}" @endif>{{ $e->error }}</td>
                                    <td class="p-3 text-right align-top whitespace-nowrap">
                                        @if(in_array($e->status, [\App\Models\MailLog::FAILED, \App\Models\MailLog::SKIPPED], true))
                                            <form method="POST" action="{{ route('admin.mail-log.retry', $e->id) }}"
                                                  x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                                                @csrf
                                                <button type="submit" :disabled="loading"
                                                        class="inline-flex items-center px-3 h-9 rounded-xl glass-btn text-text text-xs disabled:opacity-60">Erneut senden</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="p-6 text-center text-muted">Keine Einträge gefunden.</td>
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
