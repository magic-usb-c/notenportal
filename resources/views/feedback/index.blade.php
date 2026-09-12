<x-app-layout>
    <x-slot name="title">{{ __('Meine Meldungen') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :titel="__('Meine Meldungen')" schmal>
            <x-slot:aktionen>
                <button type="button" onclick="window.dispatchEvent(new CustomEvent('open-modal', { detail: 'feedback' }))"
                        class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-accent px-3.5 text-sm font-medium text-accent-contrast np-btn-primary">
                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                    {{ __('Meldung erfassen') }}
                </button>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-4 sm:px-6 lg:px-8">
            <div class="max-w-3xl rounded-xl border border-border bg-card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm text-text">
                        <thead class="sticky top-0 z-10 bg-bg text-muted shadow-xs">
                            <tr>
                                <th class="text-left p-3 whitespace-nowrap">{{ __('Datum') }}</th>
                                <th class="text-left p-3 whitespace-nowrap">{{ __('Kategorie') }}</th>
                                <th class="text-left p-3">{{ __('Text') }}</th>
                                <th class="text-left p-3 whitespace-nowrap">{{ __('Status') }}</th>
                                <th class="text-left p-3">{{ __('Antwort') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @forelse($meldungen as $m)
                                @php
                                    $statusClasses = match ($m->status) {
                                        \App\Models\Feedback::STATUS_ERLEDIGT => 'bg-note-gut/14 text-note-gut',
                                        \App\Models\Feedback::STATUS_IN_ARBEIT => 'bg-accent/10 text-accent-text',
                                        default => 'bg-note-knapp/14 text-note-knapp',
                                    };
                                    $gekuerzt = mb_strlen($m->text) > 140;
                                @endphp
                                <tr class="even:bg-bg/30">
                                    <td class="p-3 text-muted whitespace-nowrap">{{ $m->erstellt_am->format('d.m.Y H:i') }}</td>
                                    <td class="p-3 whitespace-nowrap">{{ __(\App\Models\Feedback::kategorieLabel($m->kategorie)) }}</td>
                                    <td class="p-3 max-w-md">
                                        @if($gekuerzt)
                                            <details class="np-details">
                                                <summary class="cursor-pointer select-none list-none">
                                                    {{ Str::limit($m->text, 140) }}
                                                    <span class="text-accent text-xs">{{ __('mehr') }}</span>
                                                </summary>
                                                <p class="mt-1 whitespace-pre-wrap">{{ $m->text }}</p>
                                            </details>
                                        @else
                                            <span class="whitespace-pre-wrap">{{ $m->text }}</span>
                                        @endif
                                        @if($m->istDuplikat())
                                            <p class="mt-1 text-xs text-muted">{{ __('Mit einer gleichen Meldung zusammengeführt') }}</p>
                                        @elseif($mitStimmen && ($m->stimmen_count ?? 0) > 0)
                                            <p class="mt-1 text-xs text-muted">{{ __(':n Personen betrifft das auch', ['n' => $m->stimmen_count]) }}</p>
                                        @endif
                                    </td>
                                    <td class="p-3 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs {{ $statusClasses }}">
                                            {{ __(\App\Models\Feedback::STATUS[$m->status] ?? $m->status) }}
                                        </span>
                                    </td>
                                    <td class="p-3 max-w-xs">
                                        @if($m->admin_notiz)
                                            <span class="whitespace-pre-wrap">{{ $m->admin_notiz }}</span>
                                        @else
                                            <span class="text-muted">–</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="p-10 text-center text-muted">
                                        <span class="mx-auto mb-2 inline-flex size-10 items-center justify-center rounded-full bg-accent/10 text-accent" aria-hidden="true">
                                            <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h8M8 14h5m-9 6l2.5-3H18a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v14z"/></svg>
                                        </span>
                                        <p class="text-text font-medium mb-1">{{ __('Noch keine Meldungen') }}</p>
                                        <p class="text-sm max-w-sm mx-auto">
                                            {{ __('Fehler, Ideen, Fragen oder sonst etwas – nutze den Knopf oben oder') }}
                                            <kbd class="px-1 py-0.5 rounded-md border border-border text-xs">{{ __('Strg/Cmd K') }}</kbd>
                                            {{ __('um deine erste Meldung zu erfassen.') }}
                                        </p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($meldungen->hasPages())
                    <div class="px-4 py-3 border-t border-border">
                        {{ $meldungen->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
