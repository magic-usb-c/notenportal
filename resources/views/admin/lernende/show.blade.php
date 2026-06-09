<x-app-layout>
    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-text">
                    {{ $lernender->nachname }} {{ $lernender->vorname }}
                </h2>
                <p class="text-sm text-muted mt-0.5">{{ $lernender->email }}</p>
            </div>
            <a href="{{ route('admin.lernende.index') }}"
               class="px-4 py-2 h-10 rounded-xl bg-card text-text border border-border hover:bg-bg text-sm whitespace-nowrap">
                Zurück
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-5">

            {{-- Profil + BB --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div class="bg-card border border-border rounded-2xl shadow-sm p-5 space-y-2">
                    <h3 class="font-semibold text-text">Profil</h3>
                    <dl class="text-sm space-y-1.5">
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted">Lehrberuf</dt>
                            <dd class="text-text font-medium">{{ $profil?->lehrberuf ?? '–' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted">Lehrbeginn</dt>
                            <dd class="text-text">
                                {{ $profil?->lehrbeginn ? \Carbon\Carbon::parse($profil->lehrbeginn)->format('d.m.Y') : '–' }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-muted">Lehrende</dt>
                            <dd class="text-text">
                                {{ $profil?->lehrende ? \Carbon\Carbon::parse($profil->lehrende)->format('d.m.Y') : '–' }}
                            </dd>
                        </div>
                    </dl>
                    <div class="pt-2 flex flex-wrap gap-2">
                        <a href="{{ route('admin.lernende.betreuung', $lernender_id) }}"
                           class="px-3 py-1.5 rounded-xl border border-border text-xs hover:bg-bg">Betreuungen</a>
                        <a href="{{ route('admin.lernende.tracks', $lernender_id) }}"
                           class="px-3 py-1.5 rounded-xl border border-border text-xs hover:bg-bg">Tracks</a>
                        <a href="{{ route('admin.lernende.noten.index', $lernender_id) }}"
                           class="px-3 py-1.5 rounded-xl bg-accent text-white text-xs hover:opacity-90">Alle Noten</a>
                    </div>
                </div>

                <div class="bg-card border border-border rounded-2xl shadow-sm p-5 space-y-2">
                    <h3 class="font-semibold text-text">Aktueller Berufsbildner</h3>
                    @if($aktuellerBB)
                        <p class="text-text font-medium">{{ $aktuellerBB->nachname }} {{ $aktuellerBB->vorname }}</p>
                        <p class="text-sm text-muted">{{ $aktuellerBB->email }}</p>
                    @else
                        <p class="text-sm text-muted italic">Kein aktiver Berufsbildner erfasst.</p>
                    @endif
                </div>
            </div>

            {{-- Semester-Übersicht --}}
            <div class="bg-card border border-border rounded-2xl shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-border">
                    <h3 class="font-semibold text-text">Noten nach Semester</h3>
                </div>
                @if($semStats->isEmpty())
                    <div class="px-5 py-6 text-sm text-muted text-center">Noch keine Noten erfasst.</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm text-text">
                            <thead class="bg-bg text-muted">
                                <tr>
                                    <th class="text-left p-3">Semester</th>
                                    <th class="text-center p-3">Noten</th>
                                    <th class="text-center p-3">Ø gewichtet</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                @foreach($semStats as $s)
                                    @php
                                        $avg = $s->avg !== null ? (float)$s->avg : null;
                                        $nc = $avg !== null
                                            ? ($avg >= 4.0 ? 'text-green-600 dark:text-green-400' : ($avg >= 3.5 ? 'text-yellow-600 dark:text-yellow-400' : 'text-red-600 dark:text-red-400'))
                                            : 'text-muted';
                                    @endphp
                                    <tr class="hover:bg-bg">
                                        <td class="p-3 font-medium">{{ $s->sem_label }}</td>
                                        <td class="p-3 text-center text-muted">{{ $s->count }}</td>
                                        <td class="p-3 text-center font-semibold {{ $nc }}">
                                            {{ $avg !== null ? number_format($avg, 2) : '–' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            {{-- Letzte 5 Noten --}}
            <div class="bg-card border border-border rounded-2xl shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-border flex items-center justify-between">
                    <h3 class="font-semibold text-text">Zuletzt erfasste Noten</h3>
                    <a href="{{ route('admin.lernende.noten.index', $lernender_id) }}"
                       class="text-xs text-accent hover:underline">Alle anzeigen</a>
                </div>
                @forelse($letzteNoten as $n)
                    <div class="px-5 py-3 border-b border-border last:border-0 flex items-center justify-between gap-3">
                        <div>
                            <div class="text-sm text-text">
                                {{ $n->fach_name ?? ($n->modul_nummer ? $n->modul_nummer.' – '.$n->modul_titel : ($n->titel ?? '–')) }}
                            </div>
                            <div class="text-xs text-muted">{{ \Carbon\Carbon::parse($n->pruefungsdatum)->format('d.m.Y') }}</div>
                        </div>
                        @php
                            $nw = (float)$n->note_wert;
                            $c = $nw >= 4.0 ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300'
                               : ($nw >= 3.5 ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300'
                               : 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300');
                        @endphp
                        <span class="inline-flex items-center justify-center min-w-[3rem] px-2 py-1 rounded-xl font-bold text-sm {{ $c }}">
                            {{ number_format($nw, 1) }}
                        </span>
                    </div>
                @empty
                    <div class="px-5 py-6 text-sm text-muted text-center">Noch keine Noten erfasst.</div>
                @endforelse
            </div>

        </div>
    </div>
</x-app-layout>
