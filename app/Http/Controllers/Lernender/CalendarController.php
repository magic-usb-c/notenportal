<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lernender;

use App\Http\Controllers\Controller;
use App\Models\CalendarFeed;
use App\Services\Calendar\CalendarExport;
use App\Services\Calendar\CalendarSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Kalender-Abo des Lernenden: iCal-Adresse hinterlegen (Prüfungen/Termine/Lektionen wahlweise
 * übernehmen), von Hand abgleichen, eigenen Export-Link neu erzeugen.
 */
class CalendarController extends Controller
{
    public function __construct(private readonly CalendarSync $sync) {}

    public function feedStore(Request $request): RedirectResponse
    {
        $lernender = $request->user()->lernender ?? abort(403);
        $daten = $request->validate([
            'label' => ['nullable', 'string', 'max:80'],
            'url' => ['required', 'string', 'max:2000'],
        ], [], ['url' => 'iCal-Adresse']);

        $url = CalendarSync::normalizeUrl($daten['url']);
        try {
            CalendarSync::assertPublicUrl($url);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['url' => $e->getMessage()]);
        }

        $feed = $lernender->calendarFeeds()->first() ?? new CalendarFeed(['lernender_id' => $lernender->lernender_id]);
        $feed->fill([
            'label' => $daten['label'] ?? null,
            'url' => $url,
            'import_lessons' => $request->boolean('import_lessons'),
            'import_appointments' => $request->boolean('import_appointments'),
            'import_exams' => $request->boolean('import_exams'),
        ])->save();

        return redirect()->route('learner.exams.index')->with('success', 'Kalender gespeichert.');
    }

    public function sync(Request $request): RedirectResponse
    {
        $lernender = $request->user()->lernender ?? abort(403);
        $feed = $lernender->calendarFeeds()->first();
        if (! $feed) {
            return redirect()->route('learner.exams.index')->with('error', 'Kein Kalender hinterlegt.');
        }

        try {
            $stats = $this->sync->sync($feed);

            return redirect()->route('learner.exams.index')
                ->with('success', $stats['events'].' Termine abgeglichen, '.$stats['exams'].' Prüfungen erkannt.');
        } catch (Throwable $e) {
            return redirect()->route('learner.exams.index')->with('error', 'Abgleich fehlgeschlagen: '.$e->getMessage());
        }
    }

    public function tokenReset(Request $request): RedirectResponse
    {
        CalendarExport::resetToken($request->user());

        return redirect()->route('learner.exams.index')->with('success', 'Neuer Abo-Link erzeugt.');
    }
}
