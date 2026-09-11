<?php

/*
 * Betriebsnahe Schalter des Notenportals (keine betriebsspezifischen Werte – die liegen in der Tabelle einstellungen).
 */
return [

    // Datenbank-Benutzer mit DDL-Rechten nur für Migrationen (php artisan notenportal:migrate).
    // Der Web-Benutzer (DB_USERNAME) hat im Betrieb nur Datenrechte (SELECT/INSERT/UPDATE/DELETE).
    'db_migrate' => [
        'username' => env('DB_MIGRATE_USERNAME'),
        'password' => env('DB_MIGRATE_PASSWORD'),
    ],

    // Kalender-Abgleich: Abruf interner Adressen (SSRF-Schutz). Nur für Tests oder ein Schulnetz im eigenen Netz.
    'calendar_allow_private' => (bool) env('CALENDAR_ALLOW_PRIVATE', false),

];
