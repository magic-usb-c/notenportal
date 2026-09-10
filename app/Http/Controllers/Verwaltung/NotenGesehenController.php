<?php

declare(strict_types=1);

namespace App\Http\Controllers\Verwaltung;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Noten als gesehen markieren (einzeln oder alle im aktiven Filter). */
class NotenGesehenController extends VerwaltungController
{
    public function einzeln(Request $request, int $lernender_id, int $note_id): RedirectResponse
    {
        $note = $this->sichtbarerLernender($request, $lernender_id)->noten()->whereKey($note_id)->firstOrFail();

        // Auch bei bestehender Markierung neu setzen, damit der «Neu»-Badge nach neuen Kommentaren verschwindet
        $this->markieren([$note->note_id], (int) $request->user()->benutzer_id);

        return back()
            ->with('success', 'Note als gesehen markiert.')
            ->with('opened_note', $note->note_id);
    }

    public function alle(Request $request, int $lernender_id): RedirectResponse
    {
        $this->sichtbarerLernender($request, $lernender_id);

        $noteIds = self::gefilterteNoten($request, $lernender_id)->pluck('n.note_id')->all();
        $this->markieren($noteIds, (int) $request->user()->benutzer_id);

        $anzahl = count($noteIds);

        return back()->with('success', $anzahl === 1 ? '1 Note als gesehen markiert.' : $anzahl.' Noten als gesehen markiert.');
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
