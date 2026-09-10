<x-app-layout>
    <x-slot name="title">Meine Meldungen</x-slot>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-text">Meine Meldungen</h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">
            <div class="glass rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm text-text">
                        <thead class="sticky top-0 z-10 bg-bg text-muted shadow-xs">
                            <tr>
                                <th class="text-left p-3 whitespace-nowrap">Datum</th>
                                <th class="text-left p-3 whitespace-nowrap">Kategorie</th>
                                <th class="text-left p-3">Text</th>
                                <th class="text-left p-3 whitespace-nowrap">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border">
                            @forelse($meldungen as $m)
                                @php
                                    $statusClasses = match ($m->status) {
                                        \App\Models\Feedback::STATUS_ERLEDIGT => 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300',
                                        \App\Models\Feedback::STATUS_IN_ARBEIT => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300',
                                        default => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-300',
                                    };
                                    $gekuerzt = mb_strlen($m->text) > 140;
                                @endphp
                                <tr class="even:bg-bg/30">
                                    <td class="p-3 text-muted whitespace-nowrap">{{ $m->erstellt_am->format('d.m.Y H:i') }}</td>
                                    <td class="p-3 whitespace-nowrap">{{ \App\Models\Feedback::KATEGORIEN[$m->kategorie] ?? $m->kategorie }}</td>
                                    <td class="p-3 max-w-md">
                                        @if($gekuerzt)
                                            <details class="np-details">
                                                <summary class="cursor-pointer select-none list-none">
                                                    {{ Str::limit($m->text, 140) }}
                                                    <span class="text-accent text-xs">mehr</span>
                                                </summary>
                                                <p class="mt-1 whitespace-pre-wrap">{{ $m->text }}</p>
                                            </details>
                                        @else
                                            <span class="whitespace-pre-wrap">{{ $m->text }}</span>
                                        @endif
                                    </td>
                                    <td class="p-3 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs {{ $statusClasses }}">
                                            {{ \App\Models\Feedback::STATUS[$m->status] ?? $m->status }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="p-6 text-center text-muted">Keine Meldungen erfasst.</td>
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
