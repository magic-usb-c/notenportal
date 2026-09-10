<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Tägliche Sicherung (Datenbank + Dokumente); der Installer richtet dafür /etc/cron.d/<instanz> ein.
Schedule::command('notenportal:sicherung')->dailyAt('02:30')->withoutOverlapping();
