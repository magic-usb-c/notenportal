@php
    $tage = (int) now()->startOfDay()->diffInDays($p->datum, false);
    $wann = match (true) {
        $tage === 0 => 'heute',
        $tage === 1 => 'morgen',
        $tage === -1 => 'gestern',
        $tage > 1 => 'in '.$tage.' Tagen',
        default => 'vor '.abs($tage).' Tagen',
    };
@endphp
<div class="px-5 py-3 flex flex-wrap items-center gap-x-4 gap-y-2">
    <div class="w-14 shrink-0 text-center">
        <div class="text-lg font-bold text-text tabular-nums leading-none">{{ $p->datum->format('d') }}</div>
        <div class="text-[11px] uppercase tracking-wider text-muted">{{ $p->datum->locale('de_CH')->translatedFormat('M') }}</div>
    </div>
    <div class="flex-1 min-w-0">
        <div class="font-medium text-text truncate">{{ $p->bezeichnung() }}</div>
        <div class="text-xs text-muted truncate">
            <span class="{{ $faellig ? 'text-yellow-700 dark:text-yellow-400 font-medium' : ($tage <= 7 ? 'text-accent font-medium' : '') }}">{{ $wann }}</span>
            · {{ \App\Support\Zahl::prozent($p->gewichtung_prozent) }}
            @if($p->titel) · {{ $p->titel }} @endif
        </div>
    </div>
    <div class="flex items-center gap-1 shrink-0">
        <a href="{{ route('lernender.noten.create', ['pruefung' => $p->pruefung_id]) }}"
           class="inline-flex items-center px-3 min-h-9 rounded-lg text-sm {{ $faellig ? 'bg-accent text-white np-btn-primary' : 'text-accent hover:bg-accent/10' }}">Note eintragen</a>
        <a href="{{ route('lernender.pruefungen.index', ['bearbeiten' => $p->pruefung_id]) }}#planen"
           class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-muted hover:text-text hover:bg-bg" aria-label="Bearbeiten">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
        </a>
        <form method="POST" action="{{ route('lernender.pruefungen.destroy', $p->pruefung_id) }}" onsubmit="return confirm('Prüfung entfernen?');">
            @csrf
            @method('DELETE')
            <button class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-muted hover:text-red-600 dark:hover:text-red-400 hover:bg-red-500/10" aria-label="Entfernen">×</button>
        </form>
    </div>
</div>
