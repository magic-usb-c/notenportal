<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Einzige Definition der Navigation je Rolle – Seitenleiste, Tableiste und Befehlspalette lesen von hier.
 */
final class Navigation
{
    /**
     * Rolle des Eintrags (Tastenkürzel «g d», «g n» … suchen danach) => Symbol aus App\Support\Symbole.
     * Symbole im Akzent zeigen in der Seitenleiste, wo man ist (HIG «Sidebars»).
     */
    private const array SYMBOLE = [
        'start' => 'home',
        'noten' => 'clipboard-document-check',
        'kalender' => 'calendar-days',
        'dokument' => 'document-text',
        'rechner' => 'calculator',
        'personen' => 'users',
        'daten' => 'rectangle-stack',
        'bericht' => 'chart-bar',
        'feedback' => 'chat-bubble-left-ellipsis',
        'abschluss' => 'academic-cap',
        'stammdaten' => 'circle-stack',
        'betrieb' => 'building-office-2',
    ];

    /** Gibt es für diesen Lernenden einen aktiven Notenbaum (Lehrberuf oder laufender Bildungsgang)? */
    private static function hatAbschluss(User $user): bool
    {
        $l = $user->lernender;
        if (! $l) {
            return false;
        }
        $heute = now()->toDateString();

        return DB::table('notenbaeume')->where('aktiv', true)->where(fn ($q) => $q
            ->where(fn ($q) => $q->where('bezug', 'lehrberuf')->where('lehrberuf_id', $l->lehrberuf_id))
            ->orWhere(fn ($q) => $q->where('bezug', 'bildungsgang')->whereIn('track_typ', DB::table('lernender_tracks')
                ->where('lernender_id', $l->lernender_id)->where('start_datum', '<=', $heute)
                ->where(fn ($q) => $q->whereNull('end_datum')->orWhere('end_datum', '>=', $heute))->select('track_typ'))))
            ->exists();
    }

