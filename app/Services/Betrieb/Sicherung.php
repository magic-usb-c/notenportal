<?php

declare(strict_types=1);

namespace App\Services\Betrieb;

use App\Support\Einstellungen;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Sicherung ohne Terminal: Datenbank (mariadb-dump) und alle Dokumente als ZIP unter
 * storage/app/private/sicherungen/, die neusten BEHALTEN bleiben. Täglich über den Scheduler, jederzeit über «Betrieb».
 */
final class Sicherung
{
    public const string ORDNER = 'sicherungen';

    public const int BEHALTEN = 14;

    public const string LETZTE = 'sicherung_letzte';

    public const string FEHLER = 'sicherung_fehler';

    private const string DISK = 'local';

    private const string MUSTER = '/^notenportal-\d{8}-\d{6}\.zip$/';

    public function erstellen(): string
    {
        $sperre = Cache::lock('notenportal-sicherung', 900);
        if (! $sperre->get()) {
            throw new RuntimeException('Es läuft bereits eine Sicherung.');
        }

        try {
            return $this->ausfuehren();
        } finally {
            $sperre->release();
        }
    }

    private function ausfuehren(): string
    {
        @set_time_limit(900);
        $name = 'notenportal-'.now()->format('Ymd-His').'.zip';
        $dump = tempnam(sys_get_temp_dir(), 'np-dump');
        $ziel = Storage::disk(self::DISK)->path(self::ORDNER.'/'.$name);
        $teil = $ziel.'.teil'; // erscheint erst nach vollständigem Schreiben in der Liste

        try {
            $this->datenbank($dump);

            File::ensureDirectoryExists(dirname($ziel));
            $zip = new ZipArchive;
            if ($zip->open($teil, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('ZIP-Datei lässt sich nicht anlegen.');
            }
            $zip->addFile($dump, 'datenbank.sql');
            foreach ([...Storage::disk(self::DISK)->allFiles('lernende'), ...Storage::disk(self::DISK)->allFiles('betrieb'), ...Storage::disk(self::DISK)->allFiles('feedback')] as $datei) {
                $zip->addFile(Storage::disk(self::DISK)->path($datei), 'dateien/'.$datei);
            }
            $zip->addFromString('LIESMICH.txt', $this->anleitung());
            if (! $zip->close() || ! rename($teil, $ziel)) {
                throw new RuntimeException('ZIP-Datei lässt sich nicht schreiben.');
            }
        } catch (\Throwable $e) {
            @unlink($teil);
            Einstellungen::set(self::FEHLER, now()->format('d.m.Y H:i').' – '.mb_substr($e->getMessage(), 0, 300));
            throw $e;
        } finally {
            @unlink($dump);
        }

        Einstellungen::set(self::LETZTE, now()->toIso8601String());
        Einstellungen::set(self::FEHLER, null);
        $this->aufraeumen();

        return $name;
    }

    /** @return list<array{name: string, groesse: int, datum: Carbon}> neuste zuerst */
    public function liste(): array
    {
        return collect(Storage::disk(self::DISK)->files(self::ORDNER))
            ->map(fn (string $pfad) => basename($pfad))
            ->filter(fn (string $name) => preg_match(self::MUSTER, $name) === 1)
            ->sortDesc()
            ->values()
            ->map(fn (string $name) => [
                'name' => $name,
                'groesse' => (int) Storage::disk(self::DISK)->size(self::ORDNER.'/'.$name),
                'datum' => Carbon::createFromFormat('Ymd-His', substr($name, 12, 15)),
            ])
            ->all();
    }

    /** Pfad einer vorhandenen Sicherung; unbekannte oder manipulierte Namen ergeben 404. */
    public function pfad(string $name): string
    {
        abort_unless(preg_match(self::MUSTER, $name) === 1 && Storage::disk(self::DISK)->exists(self::ORDNER.'/'.$name), 404);

        return Storage::disk(self::DISK)->path(self::ORDNER.'/'.$name);
    }

    public function loeschen(string $name): void
    {
        $this->pfad($name);
        Storage::disk(self::DISK)->delete(self::ORDNER.'/'.$name);
    }

    /** Letzte erfolgreiche Sicherung, null wenn es noch keine gibt. */
    public function letzte(): ?Carbon
    {
        $wert = Einstellungen::get(self::LETZTE);

        return $wert ? Carbon::parse($wert) : null;
    }

    public function fehler(): ?string
    {
        return Einstellungen::get(self::FEHLER) ?: null;
    }

    private function aufraeumen(): void
    {
        foreach (array_slice($this->liste(), self::BEHALTEN) as $alt) {
            Storage::disk(self::DISK)->delete(self::ORDNER.'/'.$alt['name']);
        }
    }

    private function datenbank(string $datei): void
    {
        $verbindung = config('database.connections.'.config('database.default'));
        $programm = is_executable('/usr/bin/mariadb-dump') ? '/usr/bin/mariadb-dump' : 'mysqldump';

        // Passwort über die Umgebung, nicht über die Befehlszeile (sonst in der Prozessliste sichtbar)
        $ergebnis = Process::timeout(900)->env(['MYSQL_PWD' => (string) ($verbindung['password'] ?? '')])->run([
            $programm, '--single-transaction', '--routines', '--triggers', '--no-tablespaces', '--default-character-set=utf8mb4',
            '--host='.($verbindung['host'] ?? '127.0.0.1'), '--port='.($verbindung['port'] ?? '3306'),
            '--user='.($verbindung['username'] ?? ''), '--result-file='.$datei, (string) $verbindung['database'],
        ]);

        if (! $ergebnis->successful() || ! is_file($datei) || filesize($datei) < 100) {
            throw new RuntimeException('Datenbank-Export fehlgeschlagen'.($ergebnis->errorOutput() !== '' ? ': '.trim($ergebnis->errorOutput()) : '.'));
        }
    }

    private function anleitung(): string
    {
        return <<<'TXT'
            Notenportal – Sicherung
            =======================
            datenbank.sql   vollständiger Export der Datenbank
            dateien/        hochgeladene Dokumente (storage/app/private/lernende), Betriebslogo (storage/app/private/betrieb)
                            und Feedback-Screenshots/-Anhänge (storage/app/private/feedback)

            Wiederherstellen (auf dem Server, im Verzeichnis des Notenportals):
            1. sudo mysql <Datenbankname> < datenbank.sql
            2. Inhalt von dateien/ nach storage/app/private kopieren,
               danach: sudo chgrp -R www-data storage/app/private && sudo chmod -R g+rwX storage/app/private
            3. php artisan optimize:clear
            TXT;
    }
}
