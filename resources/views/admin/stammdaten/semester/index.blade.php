<x-app-layout>
    <x-slot name="title">Semester</x-slot>
    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-text">Semester</h2>
            <a href="{{ route('admin.stammdaten.semester.create') }}"
               class="inline-flex items-center gap-2 px-4 py-2 h-10 rounded-xl bg-accent text-white np-btn-primary">
                <span class="text-lg leading-none">+</span> Neues Semester
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
                            <th class="px-4 py-3 text-left font-medium">Bezeichnung</th>
                            <th class="px-4 py-3 text-left font-medium">Von</th>
                            <th class="px-4 py-3 text-left font-medium">Bis</th>
                            <th class="px-4 py-3 text-left font-medium">Sortierung</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @php $today = now()->toDateString(); @endphp
                        @forelse($semester as $s)
                            @php
                                $isAktiv = $s->start_datum <= $today && $s->end_datum >= $today;
                            @endphp
                            <tr class="hover:bg-bg/50 {{ $isAktiv ? 'bg-accent/5' : '' }}">
                                <td class="px-4 py-3 font-semibold text-text">
                                    {{ $s->bezeichnung }}
                                    @if($isAktiv)
                                        <span class="ml-2 px-2 py-0.5 rounded-full text-xs bg-green-500/15 text-green-700 dark:text-green-400">aktuell</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-muted tabular-nums">{{ \Carbon\Carbon::parse($s->start_datum)->format('d.m.Y') }}</td>
                                <td class="px-4 py-3 text-muted tabular-nums">{{ \Carbon\Carbon::parse($s->end_datum)->format('d.m.Y') }}</td>
                                <td class="px-4 py-3 text-muted">{{ $s->sortierung }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.stammdaten.semester.edit', $s->semester_id) }}"
                                       class="inline-flex items-center px-3 min-h-9 rounded-lg text-sm text-accent hover:bg-accent/10">Bearbeiten</a>
                                    <form method="POST" action="{{ route('admin.stammdaten.semester.destroy', $s->semester_id) }}" class="inline"
                                          onsubmit="return confirm('Semester {{ $s->bezeichnung }} löschen?')"
                                          x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                                        @csrf @method('DELETE')
                                        <button :disabled="loading" class="inline-flex items-center px-3 min-h-9 rounded-lg text-sm text-red-600 dark:text-red-400 hover:bg-red-500/10 disabled:opacity-60">Löschen</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-muted">
                                    Noch keine Semester erfasst.
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
