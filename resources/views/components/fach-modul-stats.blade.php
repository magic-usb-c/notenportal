{{-- resources/views/components/fach-modul-stats.blade.php
     Aufklappbare Ø-pro-Fach/Modul-Auswertung mit Balken.
     Erwartet $stats: Collection mit ->label, ->count, ->avg --}}
@props(['stats'])

@if($stats->count() > 1)
    <div class="glass rounded-2xl overflow-hidden">
        <details class="np-fachstats">
            <summary class="cursor-pointer select-none list-none px-5 py-4 flex items-center justify-between hover:bg-bg/60">
                <span class="font-semibold text-text text-sm">Ø pro Fach / Modul</span>
                <span class="text-[11px] text-muted">{{ $stats->count() }} Fächer/Module</span>
            </summary>
            <div class="border-t border-border divide-y divide-border">
                @foreach($stats as $fs)
                    @php
                        $fsAvg = $fs->avg !== null ? (float) $fs->avg : null;
                        $fsColor = $fsAvg === null ? 'text-muted'
                            : ($fsAvg >= 5.0 ? 'text-green-700 dark:text-green-400'
                            : ($fsAvg >= 4.0 ? 'text-emerald-700 dark:text-emerald-400'
                            : ($fsAvg >= 3.5 ? 'text-yellow-700 dark:text-yellow-400'
                            : 'text-red-600 dark:text-red-400')));
                        $fsBarBg = $fsAvg === null ? 'bg-muted/30'
                            : ($fsAvg >= 5.0 ? 'bg-green-500'
                            : ($fsAvg >= 4.0 ? 'bg-emerald-500'
                            : ($fsAvg >= 3.5 ? 'bg-yellow-500'
                            : 'bg-red-500')));
                        // Skala beginnt bei Note 1 (Untergrenze), nicht bei 0
                        $fsBarWidth = $fsAvg !== null ? max(0, min(100, round((($fsAvg - 1) / 5) * 100))) : 0;
                    @endphp
                    <div class="flex items-center gap-3 px-5 py-2.5">
                        <span class="text-xs text-text w-48 shrink-0 truncate" title="{{ $fs->label }}">{{ $fs->label }}</span>
                        <div class="relative flex-1 bg-bg rounded-full h-2 overflow-hidden border border-border">
                            <div class="h-full rounded-full {{ $fsBarBg }}" style="width: {{ $fsBarWidth }}%"></div>
                            <div class="absolute inset-y-0 w-px bg-text/40" style="left: 60%" aria-hidden="true"></div>
                        </div>
                        <span class="text-sm font-semibold tabular-nums {{ $fsColor }} w-12 text-right">
                            {{ $fsAvg !== null ? number_format($fsAvg, 2) : '–' }}
                        </span>
                        <span class="text-[11px] text-muted tabular-nums w-16 text-right shrink-0">
                            {{ $fs->count }} {{ $fs->count === 1 ? 'Note' : 'Noten' }}
                        </span>
                    </div>
                @endforeach
            </div>
        </details>
    </div>
@endif
