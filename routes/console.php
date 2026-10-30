<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Tägliche Sicherung (Datenbank + Dokumente) samt Kopie ausser Haus; der Installer richtet dafür /etc/cron.d/<instanz> ein.
// Bewusst nicht zwischen 02:00 und 03:00: die Stunde 02:xx kommt bei der Umstellung auf Winterzeit doppelt vor.
Schedule::command('notenportal:sicherung')->dailyAt('04:10')->withoutOverlapping();

// Mail-Queue: Worker startet jede Minute über den Scheduler und beendet sich, wenn die Queue leer ist (kein Systemdienst nötig).
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')->everyMinute()->withoutOverlapping()->runInBackground();

// Tageszusammenfassung der Benachrichtigungen
Schedule::command('notifications:digest')->dailyAt('18:00')->withoutOverlapping();

// Tägliche Prüfung: Prüfungserinnerung, Inaktivität, Semesterende/-abschluss, Lernstand kritisch
Schedule::command('notifications:check')->dailyAt('06:30')->withoutOverlapping();

// Abonnierte Kalender (Schulnetz-iCal) stündlich abgleichen
Schedule::command('calendar:sync')->hourlyAt(17)->withoutOverlapping()->runInBackground();

// Fehlgeschlagene Jobs nach 7 Tagen löschen: Bereitschaft und Admin-Übersicht zählen alle Einträge in failed_jobs
// und blieben sonst dauerhaft auf alten Fehlern stehen; das Mail-Protokoll zeigt ohnehin nur die letzte Woche.
Schedule::command('queue:prune-failed --hours=168')->dailyAt('03:30')->withoutOverlapping();

// Aktivitätsprotokoll: Einträge älter als 365 Tage löschen
Schedule::command('notenportal:aktivitaeten-aufraeumen')->dailyAt('03:15')->withoutOverlapping();

// Feedback: Screenshots und Anhänge ohne Datenbankzeile löschen (Kaskade, Raw-Delete, abgebrochener Upload)
Schedule::command('notenportal:feedback-aufraeumen')->weeklyOn(1, '03:45')->withoutOverlapping();
