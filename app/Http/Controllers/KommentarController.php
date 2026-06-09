<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Note;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KommentarController extends Controller
{
    public function store(Request $request, int $note_id)
    {
        $user = $request->user();
        $note = Note::findOrFail($note_id);

        $lernender   = $user?->lernender;
        $berufsbildner = $user?->berufsbildner;

        if ($lernender) {
            // Lernender darf nur eigene Noten kommentieren
            abort_if((int) $note->lernender_id !== (int) $lernender->lernender_id, 403);

        } elseif ($berufsbildner) {
            // BB darf nur Noten von aktuell betreuten Lernenden kommentieren
            $today = now()->toDateString();
            $betreut = DB::table('betreuungen')
                ->where('berufsbildner_id', $berufsbildner->berufsbildner_id)
                ->where('lernender_id', $note->lernender_id)
                ->where('gueltig_von', '<=', $today)
                ->where(fn($q) => $q->whereNull('gueltig_bis')->orWhere('gueltig_bis', '>=', $today))
                ->exists();
            abort_if(!$betreut, 403);

        } elseif ($user->hasRole('Admin')) {
            // Admin darf alle Noten kommentieren – keine weitere Prüfung nötig

        } else {
            abort(403);
        }

        $validated = $request->validate([
            'kommentar_text' => ['required', 'string', 'max:2000'],
        ]);

        DB::table('noten_kommentare')->insert([
            'note_id'           => $note_id,
            'autor_benutzer_id' => (int) $user->benutzer_id,
            'kommentar_text'    => $validated['kommentar_text'],
            'erstellt_am'       => now(),
        ]);

        return back()->with('status', 'Kommentar gespeichert.');
    }
}
