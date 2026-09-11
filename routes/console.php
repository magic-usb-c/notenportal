<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Tägliche Sicherung (Datenbank + Dokumente); der Installer richtet dafür /etc/cron.d/<instanz> ein.
Schedule::command('notenportal:sicherung')->dailyAt('02:30')->withoutOverlapping();

// Mail-Queue: Worker startet jede Minute über den Scheduler und beendet sich, wenn die Queue leer ist (kein Systemdienst nötig).
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')->everyMinute()->withoutOverlapping()->runInBackground();

// Tageszusammenfassung der Benachrichtigungen
Schedule::command('notifications:digest')->dailyAt('18:00')->withoutOverlapping();

// Tägliche Prüfung: Prüfungserinnerung, Inaktivität, Semesterende/-abschluss, Lernstand kritisch
Schedule::command('notifications:check')->dailyAt('06:30')->withoutOverlapping();

// Abonnierte Kalender (Schulnetz-iCal) stündlich abgleichen
Schedule::command('calendar:sync')->hourlyAt(17)->withoutOverlapping()->runInBackground();
