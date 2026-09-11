<?php

declare(strict_types=1);

namespace App\Http\Controllers\Verwaltung;

use App\Models\Lernender;
use App\Services\Notifications\MailContent;
use App\Services\Notifications\Messages\GradeSeen;
use App\Services\Notifications\NotificationCatalog;
use App\Services\Notifications\Notifier;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Noten als gesehen markieren (einzeln oder alle im aktiven Filter). */
class NotenGesehenController extends VerwaltungController
{
    public function einzeln(Request $request, int $lernender_id, int $note_id): RedirectResponse
    {
        $lernender = $this->sichtbarerLernender($request, $lernender_id);
        $note = $lernender->noten()->with(['fach', 'modulBelegung.modul'])->whereKey($note_id)->firstOrFail();

        // Auch bei bestehender Markierung neu setzen, damit der «Neu»-Badge nach neuen Kommentaren verschwindet
        $this->markieren([$note->note_id], (int) $request->user()->benutzer_id);

        $this->benachrichtigen($lernender, GradeSeen::einzeln($note, route('learner.grades.index', ['_open' => $note->note_id])));

        return back()
            ->with('success', 'Note als gesehen markiert.')
            ->with('opened_note', $note->note_id);
    }

    public function alle(Request $request, int $lernender_id): RedirectResponse
    {
        $lernender = $this->sichtbarerLernender($request, $lernender_id);

        $noteIds = self::gefilterteNoten($request, $lernender_id)->pluck('n.note_id')->all();
        $this->markieren($noteIds, (int) $request->user()->benutzer_id);

        $anzahl = count($noteIds);
        if ($anzahl > 0) {
            $this->benachrichtigen($lernender, GradeSeen::sammel($anzahl, route('learner.grades.index')));
        }

        return back()->with('success', $anzahl === 1 ? '1 Note als gesehen markiert.' : $anzahl.' Noten als gesehen markiert.');
    }

    private function benachrichtigen(Lernender $lernender, MailContent $inhalt): void
    {
        $lernender->loadMissing('benutzer');
        if ($lernender->benutzer) {
            Notifier::send($lernender->benutzer, NotificationCatalog::GRADE_SEEN, $inhalt);
        }
    }

    /** Nicht gelöschte Noten des Lernenden, eingeschränkt auf die Filter Kategorie/Semester der Notenansicht. */
    public static function gefilterteNoten(Request $request, int $lernenderId): Builder
    {
        return DB::table('noten as n')
            ->where('n.lernender_id', $lernenderId)
            ->whereNull('n.geloescht_am')
            ->when($request->filled('kategorie_id'), fn ($q) => $q->where('n.kategorie_id', $request->integer('kategorie_id')))
            ->when($request->filled('semester_id'), fn ($q) => $q->where('n.semester_id', $request->integer('semester_id')));
    }

    /** @param  list<int>  $noteIds */
    private function markieren(array $noteIds, int $viewerId): void
    {
        if ($noteIds === []) {
            return;
        }

        $jetzt = now();

        DB::table('noten_gesehen')->upsert(
            array_map(fn ($id) => ['note_id' => $id, 'viewer_benutzer_id' => $viewerId, 'gesehen_am' => $jetzt], $noteIds),
            ['note_id', 'viewer_benutzer_id'],
            ['gesehen_am'],
        );
    }
}