    /**
     * @return list<array{label: string, url: string, aktiv: bool, icon: string, symbol: string, badge?: int, mehr?: bool, kinder?: list<array{label: string, url: string, aktiv: bool, icon: string, symbol: string}>}>
     */
    public static function fuer(?User $user, int $feedbackOffen = 0): array
    {
        if (! $user) {
            return [];
        }

        $eintraege = match (true) {
            $user->hasRole('Admin') => [
                self::link(__('Übersicht'), 'admin.dashboard', ['admin.dashboard'], 'start'),
                self::link(__('Lernende'), 'admin.learners.index', ['admin.learners.*', 'admin.grades.*'], 'personen'),
                self::link(__('Prüfungstermine'), 'admin.exams.index', ['admin.exams.*'], 'kalender'),
                self::gruppe(__('Personen'), 'personen', [
                    self::link(__('Berufsbildner'), 'admin.trainers.index', ['admin.trainers.*'], symbol: 'identification'),
                    self::link(__('Benutzerkonten'), 'admin.users.index', ['admin.users.*'], symbol: 'user-circle'),
                ]),
                self::gruppe(__('Stammdaten'), 'stammdaten', [
                    self::link(__('Lehrberufe'), 'admin.master-data.professions.index', ['admin.master-data.professions.*'], symbol: 'briefcase'),
                    self::link(__('Module'), 'admin.master-data.modules.index', ['admin.master-data.modules.*'], symbol: 'rectangle-stack'),
                    self::link(__('Fächer'), 'admin.master-data.subjects.index', ['admin.master-data.subjects.*'], symbol: 'book-open'),
                    self::link(__('Kategorien'), 'admin.master-data.categories.index', ['admin.master-data.categories.*'], symbol: 'tag'),
                    self::link(__('Notenbäume'), 'admin.master-data.grade-trees.index', ['admin.master-data.grade-trees.*'], symbol: 'queue-list'),
                    self::link(__('Semester'), 'admin.master-data.semesters.index', ['admin.master-data.semesters.*'], symbol: 'calendar'),
                ]),
                // Betrieb und Protokolle getrennt von den Stammdaten: andere Aufgabe, anderer Rhythmus
                self::gruppe(__('Betrieb'), 'betrieb', [
                    self::link(__('Einstellungen'), 'admin.operations.edit', ['admin.operations.*'], symbol: 'cog-6-tooth'),
                    self::link(__('Einrichtung'), 'admin.setup', ['admin.setup*'], symbol: 'wrench-screwdriver'),
                    self::link(__('Benachrichtigungen'), 'admin.notifications.index', ['admin.notifications.*'], symbol: 'bell'),
                    self::link(__('Versandprotokoll'), 'admin.mail-log.index', ['admin.mail-log.*'], symbol: 'envelope'),
                    self::link(__('Aktivitätsprotokoll'), 'admin.activity.index', ['admin.activity.*'], symbol: 'clock'),
                ]),
                // «mehr»: in der Leiste oben unter 1536 px im Menü «Mehr» (HIG: Überlauf statt Umbruch)
                self::link(__('Berichte'), 'admin.reports.grades', ['admin.reports.*'], 'bericht') + ['mehr' => true],
                self::link(__('Feedback'), 'admin.feedback.index', ['admin.feedback.*', 'feedback.*'], 'feedback') + ['badge' => $feedbackOffen, 'mehr' => true],
            ],
            $user->hasRole('Berufsbildner') => [
                self::link(__('Übersicht'), 'trainer.dashboard', ['trainer.dashboard'], 'start'),
                self::link(__('Lernende'), 'trainer.learners.index', ['trainer.learners.*', 'trainer.grades.*'], 'personen'),
                self::link(__('Prüfungstermine'), 'trainer.exams.index', ['trainer.exams.*'], 'kalender'),
                self::link(__('Module'), 'modules.index', ['modules.*'], 'daten'),
            ],
            $user->hasRole('Lernender') => [
                self::link(__('Übersicht'), 'learner.dashboard', ['learner.dashboard'], 'start'),
                self::link(__('Noten'), 'learner.grades.index', ['learner.grades.index', 'learner.grades.create', 'learner.grades.edit', 'learner.grades.print', 'learner.grades.import.*'], 'noten'),
                self::link(__('Agenda'), 'learner.exams.index', ['learner.exams.*'], 'kalender'),
                self::link(__('Rechner'), 'learner.grades.calculator', ['learner.grades.calculator'], 'rechner'),
                ...(self::hatAbschluss($user) ? [self::link(__('Abschluss'), 'learner.qualification.index', ['learner.qualification.*'], 'abschluss')] : []),
                // Module sind gemeinsame Stammdaten – deshalb steht der Eintrag bei allen Rollen, nicht nur beim Admin.
                self::link(__('Module'), 'modules.index', ['modules.*'], 'daten'),
                self::link(__('Dokumente'), 'learner.documents.index', ['learner.documents.*'], 'dokument'),
            ],
            default => [],
        };

        return $eintraege;
    }

    /**
     * Zusätzliche Befehle für die Palette (Aktionen, nicht Seiten).
     *
     * @return list<array{label: string, url: string, gruppe: string}>
     */
    public static function befehle(User $user): array
    {
        $befehle = [];
        if ($user->hasRole('Lernender')) {
            $befehle[] = ['label' => __('Note erfassen'), 'url' => route('learner.grades.create'), 'gruppe' => __('Aktion')];
            $befehle[] = ['label' => __('Prüfung planen'), 'url' => route('learner.exams.index').'?planen=1', 'gruppe' => __('Aktion')];
            $befehle[] = ['label' => __('Notenblatt drucken'), 'url' => route('learner.grades.print'), 'gruppe' => __('Aktion')];
        }
        if ($user->hasRole('Admin') || $user->hasRole('Berufsbildner')) {
            $bereich = $user->hasRole('Admin') ? 'admin' : 'trainer';
            $befehle[] = ['label' => __('Lernende erfassen'), 'url' => route($bereich.'.learners.create'), 'gruppe' => __('Aktion')];
            $befehle[] = ['label' => __('Alle Noten exportieren'), 'url' => route($bereich.'.grades.export_all'), 'gruppe' => __('Aktion')];
        }
        if ($user->hasRole('Admin')) {
            $befehle[] = ['label' => __('Benutzerkonto anlegen'), 'url' => route('admin.users.create'), 'gruppe' => __('Aktion')];
            $befehle[] = ['label' => __('Semester anlegen'), 'url' => route('admin.master-data.semesters.create'), 'gruppe' => __('Aktion')];
        }
        $befehle[] = ['label' => __('Profil'), 'url' => route('settings.profile'), 'gruppe' => __('Konto')];
        $befehle[] = ['label' => __('Benachrichtigungen'), 'url' => route('notifications.settings'), 'gruppe' => __('Konto')];
        $befehle[] = ['label' => __('Feedback melden'), 'url' => '#feedback-modal', 'gruppe' => __('Konto')];
        $befehle[] = ['label' => __('Meine Meldungen'), 'url' => route('feedback.index'), 'gruppe' => __('Konto')];
        $befehle[] = ['label' => __('Meine Daten herunterladen'), 'url' => route('profile.data-export'), 'gruppe' => __('Konto')];
        if (Darstellung::fuer($user)['tastenkuerzel'] === Darstellung::TASTENKUERZEL_AN) {
            $befehle[] = ['label' => __('Tastenkürzel anzeigen'), 'url' => '#tastenkuerzel-modal', 'gruppe' => __('Konto')];
        }

        foreach (self::darstellungsBefehle() as $befehl) {
            $befehle[] = $befehl;
        }

        return $befehle;
    }

