<?php

declare(strict_types=1);

namespace App\Support;

use Throwable;

/**
 * Kurzer Git-Commit-Hash für die technischen Angaben im Feedback (Block G): reine Dateizugriffe auf
 * .git/ (kein Shell-Aufruf pro Request). Null, wenn kein Git-Verzeichnis vorhanden ist (z. B. ein
 * Deployment ohne .git) oder die Refs gepackt und nicht auffindbar sind.
 */
final class AppVersion
{
    public static function commit(): ?string
    {
        try {
            return self::kurzerHash();
        } catch (Throwable) {
            return null;
        }
    }

    private static function kurzerHash(): ?string
    {
        $kopf = @file_get_contents(base_path('.git/HEAD'));
        if ($kopf === false) {
            return null;
        }
        $kopf = trim($kopf);

        if (! str_starts_with($kopf, 'ref: ')) {
            // Detached HEAD: HEAD enthält den Hash direkt.
            return self::kuerzen($kopf);
        }

        $ref = substr($kopf, 5);
        $hash = @file_get_contents(base_path('.git/'.$ref));
        if ($hash !== false) {
            return self::kuerzen(trim($hash));
        }

        // Nach einem Git-Housekeeping (gc) liegen Refs gepackt statt als Einzeldatei vor.
        $gepackt = @file_get_contents(base_path('.git/packed-refs'));
        if ($gepackt === false) {
            return null;
        }
        foreach (explode("\n", $gepackt) as $zeile) {
            if (str_ends_with($zeile, ' '.$ref)) {
                return self::kuerzen(explode(' ', $zeile)[0]);
            }
        }

        return null;
    }

    private static function kuerzen(string $hash): ?string
    {
        $hash = trim($hash);

        return preg_match('/^[0-9a-f]{7,40}$/', $hash) === 1 ? substr($hash, 0, 7) : null;
    }
}
