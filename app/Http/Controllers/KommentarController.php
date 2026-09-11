<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Lernender;
use App\Models\Note;
use App\Services\Notifications\Empfaenger;
use App\Services\Notifications\Messages\CommentAdded;
use App\Services\Notifications\NotificationCatalog;
use App\Services\Notifications\Notifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KommentarController extends Controller
{
    public function store(Request $request, int $note_id)
    {
        $user = $request->user();
        $note = Note::with(['fach', 'modulBelegung.modul', 'lernender.benutzer'])->findOrFail($note_id);

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

        $this->benachrichtigen($note, $validated['kommentar_text'], $user);

        return back()
            ->with('success', __('Kommentar gespeichert.'))
            ->with('opened_note', $note_id);
    }

    /** Autor Lernender → aktive Betreuer, Autor BB/Admin → der Lernende. Nie an den Autor selbst. */
    private function benachrichtigen(Note $note, string $text, \App\Models\User $autor): void
    {
        if ($autor->lernender) {
            $zielUrl = route('trainer.learners.show', $note->lernender_id);
            foreach (Empfaenger::aktiveBetreuer((int) $note->lernender_id) as $betreuer) {
                Notifier::send($betreuer, NotificationCatalog::COMMENT_ADDED, fn () => CommentAdded::content(
                    $note, $text, $autor, $zielUrl
                ));
            }
        } elseif ($note->lernender?->benutzer) {
            Notifier::send($note->lernender->benutzer, NotificationCatalog::COMMENT_ADDED, fn () => CommentAdded::content(
                $note, $text, $autor, route('learner.grades.index', ['_open' => $note->note_id])
            ));
        }
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

        return back()->with('success', __('Kommentar gelöscht.'));
    }
}
