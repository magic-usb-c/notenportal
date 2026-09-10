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
                self::link('Übersicht', 'admin.dashboard', ['admin.dashboard'], 'start'),
                self::link('Lernende', 'admin.lernende.index', ['admin.lernende.*', 'admin.noten.*'], 'personen'),
                self::gruppe('Personen', 'personen', [
                    self::link('Berufsbildner', 'admin.berufsbildner.index', ['admin.berufsbildner.*']),
                    self::link('Benutzerkonten', 'admin.benutzer.index', ['admin.benutzer.*']),
                ]),
                self::gruppe('Stammdaten', 'daten', [
                    self::link('Betrieb', 'admin.betrieb.edit', ['admin.betrieb.*']),
                    self::link('Einrichtung', 'admin.einrichtung', ['admin.einrichtung*']),
                    self::link('Lehrberufe', 'admin.stammdaten.lehrberufe.index', ['admin.stammdaten.lehrberufe.*']),
                    self::link('Module', 'admin.stammdaten.module.index', ['admin.stammdaten.module.*']),
                    self::link('Fächer', 'admin.stammdaten.faecher.index', ['admin.stammdaten.faecher.*']),
                    self::link('Kategorien', 'admin.stammdaten.kategorien.index', ['admin.stammdaten.kategorien.*']),
                    self::link('Semester', 'admin.stammdaten.semester.index', ['admin.stammdaten.semester.*']),
                ]),
                self::link('Berichte', 'admin.berichte.noten', ['admin.berichte.*'], 'bericht'),
                self::link('Feedback', 'admin.feedback.index', ['admin.feedback.*'], 'feedback') + ['badge' => $feedbackOffen],
            ],
            $user->hasRole('Berufsbildner') => [
                self::link('Übersicht', 'berufsbildner.dashboard', ['berufsbildner.dashboard'], 'start'),
                self::link('Lernende', 'berufsbildner.lernende.index', ['berufsbildner.lernende.*', 'berufsbildner.noten.*'], 'personen'),
            ],
            $user->hasRole('Lernender') => [
                self::link('Übersicht', 'lernender.dashboard', ['lernender.dashboard'], 'start'),
                self::link('Noten', 'lernender.noten.index', ['lernender.noten.index', 'lernender.noten.create', 'lernender.noten.edit', 'lernender.noten.drucken'], 'noten'),
                self::link('Prüfungen', 'lernender.pruefungen.index', ['lernender.pruefungen.*'], 'kalender'),
                self::link('Rechner', 'lernender.noten.rechner', ['lernender.noten.rechner'], 'rechner'),
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
            $befehle[] = ['label' => 'Note erfassen', 'url' => route('lernender.noten.create'), 'gruppe' => 'Aktion'];
            $befehle[] = ['label' => 'Prüfung planen', 'url' => route('lernender.pruefungen.index').'#planen', 'gruppe' => 'Aktion'];
            $befehle[] = ['label' => 'Notenblatt drucken', 'url' => route('lernender.noten.drucken'), 'gruppe' => 'Aktion'];
        }
        if ($user->hasRole('Admin') || $user->hasRole('Berufsbildner')) {
            $bereich = $user->hasRole('Admin') ? 'admin' : 'berufsbildner';
            $befehle[] = ['label' => 'Lernende erfassen', 'url' => route($bereich.'.lernende.create'), 'gruppe' => 'Aktion'];
            $befehle[] = ['label' => 'Alle Noten exportieren', 'url' => route($bereich.'.noten.export_alle'), 'gruppe' => 'Aktion'];
        }
        if ($user->hasRole('Admin')) {
            $befehle[] = ['label' => 'Benutzerkonto anlegen', 'url' => route('admin.benutzer.create'), 'gruppe' => 'Aktion'];
            $befehle[] = ['label' => 'Semester anlegen', 'url' => route('admin.stammdaten.semester.create'), 'gruppe' => 'Aktion'];
        }
        $befehle[] = ['label' => 'Profil', 'url' => route('profile.edit'), 'gruppe' => 'Konto'];
        $befehle[] = ['label' => 'Meine Meldungen', 'url' => route('feedback.index'), 'gruppe' => 'Konto'];

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
