<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\NotificationPolicy;
use App\Models\User;
use InvalidArgumentException;

/**
 * Alle Benachrichtigungsanlässe mit Standardregeln. Der Admin überschreibt je Anlass
 * (notification_policies): aktiv, verpflichtend, Frequenz, Auslöser-Parameter.
 * Was nicht verpflichtend ist, wählt jeder Benutzer selbst (notification_preferences).
 */
final class NotificationCatalog
{
    public const string IMMEDIATE = 'immediate';

    public const string DAILY = 'daily';

    public const string NEVER = 'never';

    public const array FREQUENCIES = [
        self::IMMEDIATE => 'Sofort',
        self::DAILY => 'Tageszusammenfassung',
        self::NEVER => 'Nie',
    ];

    public const string ACCOUNT_CREATED = 'account_created';

    public const string PASSWORD_RESET = 'password_reset';

    public const string PASSWORD_CHANGED = 'password_changed';

    public const string COMMENT_ADDED = 'comment_added';

    public const string GRADE_SEEN = 'grade_seen';

    public const string GRADE_CORRECTED = 'grade_corrected';

    public const string GRADE_ADDED = 'grade_added';

    public const string BELOW_THRESHOLD = 'below_threshold';

    public const string INACTIVITY = 'inactivity';

    public const string EXAM_REMINDER = 'exam_reminder';

    public const string SEMESTER_ENDING = 'semester_ending';

    public const string SEMESTER_CLOSED = 'semester_closed';

    public const string LEARNER_AT_RISK = 'learner_at_risk';

    public const string LEARNER_ASSIGNED = 'learner_assigned';

    public const string FEEDBACK_RECEIVED = 'feedback_received';

    public const string FEEDBACK_ANSWERED = 'feedback_answered';

    public const string BACKUP_FAILED = 'backup_failed';

    /** Systemmails ohne Katalogeintrag in der Benutzerwahl */
    public const string DAILY_DIGEST = 'daily_digest';

    public const string TEST = 'test';

    public const array GROUPS = ['konto' => 'Konto', 'noten' => 'Noten', 'pruefungen' => 'Prüfungen und Semester', 'betreuung' => 'Betreuung', 'betrieb' => 'Betrieb'];

    private const array L = ['Lernender'];

    private const array BB = ['Berufsbildner'];

    private const array A = ['Admin'];

    private const array ALLE = ['Lernender', 'Berufsbildner', 'Admin'];

    /** @var array<string, array{enabled: bool, mandatory: bool, frequency: string, params: array<string, int>}>|null */
    private static ?array $policies = null;

