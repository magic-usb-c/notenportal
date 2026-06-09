<x-app-layout>
    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-4">
            <div>
                <nav class="text-xs text-muted flex items-center gap-1 mb-1">
                    <a href="{{ route('admin.lernende.index') }}" class="hover:text-text transition-colors">Lernende</a>
                    <span class="text-muted/40">›</span>
                    <span class="text-text">{{ $lernender->nachname }} {{ $lernender->vorname }}</span>
                </nav>
                <h2 class="font-semibold text-xl text-text">
                    {{ $lernender->nachname }} {{ $lernender->vorname }}
                </h2>
                <p class="text-xs text-muted mt-0.5">{{ $lernender->email }}</p>
            </div>
            <a href="{{ route('admin.lernende.index') }}"
               class="px-4 py-2 h-10 rounded-xl bg-card text-text border border-border hover:bg-bg text-sm whitespace-nowrap">
                Zurück
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-5">

            @if(session('status'))
                <div class="rounded-xl border border-green-300 bg-green-50 dark:bg-green-900/20 dark:border-green-700 px-4 py-3 text-sm text-green-800 dark:text-green-200" data-autohide>
                    {{ session('status') }}
                </div>
            @endif

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
                        <a href="{{ route('admin.lernende.profil.edit', $lernender_id) }}"
                           class="px-3 py-1.5 rounded-xl border border-accent text-accent text-xs hover:bg-accent hover:text-white transition-colors">Profil bearbeiten</a>
                        <a href="{{ route('admin.benutzer.edit', $profil->benutzer_id) }}"
                           class="px-3 py-1.5 rounded-xl border border-border text-xs hover:bg-bg">Benutzer bearbeiten</a>
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
                        <div class="pt-1">
                            <a href="{{ route('admin.lernende.index', ['berufsbildner_id' => $aktuellerBB->berufsbildner_id]) }}"
                               class="text-xs text-accent hover:underline">Lernende dieses BB anzeigen</a>
                        </div>
                    @else
                        <p class="text-sm text-muted italic">Kein aktiver Berufsbildner erfasst.</p>
                        <div class="pt-1">
                            <a href="{{ route('admin.lernende.betreuung', $lernender_id) }}"
                               class="text-xs text-accent hover:underline">Betreuung einrichten</a>
                        </div>
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
                                    <th class="text-center p-3 whitespace-nowrap">Ø gewichtet</th>
                                    <th class="text-center p-3 whitespace-nowrap">Bestanden</th>
                                    <th class="text-center p-3 whitespace-nowrap">Quote</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                @foreach($semStats as $s)
                                    @php
                                        $avg = $s->avg !== null ? (float)$s->avg : null;
                                        $nc = $avg !== null
                                            ? ($avg >= 5.0 ? 'text-green-600 dark:text-green-400'
                                                : ($avg >= 4.0 ? 'text-emerald-600 dark:text-emerald-400'
                                                : ($avg >= 3.5 ? 'text-yellow-600 dark:text-yellow-400'
                                                : 'text-red-600 dark:text-red-400')))
                                            : 'text-muted';
                                        $passed = (int) ($s->passed ?? 0);
                                        $quote = $s->count > 0 ? round($passed / $s->count * 100) : null;
                                        $qc = $quote === null ? 'text-muted'
                                            : ($quote >= 75 ? 'text-green-600 dark:text-green-400'
                                            : ($quote >= 50 ? 'text-yellow-600 dark:text-yellow-400'
                                            : 'text-red-600 dark:text-red-400'));
                                    @endphp
                                    <tr class="hover:bg-bg">
                                        <td class="p-3 font-medium">
                                            <a href="{{ route('admin.lernende.noten.index', ['lernender_id' => $lernender_id, 'semester_id' => $s->semester_id]) }}"
                                               class="hover:text-accent">{{ $s->sem_label }}</a>
                                        </td>
                                        <td class="p-3 text-center text-muted">{{ $s->count }}</td>
                                        <td class="p-3 text-center font-semibold {{ $nc }}">
                                            {{ $avg !== null ? number_format($avg, 2) : '–' }}
                                        </td>
                                        <td class="p-3 text-center text-muted">{{ $passed }} / {{ $s->count }}</td>
                                        <td class="p-3 text-center font-semibold {{ $qc }}">
                                            {{ $quote !== null ? $quote . ' %' : '–' }}
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
                            $c = $nw >= 5.0 ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300'
                               : ($nw >= 4.0 ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300'
                               : ($nw >= 3.5 ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300'
                               : 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300'));
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
