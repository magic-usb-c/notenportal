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
                    <thead class="sticky top-0 bg-surface-2">
                        <tr>
                            <th scope="col" class="h-9 px-4 text-left text-2xs font-medium text-muted">Kürzel</th>
                            <th scope="col" class="h-9 px-4 text-left text-2xs font-medium text-muted">Name</th>
                            <th scope="col" class="h-9 px-4 text-left text-2xs font-medium text-muted">Status</th>
                            <th scope="col" class="h-9 px-4"><span class="sr-only">Aktionen</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse($lehrberufe as $lb)
                            <tr class="group h-11 border-b border-border last:border-0 hover:bg-surface-2/60">
                                <td class="px-4 font-mono font-semibold text-text">{{ $lb->kuerzel }}</td>
                                <td class="px-4 text-text">{{ $lb->name }}</td>
                                <td class="px-4">
                                    @if($lb->aktiv)
                                        <span class="px-2 py-0.5 rounded-md text-xs bg-note-gut/14 text-note-gut">aktiv</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-md text-xs bg-surface-2 text-muted border border-border">inaktiv</span>
                                    @endif
                                </td>
                                <td class="px-4 text-right">
                                    <div class="flex items-center justify-end gap-3 opacity-0 group-hover:opacity-100 group-focus-within:opacity-100 max-md:opacity-100">
                                        <a href="{{ route('admin.master-data.professions.edit', $lb->lehrberuf_id) }}"
                                           class="inline-flex items-center px-3 min-h-9 rounded-lg text-sm text-accent-text hover:bg-accent/10">Bearbeiten</a>
                                        <a href="{{ route('admin.master-data.professions.show', $lb->lehrberuf_id) }}"
                                           class="text-sm text-accent-text hover:underline whitespace-nowrap">Module & Fächer</a>
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
