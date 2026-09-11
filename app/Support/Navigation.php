<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

/**
 * Einzige Definition der Navigation je Rolle – Menü, Mobilmenü und Befehlspalette lesen von hier.
 */
final class Navigation
{
    private const array ICONS = [
        'start' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
        'noten' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
        'kalender' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
        'dokument' => 'M7 21h10a2 2 0 002-2V9.4a1 1 0 00-.3-.7l-5.4-5.4a1 1 0 00-.7-.3H7a2 2 0 00-2 2v14a2 2 0 002 2zm5-17v5a1 1 0 001 1h5',
        'rechner' => 'M9 7h6m-6 4h6m-6 4h4m5 4H5a2 2 0 01-2-2V5a2 2 0 012-2h10l4 4v11a2 2 0 01-2 2z',
        'personen' => 'M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-5.13a4 4 0 11-8 0 4 4 0 018 0zm6 3a3 3 0 11-6 0 3 3 0 016 0z',
        'daten' => 'M4 7v10c0 2 3.6 3 8 3s8-1 8-3V7M4 7c0 2 3.6 3 8 3s8-1 8-3M4 7c0-2 3.6-3 8-3s8 1 8 3m0 5c0 2-3.6 3-8 3s-8-1-8-3',
        'bericht' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
        'feedback' => 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.4-4 8-9 8a9.9 9.9 0 01-4.3-.9L3 20l1.4-3.7A7.7 7.7 0 013 12c0-4.4 4-8 9-8s9 3.6 9 8z',
        'plus' => 'M12 4v16m8-8H4',
        'profil' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
        'einstellungen' => 'M10.3 4.3c.4-1.8 3-1.8 3.4 0a1.7 1.7 0 002.6 1.1c1.5-.9 3.3.8 2.4 2.4a1.7 1.7 0 001 2.5c1.8.4 1.8 3 0 3.4a1.7 1.7 0 00-1 2.6c.9 1.5-.9 3.3-2.4 2.4a1.7 1.7 0 00-2.6 1c-.4 1.8-3 1.8-3.4 0a1.7 1.7 0 00-2.6-1c-1.5.9-3.3-.9-2.4-2.4a1.7 1.7 0 00-1-2.6c-1.8-.4-1.8-3 0-3.4a1.7 1.7 0 001-2.5c-.9-1.6.9-3.3 2.4-2.4 1 .6 2.3.1 2.6-1.1zM15 12a3 3 0 11-6 0 3 3 0 016 0z',
    ];

    /**
     * @return list<array{label: string, url: string, aktiv: bool, icon: string, badge?: int, kinder?: list<array{label: string, url: string, aktiv: bool}>}>
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
                    self::link(__('Berufsbildner'), 'admin.trainers.index', ['admin.trainers.*']),
                    self::link(__('Benutzerkonten'), 'admin.users.index', ['admin.users.*']),
                ]),
                self::gruppe(__('Stammdaten'), 'daten', [
                    self::link(__('Betrieb'), 'admin.operations.edit', ['admin.operations.*']),
                    self::link(__('Benachrichtigungen'), 'admin.notifications.index', ['admin.notifications.*']),
                    self::link(__('Versandprotokoll'), 'admin.mail-log.index', ['admin.mail-log.*']),
                    self::link(__('Aktivitätsprotokoll'), 'admin.activity.index', ['admin.activity.*']),
                    self::link(__('Einrichtung'), 'admin.setup', ['admin.setup*']),
                    self::link(__('Lehrberufe'), 'admin.master-data.professions.index', ['admin.master-data.professions.*']),
                    self::link(__('Module'), 'admin.master-data.modules.index', ['admin.master-data.modules.*']),
                    self::link(__('Fächer'), 'admin.master-data.subjects.index', ['admin.master-data.subjects.*']),
                    self::link(__('Kategorien'), 'admin.master-data.categories.index', ['admin.master-data.categories.*']),
                    self::link(__('Semester'), 'admin.master-data.semesters.index', ['admin.master-data.semesters.*']),
                ]),
                self::link(__('Berichte'), 'admin.reports.grades', ['admin.reports.*'], 'bericht'),
                self::link(__('Feedback'), 'admin.feedback.index', ['admin.feedback.*'], 'feedback') + ['badge' => $feedbackOffen],
            ],
            $user->hasRole('Berufsbildner') => [
                self::link(__('Übersicht'), 'trainer.dashboard', ['trainer.dashboard'], 'start'),
                self::link(__('Lernende'), 'trainer.learners.index', ['trainer.learners.*', 'trainer.grades.*'], 'personen'),
                self::link(__('Prüfungstermine'), 'trainer.exams.index', ['trainer.exams.*'], 'kalender'),
            ],
            $user->hasRole('Lernender') => [
                self::link(__('Übersicht'), 'learner.dashboard', ['learner.dashboard'], 'start'),
                self::link(__('Noten'), 'learner.grades.index', ['learner.grades.index', 'learner.grades.create', 'learner.grades.edit', 'learner.grades.print', 'learner.grades.import.*'], 'noten'),
                self::link(__('Agenda'), 'learner.exams.index', ['learner.exams.*'], 'kalender'),
                self::link(__('Rechner'), 'learner.grades.calculator', ['learner.grades.calculator'], 'rechner'),
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
        $befehle[] = ['label' => __('Profil'), 'url' => route('profile.edit'), 'gruppe' => __('Konto')];
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
        $befehle[] = ['label' => __('Dichte: Normal'), 'url' => '#dichte:normal', 'gruppe' => $gruppe];
        $befehle[] = ['label' => __('Dichte: Kompakt'), 'url' => '#dichte:kompakt', 'gruppe' => $gruppe];

        return $befehle;
    }

    public static function icon(string $name): string
    {
        return self::ICONS[$name] ?? self::ICONS['start'];
    }

    private static function link(string $label, string $route, array $muster, string $icon = 'start'): array
    {
        return ['label' => $label, 'url' => route($route), 'aktiv' => request()->routeIs(...$muster), 'icon' => $icon];
    }

    private static function gruppe(string $label, string $icon, array $kinder): array
    {
        return ['label' => $label, 'url' => $kinder[0]['url'], 'aktiv' => collect($kinder)->contains('aktiv', true), 'icon' => $icon, 'kinder' => $kinder];
    }
}
