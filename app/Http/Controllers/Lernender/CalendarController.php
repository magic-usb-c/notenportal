<?php

declare(strict_types=1);

namespace App\Http\Controllers\Lernender;

use App\Http\Controllers\Controller;
use App\Models\CalendarFeed;
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

    /** Höchstens so viele Kalender pro Lernendem (Rückmeldung #15 Phase 1, Entscheidung E). */
    private const int MAX_FEEDS = CalendarFeed::MAX_PRO_LERNENDEM;

    public function feedStore(Request $request): RedirectResponse
    {
        $lernender = $request->user()->lernender ?? abort(403);
        // Beim Bearbeiten wird die Adresse nie im Formular angezeigt (Geheimnis) – ein leeres Feld lässt sie unverändert.
        $neu = ! filled($request->input('feed_id'));
        $daten = $request->validate([
            'feed_id' => ['nullable', 'integer'],
            'label' => ['nullable', 'string', 'max:80'],
            'url' => [$neu ? 'required' : 'nullable', 'string', 'max:2000'],
        ], [], ['url' => __('iCal-Adresse')]);

        if (filled($daten['feed_id'] ?? null)) {
            $feed = $lernender->calendarFeeds()->whereKey($daten['feed_id'])->firstOrFail();
        } else {
            if ($lernender->calendarFeeds()->count() >= self::MAX_FEEDS) {
                throw ValidationException::withMessages(['url' => __('Höchstens :max Kalender. Bitte zuerst einen entfernen.', ['max' => self::MAX_FEEDS])]);
            }
            $feed = new CalendarFeed(['lernender_id' => $lernender->lernender_id]);
        }

        if (filled($daten['url'] ?? null)) {
            $url = CalendarSync::normalizeUrl($daten['url']);
            try {
                CalendarSync::assertPublicUrl($url);
            } catch (RuntimeException $e) {
                throw ValidationException::withMessages(['url' => $e->getMessage()]);
            }

            // Andere Adresse: der alte Stempel gehört zum alten Kalender und darf ihn nicht als unverändert ausgeben.
            if ($feed->exists && $feed->url !== $url) {
                $feed->etag = null;
                $feed->last_modified = null;
            }
            $feed->url = $url;
        }

        $feed->fill([
            'label' => $daten['label'] ?? null,
            'import_lessons' => $request->boolean('import_lessons'),
            'import_appointments' => $request->boolean('import_appointments'),
            'import_exams' => $request->boolean('import_exams'),
        ])->save();

        return redirect()->route('settings.calendar')->with('success', __('Kalender gespeichert.'));
    }

    public function feedDestroy(Request $request, int $id): RedirectResponse
    {
        $lernender = $request->user()->lernender ?? abort(403);
        $lernender->calendarFeeds()->whereKey($id)->firstOrFail()->delete();

        return redirect()->route('settings.calendar')->with('success', __('Kalender entfernt.'));
    }

    public function feedSync(Request $request, int $id): RedirectResponse
    {
        $lernender = $request->user()->lernender ?? abort(403);
        $feed = $lernender->calendarFeeds()->whereKey($id)->firstOrFail();

        try {
            $stats = $this->sync->sync($feed);

            // Bei 304 stammen die Zahlen aus dem letzten echten Lauf. Sie als frisch abgeglichen zu melden,
            // läse sich mit «0 Prüfungen erkannt» wie ein Verlust – es hat sich schlicht nichts geändert.
            return redirect()->route('settings.calendar')->with('success', $stats['unchanged']
                ? __('Kalender unverändert – es gab nichts abzugleichen.')
                : __(':events Termine abgeglichen, :exams Prüfungen erkannt.', ['events' => $stats['events'], 'exams' => $stats['exams']]));
        } catch (Throwable $e) {
            return redirect()->route('settings.calendar')->with('error', __('Abgleich fehlgeschlagen: :fehler', ['fehler' => CalendarSync::fehlertext($e)]));
        }
    }

    /** Gleicht alle Kalender des Lernenden ab (Summen addieren, Fehler je Feed sammeln). */
    public function sync(Request $request): RedirectResponse
    {
        $lernender = $request->user()->lernender ?? abort(403);
        $feeds = $lernender->calendarFeeds()->get();
        if ($feeds->isEmpty()) {
            return redirect()->route('settings.calendar')->with('error', __('Kein Kalender hinterlegt.'));
        }

        $events = 0;
        $exams = 0;
        $unveraendert = 0;
        $fehler = [];
        foreach ($feeds as $feed) {
            try {
                $stats = $this->sync->sync($feed);
                // Ein unveränderter Kalender (304) liefert die Zahlen des letzten Laufs; mitzählen wäre falsch.
                if ($stats['unchanged']) {
                    $unveraendert++;

                    continue;
                }
                $events += $stats['events'];
                $exams += $stats['exams'];
            } catch (Throwable $e) {
                $fehler[] = $feed->host().': '.CalendarSync::fehlertext($e);
            }
        }

        if ($fehler === []) {
            return redirect()->route('settings.calendar')->with('success', $unveraendert === $feeds->count()
                ? __('Alle Kalender unverändert – es gab nichts abzugleichen.')
                : __(':events Termine abgeglichen, :exams Prüfungen erkannt.', ['events' => $events, 'exams' => $exams]));
        }

        return redirect()->route('settings.calendar')->with('error', __(
            ':events Termine abgeglichen, :exams Prüfungen erkannt, Fehler: :fehler',
            ['events' => $events, 'exams' => $exams, 'fehler' => implode('; ', $fehler)]
        ));
    }
}
