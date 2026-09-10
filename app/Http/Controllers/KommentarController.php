<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Lernender;
use App\Models\Note;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KommentarController extends Controller
{
    public function store(Request $request, int $note_id)
    {
        $user = $request->user();
        $note = Note::findOrFail($note_id);

        if ($user->lernender) {
            // Lernender darf nur eigene Noten kommentieren
            abort_if((int) $note->lernender_id !== (int) $user->lernender->lernender_id, 403);
        } else {
            // Admin/BB: nur Noten sichtbarer Lernender (fremde: 404, keine Existenz verraten)
            abort_unless(Lernender::sichtbarFuer($user)->whereKey($note->lernender_id)->exists(), 404);
        }

        $validated = $request->validate([
            'kommentar_text' => ['required', 'string', 'max:2000'],
        ]);

        DB::table('noten_kommentare')->insert([
            'note_id' => $note_id,
            'autor_benutzer_id' => (int) $user->benutzer_id,
            'kommentar_text' => $validated['kommentar_text'],
            'erstellt_am' => now(),
        ]);

        return back()
            ->with('status', 'Kommentar gespeichert.')
            ->with('opened_note', $note_id);
    }

    public function destroy(Request $request, int $kommentar_id)
    {
        $kommentar = DB::table('noten_kommentare')->where('kommentar_id', $kommentar_id)->first();
        abort_if(! $kommentar, 404);

        $user = $request->user();

        // Eigener Kommentar oder Admin darf löschen
        $isOwn = (int) $kommentar->autor_benutzer_id === (int) $user->benutzer_id;
        $isAdmin = $user->hasRole('Admin');
        abort_if(! $isOwn && ! $isAdmin, 403);

        DB::table('noten_kommentare')->where('kommentar_id', $kommentar_id)->delete();

        return back()->with('status', 'Kommentar gelöscht.');
    }
}
