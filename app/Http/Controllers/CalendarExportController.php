<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Calendar\CalendarExport;
use Illuminate\Http\Response;

/**
 * Öffentlicher iCal-Abo-Link (Token statt Login, wie Google/Outlook-Kalenderabos üblich).
 * Liefert eigene Prüfungen und Termine des Lernenden bzw. die Prüfungen der sichtbaren Lernenden für BB/Admin.
 */
class CalendarExportController extends Controller
{
    public function __invoke(string $token, CalendarExport $export): Response
    {
        $user = User::where('kalender_token', $token)->firstOrFail();

        return response($export->forUser($user), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="notenportal.ics"',
            'Cache-Control' => 'private, max-age=900',
        ]);
    }
}