    /**
     * Schnellwechsel für die Befehlspalette (Darstellung, Farbthema, Schrift, Dichte). Ausgeführt
     * clientseitig (resources/js/suche.js liest die Ziel-URL '#art:wert'), gespeichert über
     * PATCH /profile/preferences bzw. /profile/appearance.
     *
     * @return list<array{label: string, url: string, gruppe: string}>
     */
    private static function darstellungsBefehle(): array
    {
        $gruppe = __('Darstellung');
        $befehle = [
            ['label' => __('Darstellung: Hell'), 'url' => '#darstellung:hell', 'gruppe' => $gruppe],
            ['label' => __('Darstellung: Dunkel'), 'url' => '#darstellung:dunkel', 'gruppe' => $gruppe],
            ['label' => __('Darstellung: Wie Gerät'), 'url' => '#darstellung:system', 'gruppe' => $gruppe],
        ];

        if (! Darstellung::praeferenzenOptionVerfuegbar()) {
            return $befehle;
        }

        foreach (Theme::THEMES as $wert => $name) {
            $befehle[] = ['label' => __('Theme: :name', ['name' => $name]), 'url' => '#theme:'.$wert, 'gruppe' => $gruppe];
        }

        $befehle[] = ['label' => __('Schrift: Normal'), 'url' => '#schrift:normal', 'gruppe' => $gruppe];
        $befehle[] = ['label' => __('Schrift: Gross'), 'url' => '#schrift:gross', 'gruppe' => $gruppe];
        $befehle[] = ['label' => __('Schrift: Sehr gross'), 'url' => '#schrift:sehr-gross', 'gruppe' => $gruppe];
        $befehle[] = ['label' => __('Navigation: Oben'), 'url' => '#navigation:oben', 'gruppe' => $gruppe];
        $befehle[] = ['label' => __('Navigation: Seitenleiste'), 'url' => '#navigation:seite', 'gruppe' => $gruppe];
        $befehle[] = ['label' => __('Dichte: Normal'), 'url' => '#dichte:normal', 'gruppe' => $gruppe];
        $befehle[] = ['label' => __('Dichte: Kompakt'), 'url' => '#dichte:kompakt', 'gruppe' => $gruppe];
        $befehle[] = ['label' => __('Diagrammfarben: Standard'), 'url' => '#diagramm:standard', 'gruppe' => $gruppe];
        $befehle[] = ['label' => __('Diagrammfarben: Farbenblind'), 'url' => '#diagramm:farbenblind', 'gruppe' => $gruppe];

        return $befehle;
    }

    private static function link(string $label, string $route, array $muster, string $icon = 'start', ?string $symbol = null): array
    {
        return ['label' => $label, 'url' => route($route), 'aktiv' => request()->routeIs(...$muster), 'icon' => $icon, 'symbol' => $symbol ?? self::SYMBOLE[$icon]];
    }

    private static function gruppe(string $label, string $icon, array $kinder): array
    {
        return ['label' => $label, 'url' => $kinder[0]['url'], 'aktiv' => collect($kinder)->contains('aktiv', true), 'icon' => $icon, 'symbol' => self::SYMBOLE[$icon], 'kinder' => $kinder];
    }
}