    /**
     * @return array<string, array{label: string, description: string, group: string, roles: list<string>, enabled: bool, mandatory: bool, frequency: string, frequencies: list<string>, locked?: bool, params: array<string, array{label: string, default: int, min: int, max: int}>}>
     */
    public static function all(): array
    {
        $alle = [self::IMMEDIATE, self::DAILY, self::NEVER];
        $sofortOderNie = [self::IMMEDIATE, self::NEVER];

        return [
            self::ACCOUNT_CREATED => [
                'label' => __('Konto eröffnet'), 'description' => __('Link zum Festlegen des Passworts, sobald ein Konto angelegt wird.'),
                'group' => 'konto', 'roles' => self::ALLE, 'enabled' => true, 'mandatory' => true, 'locked' => true,
                'frequency' => self::IMMEDIATE, 'frequencies' => [self::IMMEDIATE], 'params' => [],
            ],
            self::PASSWORD_RESET => [
                'label' => __('Passwort zurücksetzen'), 'description' => __('Link nach «Passwort vergessen» oder wenn ein Admin das Passwort zurücksetzt.'),
                'group' => 'konto', 'roles' => self::ALLE, 'enabled' => true, 'mandatory' => true, 'locked' => true,
                'frequency' => self::IMMEDIATE, 'frequencies' => [self::IMMEDIATE], 'params' => [],
            ],
            self::PASSWORD_CHANGED => [
                'label' => __('Passwort geändert'), 'description' => __('Sicherheitshinweis, wenn das eigene Passwort geändert wurde.'),
                'group' => 'konto', 'roles' => self::ALLE, 'enabled' => true, 'mandatory' => true,
                'frequency' => self::IMMEDIATE, 'frequencies' => $sofortOderNie, 'params' => [],
            ],
            self::COMMENT_ADDED => [
                'label' => __('Neuer Kommentar'), 'description' => __('Jemand hat eine Note kommentiert.'),
                'group' => 'noten', 'roles' => ['Lernender', 'Berufsbildner'], 'enabled' => true, 'mandatory' => false,
                'frequency' => self::IMMEDIATE, 'frequencies' => $alle, 'params' => [],
            ],
            self::GRADE_SEEN => [
                'label' => __('Note angesehen'), 'description' => __('Der Berufsbildner hat eine Note als gesehen markiert.'),
                'group' => 'noten', 'roles' => self::L, 'enabled' => true, 'mandatory' => false,
                'frequency' => self::DAILY, 'frequencies' => $alle, 'params' => [],
            ],
            self::GRADE_CORRECTED => [
                'label' => __('Note korrigiert'), 'description' => __('Berufsbildner oder Admin hat eine eigene Note geändert.'),
                'group' => 'noten', 'roles' => self::L, 'enabled' => true, 'mandatory' => false,
                'frequency' => self::IMMEDIATE, 'frequencies' => $alle, 'params' => [],
            ],
            self::GRADE_ADDED => [
                'label' => __('Neue Note erfasst'), 'description' => __('Ein betreuter Lernender hat eine Note eingetragen.'),
                'group' => 'betreuung', 'roles' => self::BB, 'enabled' => true, 'mandatory' => false,
                'frequency' => self::DAILY, 'frequencies' => $alle, 'params' => [],
            ],
            self::BELOW_THRESHOLD => [
                'label' => __('Schnitt unter Grenzwert'), 'description' => __('Nach einer neuen Note fällt ein Fach-, Modul- oder Semesterschnitt unter die Grenze «genügend».'),
                'group' => 'noten', 'roles' => ['Lernender', 'Berufsbildner'], 'enabled' => true, 'mandatory' => false,
                'frequency' => self::IMMEDIATE, 'frequencies' => $alle, 'params' => [],
            ],
            self::INACTIVITY => [
                'label' => __('Länger keine Note'), 'description' => __('Erinnerung, wenn seit einiger Zeit keine Note mehr eingetragen wurde.'),
                'group' => 'noten', 'roles' => self::L, 'enabled' => true, 'mandatory' => false,
                'frequency' => self::IMMEDIATE, 'frequencies' => $alle,
                'params' => [
                    'days' => ['label' => __('Nach Tagen ohne Note'), 'default' => 30, 'min' => 7, 'max' => 180],
                    'repeat_days' => ['label' => __('Wiederholen alle (Tage)'), 'default' => 14, 'min' => 7, 'max' => 90],
                ],
            ],
            self::EXAM_REMINDER => [
                'label' => __('Prüfung steht bevor'), 'description' => __('Erinnerung mit Prüfungsstoff, Dauer und Hilfsmitteln einige Tage vor der Prüfung.'),
                'group' => 'pruefungen', 'roles' => self::L, 'enabled' => true, 'mandatory' => false,
                'frequency' => self::IMMEDIATE, 'frequencies' => $alle,
                'params' => ['days_before' => ['label' => __('Tage vorher'), 'default' => 3, 'min' => 1, 'max' => 21]],
            ],
            self::SEMESTER_ENDING => [
                'label' => __('Semesterende naht'), 'description' => __('Offene Prüfungen und unvollständige Module kurz vor Semesterende.'),
                'group' => 'pruefungen', 'roles' => self::L, 'enabled' => true, 'mandatory' => false,
                'frequency' => self::IMMEDIATE, 'frequencies' => $alle,
                'params' => ['days_before' => ['label' => __('Tage vor Semesterende'), 'default' => 14, 'min' => 3, 'max' => 60]],
            ],
            self::SEMESTER_CLOSED => [
                'label' => __('Semesterabschluss'), 'description' => __('Zeugnisnoten des abgeschlossenen Semesters und Erinnerung, das Zeugnis hochzuladen.'),
                'group' => 'pruefungen', 'roles' => ['Lernender', 'Berufsbildner'], 'enabled' => true, 'mandatory' => false,
                'frequency' => self::IMMEDIATE, 'frequencies' => $alle, 'params' => [],
            ],
            self::LEARNER_AT_RISK => [
                'label' => __('Lernstand kritisch'), 'description' => __('Die Ampel eines betreuten Lernenden springt auf Rot.'),
                'group' => 'betreuung', 'roles' => self::BB, 'enabled' => true, 'mandatory' => false,
                'frequency' => self::IMMEDIATE, 'frequencies' => $alle, 'params' => [],
            ],
            self::LEARNER_ASSIGNED => [
                'label' => __('Neue Betreuung'), 'description' => __('Ein Lernender wurde zur Betreuung zugewiesen.'),
                'group' => 'betreuung', 'roles' => self::BB, 'enabled' => true, 'mandatory' => false,
                'frequency' => self::IMMEDIATE, 'frequencies' => $alle, 'params' => [],
            ],
            self::FEEDBACK_RECEIVED => [
                'label' => __('Neue Meldung'), 'description' => __('Jemand hat Feedback, eine Idee oder einen Fehler gemeldet.'),
                'group' => 'betrieb', 'roles' => self::A, 'enabled' => true, 'mandatory' => false,
                'frequency' => self::IMMEDIATE, 'frequencies' => $alle, 'params' => [],
            ],
            self::FEEDBACK_ANSWERED => [
                'label' => __('Meldung beantwortet'), 'description' => __('Die eigene Meldung wurde beantwortet oder ihr Status hat sich geändert.'),
                'group' => 'betrieb', 'roles' => self::ALLE, 'enabled' => true, 'mandatory' => false,
                'frequency' => self::IMMEDIATE, 'frequencies' => $alle, 'params' => [],
            ],
            self::BACKUP_FAILED => [
                'label' => __('Sicherung fehlgeschlagen'), 'description' => __('Die tägliche Datensicherung ist fehlgeschlagen.'),
                'group' => 'betrieb', 'roles' => self::A, 'enabled' => true, 'mandatory' => true,
                'frequency' => self::IMMEDIATE, 'frequencies' => $sofortOderNie, 'params' => [],
            ],
        ];
    }

