<x-app-layout>
    <x-slot name="title">Lehrberufe</x-slot>
    <x-slot name="header">
        <x-seitenkopf titel="Lehrberufe">
            <x-slot:aktionen>
                <a href="{{ route('admin.master-data.professions.create') }}"
                   class="inline-flex h-9 items-center gap-2 rounded-lg bg-accent px-3.5 text-sm font-medium text-accent-contrast np-btn-primary">
                    <span class="text-lg leading-none">+</span> Neuer Lehrberuf
                </a>
            </x-slot:aktionen>
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-4">

            <div class="rounded-xl border border-border bg-card overflow-hidden">
                <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-bg border-b border-border text-muted">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium">Kürzel</th>
                            <th class="px-4 py-3 text-left font-medium">Name</th>
                            <th class="px-4 py-3 text-left font-medium">Status</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse($lehrberufe as $lb)
                            <tr class="hover:bg-bg/50">
                                <td class="px-4 py-3 font-mono font-semibold text-text">{{ $lb->kuerzel }}</td>
                                <td class="px-4 py-3 text-text">{{ $lb->name }}</td>
                                <td class="px-4 py-3">
                                    @if($lb->aktiv)
                                        <span class="px-2 py-0.5 rounded-full text-xs bg-note-gut/14 text-note-gut">aktiv</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-full text-xs bg-bg text-muted border border-border">inaktiv</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-3">
                                        <a href="{{ route('admin.master-data.professions.edit', $lb->lehrberuf_id) }}"
                                           class="inline-flex items-center px-3 min-h-9 rounded-lg text-sm text-accent hover:bg-accent/10">Bearbeiten</a>
                                        <a href="{{ route('admin.master-data.professions.show', $lb->lehrberuf_id) }}"
                                           class="text-sm text-accent hover:underline whitespace-nowrap">Module & Fächer</a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-muted">
                                    Noch keine Lehrberufe erfasst.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            </div>

        </div>
    </div>
</x-app-layout>
