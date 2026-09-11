<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Lernender;
use App\Models\Note;
use App\Models\User;
use App\Services\Noten\NoteService;
use App\Support\NotenSkala;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Suche der Befehlspalette.
 * Admin/BB: Lernende (sichtbar für die eigene Rolle), Admin zusätzlich Konten.
 * Lernende: nur die eigenen Fächer/Module und die eigenen Noten (Zugriff serverseitig
 * über den Lernenden aus der Session bestimmt, nie über Request-Parameter).
 */
class SucheController extends Controller
{
    public function __construct(private readonly NoteService $noteService) {}

    public function __invoke(Request $request): JsonResponse|RedirectResponse
    {
        // Nur für die Befehlspalette; direkter Aufruf im Browser (Lesezeichen, Verlauf) führt zur Übersicht
        if (! $request->wantsJson()) {
            return redirect()->route('dashboard');
        }

        $user = $request->user();
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $istNurLernender = $user->hasRole('Lernender') && ! $user->hasRole('Admin') && ! $user->hasRole('Berufsbildner');
        if ($istNurLernender) {
            return response()->json($this->sucheEigeneDaten($user, $q));
        }

        $like = '%'.addcslashes($q, '%_\\').'%';
        $bereich = $user->hasRole('Admin') ? 'admin' : 'trainer';

        $treffer = Lernender::sichtbarFuer($user)
            ->with(['benutzer', 'lehrberuf'])
            ->whereHas('benutzer', fn ($b) => $b->where(fn ($w) => $w->where('vorname', 'like', $like)
                ->orWhere('nachname', 'like', $like)->orWhere('email', 'like', $like)
                ->orWhereRaw("CONCAT(vorname, ' ', nachname) LIKE ?", [$like])))
            ->limit(8)->get()
            ->map(fn (Lernender $l) => [
                'label' => $l->benutzer->vorname.' '.$l->benutzer->nachname,
                'sub' => trim(($l->lehrberuf?->kuerzel ?? '').($l->lehrjahr() ? ' · '.$l->lehrjahr().'. Lehrjahr' : '')),
                'url' => route($bereich.'.learners.show', $l->lernender_id),
                'gruppe' => 'Lernende',
            ]);

        if ($bereich === 'admin') {
            $konten = User::query()
                ->whereDoesntHave('lernender')
                ->where(fn ($w) => $w->where('vorname', 'like', $like)->orWhere('nachname', 'like', $like)->orWhere('email', 'like', $like))
                ->limit(5)->get()
                ->map(fn (User $u) => [
                    'label' => $u->vorname.' '.$u->nachname,
                    'sub' => $u->email,
                    'url' => route('admin.users.edit', $u->benutzer_id),
                    'gruppe' => 'Konten',
                ]);
            $treffer = $treffer->concat($konten);
        }

        return response()->json($treffer->values());
    }

    /**
     * Suche für Lernende: nur die eigenen Fächer/Module (zum Erfassen) und die eigenen Noten.
     * Der Lernende wird ausschliesslich aus der Session (User::lernender) ermittelt, niemals
     * aus einem Request-Parameter – damit sind fremde Daten serverseitig ausgeschlossen.
     */
    private function sucheEigeneDaten(User $user, string $q): array
    {
        $lernender = $user->lernender;
        if (! $lernender) {
            return [];
        }

        $lernenderId = (int) $lernender->lernender_id;
        $qKlein = mb_strtolower($q);
        $like = '%'.addcslashes($q, '%_\\').'%';

        $bezuege = collect($this->noteService->bezugOptionen($lernenderId))
            ->flatten(1)
            ->filter(fn ($o) => str_contains(mb_strtolower($o['label']), $qKlein))
            ->take(6)
            ->map(fn ($o) => [
                'label' => $o['label'],
                'sub' => str_starts_with($o['wert'], 'modul:') ? 'Modul' : 'Fach',
                'url' => route('learner.grades.create', ['bezug' => $o['wert']]),
                'gruppe' => 'Fächer & Module',
            ])->values();

        $noten = Note::query()
            ->where('lernender_id', $lernenderId)
            ->where(fn ($w) => $w->where('titel', 'like', $like)
                ->orWhereHas('fach', fn ($f) => $f->where('name', 'like', $like))
                ->orWhereHas('modulBelegung.modul', fn ($m) => $m->where('titel', 'like', $like)
                    ->orWhere('modul_nummer', 'like', $like)))
            ->with(['fach', 'modulBelegung.modul'])
            ->orderByDesc('pruefungsdatum')
            ->limit(6)->get()
            ->map(fn (Note $n) => [
                'label' => $n->titel ?: ($n->fach?->name ?? trim(($n->modulBelegung?->modul?->modul_nummer ?? '').' '.($n->modulBelegung?->modul?->titel ?? ''))),
                'sub' => 'Note '.NotenSkala::format($n->note_wert).' · '.$n->pruefungsdatum?->format('d.m.Y'),
                'url' => route('learner.grades.index', ['_open' => $n->note_id]),
                'gruppe' => 'Eigene Noten',
            ])->values();

        return $bezuege->concat($noten)->values()->all();
    }
}
