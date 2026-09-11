<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Lernender;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Suche der Befehlspalette: Lernende (sichtbar für Admin/BB), für Admins zusätzlich Konten. */
class SucheController extends Controller
{
    public function __invoke(Request $request): JsonResponse|RedirectResponse
    {
        // Nur für die Befehlspalette; direkter Aufruf im Browser (Lesezeichen, Verlauf) führt zur Übersicht
        if (! $request->wantsJson()) {
            return redirect()->route('dashboard');
        }

        $user = $request->user();
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2 || $user->hasRole('Lernender') && ! $user->hasRole('Admin') && ! $user->hasRole('Berufsbildner')) {
            return response()->json([]);
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
}
