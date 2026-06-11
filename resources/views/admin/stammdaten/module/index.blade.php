<x-app-layout>
    <x-slot name="title">Module</x-slot>
    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-text">Module</h2>
            <a href="{{ route('admin.stammdaten.module.create') }}"
               class="inline-flex items-center gap-2 px-4 py-2 h-10 rounded-xl bg-accent text-white np-btn-primary">
                <span class="text-lg leading-none">+</span> Neues Modul
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">


            <div class="glass rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-bg border-b border-border text-muted">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium">Nummer</th>
                            <th class="px-4 py-3 text-left font-medium">Titel</th>
                            <th class="px-4 py-3 text-left font-medium">Lehrberufe</th>
                            <th class="px-4 py-3 text-left font-medium">Status</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse($module as $m)
                            <tr class="hover:bg-bg/50">
                                <td class="px-4 py-3 font-mono font-semibold text-text">{{ $m->modul_nummer }}</td>
                                <td class="px-4 py-3 text-text">{{ $m->titel }}</td>
                                <td class="px-4 py-3 text-muted">{{ $m->lehrberuf_count }}</td>
                                <td class="px-4 py-3">
                                    @if($m->aktiv)
                                        <span class="px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300">aktiv</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-full text-xs bg-bg text-muted border border-border">inaktiv</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.stammdaten.module.edit', $m->modul_id) }}"
                                       class="text-sm text-accent hover:underline">Bearbeiten</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-muted">
                                    Noch keine Module erfasst.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            </div>

            <p class="text-xs text-muted px-1">
                Module werden über die
                <a href="{{ route('admin.stammdaten.lehrberufe.index') }}" class="text-accent hover:underline">Lehrberuf-Detailseite</a>
                einem Lehrberuf zugewiesen.
            </p>

        </div>
    </div>
</x-app-layout>