    public static function get(string $type): array
    {
        return self::all()[$type] ?? throw new InvalidArgumentException("Unbekannter Benachrichtigungsanlass: {$type}");
    }

    public static function exists(string $type): bool
    {
        return isset(self::all()[$type]);
    }

    /**
     * Wirksame Regel: Katalog-Standard, überschrieben durch den Admin.
     *
     * @return array{enabled: bool, mandatory: bool, frequency: string, params: array<string, int>}
     */
    public static function policy(string $type): array
    {
        if (in_array($type, [self::DAILY_DIGEST, self::TEST], true)) {
            return ['enabled' => true, 'mandatory' => true, 'frequency' => self::IMMEDIATE, 'params' => []];
        }

        self::$policies ??= NotificationPolicy::all()->keyBy('type')->all();
        $def = self::get($type);
        $row = self::$policies[$type] ?? null;
        $params = [];
        foreach ($def['params'] as $name => $p) {
            $params[$name] = (int) ($row?->params[$name] ?? $p['default']);
        }
        $locked = $def['locked'] ?? false;
        $frequency = $row?->frequency ?? $def['frequency'];

        return [
            'enabled' => $locked ? true : (bool) ($row?->enabled ?? $def['enabled']),
            'mandatory' => $locked ? true : (bool) ($row?->mandatory ?? $def['mandatory']),
            'frequency' => in_array($frequency, $def['frequencies'], true) ? $frequency : $def['frequency'],
            'params' => $params,
        ];
    }

    public static function param(string $type, string $name): int
    {
        return self::policy($type)['params'][$name];
    }

    /** Anlässe, die für den Benutzer (Rollen) relevant und vom Admin aktiviert sind. */
    public static function forUser(User $user): array
    {
        $rollen = $user->rollen()->pluck('name')->all();

        return array_filter(self::all(), fn (array $def, string $type) => array_intersect($def['roles'], $rollen) !== [] && self::policy($type)['enabled'],
            ARRAY_FILTER_USE_BOTH);
    }

    public static function forget(): void
    {
        self::$policies = null;
    }
}
