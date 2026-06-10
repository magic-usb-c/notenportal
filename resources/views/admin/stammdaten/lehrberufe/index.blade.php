<x-app-layout>
    <x-slot name="title">Lehrberufe</x-slot>
    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-text">Lehrberufe</h2>
            <a href="{{ route('admin.stammdaten.lehrberufe.create') }}"
               class="inline-flex items-center gap-2 px-4 py-2 h-10 rounded-xl bg-accent text-white np-btn-primary">
                <span class="text-lg leading-none">+</span> Neuer Lehrberuf
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">

            @if(session('status'))
                <div class="rounded-xl border border-green-300 bg-green-50 dark:bg-green-900/20 dark:border-green-700 px-4 py-3 text-sm text-green-800 dark:text-green-200" data-autohide>
                    {{ session('status') }}
                </div>
            @endif

            <div class="bg-card border border-border rounded-2xl shadow-sm overflow-hidden">
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
                                        <span class="px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300">aktiv</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-full text-xs bg-bg text-muted border border-border">inaktiv</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-3">
                                        <a href="{{ route('admin.stammdaten.lehrberufe.edit', $lb->lehrberuf_id) }}"
                                           class="text-sm text-accent hover:underline">Bearbeiten</a>
                                        <a href="{{ route('admin.stammdaten.lehrberufe.show', $lb->lehrberuf_id) }}"
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
</x-app-layout>
