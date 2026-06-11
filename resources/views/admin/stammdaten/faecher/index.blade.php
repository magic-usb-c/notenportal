<x-app-layout>
    <x-slot name="title">Fächer</x-slot>
    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-text">Fächer</h2>
            <a href="{{ route('admin.stammdaten.faecher.create') }}"
               class="inline-flex items-center gap-2 px-4 py-2 h-10 rounded-xl bg-accent text-white np-btn-primary">
                <span class="text-lg leading-none">+</span> Neues Fach
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-4">


            <div class="glass rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-bg border-b border-border text-muted">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium">Kürzel</th>
                            <th class="px-4 py-3 text-left font-medium">Name</th>
                            <th class="px-4 py-3 text-left font-medium">Track</th>
                            <th class="px-4 py-3 text-left font-medium">Lehrberufe</th>
                            <th class="px-4 py-3 text-left font-medium">Status</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse($faecher as $f)
                            <tr class="hover:bg-bg/50">
                                <td class="px-4 py-3 font-mono font-semibold text-text">{{ $f->kurzname }}</td>
                                <td class="px-4 py-3 text-text">{{ $f->name }}</td>
                                <td class="px-4 py-3">
                                    <span class="px-2 py-0.5 rounded-full text-xs
                                        {{ $f->track_typ === 'BMS' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300' : 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300' }}">
                                        {{ $f->track_typ }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-muted">{{ $f->lehrberuf_count }}</td>
                                <td class="px-4 py-3">
                                    @if($f->aktiv)
                                        <span class="px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300">aktiv</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-full text-xs bg-bg text-muted border border-border">inaktiv</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.stammdaten.faecher.edit', $f->fach_id) }}"
                                       class="text-sm text-accent hover:underline">Bearbeiten</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-muted">
                                    Noch keine Fächer erfasst.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            </div>

            <p class="text-xs text-muted px-1">
                Fächer werden über die
                <a href="{{ route('admin.stammdaten.lehrberufe.index') }}" class="text-accent hover:underline">Lehrberuf-Detailseite</a>
                einem Lehrberuf zugewiesen.
            </p>

        </div>
    </div>
</x-app-layout>
