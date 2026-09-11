<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Old German URL paths (before 11.09.2026) → English paths. Translated segment by segment;
 * the fallback route answers with 301 when the translated path hits an existing GET route.
 */
final class LegacyPaths
{
    /** First segment = role area */
    private const array AREAS = ['berufsbildner' => 'trainer', 'lernender' => 'learner'];

    private const array SEGMENTS = [
        'lernende' => 'learners', 'noten' => 'grades', 'dokumente' => 'documents', 'abgleich' => 'reconcile',
        'uebernehmen' => 'apply', 'konto' => 'account', 'passwort' => 'password', 'aktiv' => 'active',
        'betreuung' => 'supervision', 'betreuungen' => 'supervisions', 'beenden' => 'end', 'rechner' => 'calculator',
        'berechnen' => 'calculate', 'drucken' => 'print', 'alle-gesehen' => 'seen-all', 'gesehen' => 'seen',
        'lesen' => 'read', 'verwerfen' => 'discard', 'vorlage' => 'template', 'benutzer' => 'users',
        'toggle-aktiv' => 'toggle-active', 'berichte' => 'reports', 'berufsbildner' => 'trainers',
        'betrieb' => 'operations', 'sicherungen' => 'backups', 'kopie' => 'offsite', 'einrichtung' => 'setup',
        'abschliessen' => 'finish', 'fertig' => 'finish', 'kategorien' => 'categories', 'semester' => 'semesters',
        'lehrberufe' => 'professions', 'module' => 'modules', 'modul' => 'module', 'personen' => 'people',
        'stammdaten' => 'master-data', 'faecher' => 'subjects', 'pruefungen' => 'exams', 'ziele' => 'goals',
        'kommentare' => 'comments', 'suche' => 'search', 'darstellung' => 'appearance', 'fortsetzen' => 'resume',
        'wiederholen' => 'repeat', 'titel' => 'title', 'passwort-festlegen' => 'set-password',
    ];

    public static function translate(string $path): string
    {
        $segments = explode('/', trim($path, '/'));
        foreach ($segments as $i => $segment) {
            $segments[$i] = ($i === 0 ? self::AREAS[$segment] ?? null : null) ?? self::SEGMENTS[$segment] ?? $segment;
        }

        return '/'.implode('/', $segments);
    }

    /** New URL for an old GET path, or null if nothing matches. */
    public static function redirectTarget(Request $request): ?string
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return null;
        }

        $new = self::translate($request->path());
        if ($new === '/'.trim($request->path(), '/')) {
            return null;
        }

        try {
            $route = Route::getRoutes()->match(Request::create($new, 'GET'));
        } catch (HttpException) {
            return null;
        }
        if ($route->isFallback) {
            return null;
        }

        $query = $request->getQueryString();

        return url($new).($query ? '?'.$query : '');
    }
}
