<?php

declare(strict_types=1);

namespace App\Services\Betrieb;

use App\Support\Einstellungen;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Kopie der Sicherungen ausser Haus: spiegelt storage/app/private/sicherungen/notenportal-*.zip per rsync
 * in einen Ordner (z. B. eingebundenes Netzlaufwerk) oder per SSH auf einen anderen Server.
 * Nur Sicherungsdateien werden am Ziel gelöscht, andere Dateien dort bleiben unberührt.
 *
 * SSH-Schlüssel: storage/app/private/ssh/id_ed25519, erzeugt vom Web-/Cron-Benutzer (www-data),
 * öffentlicher Teil wird auf «Betrieb» angezeigt und am Ziel in authorized_keys eingetragen.
 */
final class SicherungKopie
{
    public const string ZIEL = 'sicherung_kopie_ziel';

    public const string HOST = 'sicherung_kopie_host';

    public const string PORT = 'sicherung_kopie_port';

    public const string BENUTZER = 'sicherung_kopie_benutzer';

    public const string PFAD = 'sicherung_kopie_pfad';

    public const string LETZTE = 'sicherung_kopie_letzte';

    public const string FEHLER = 'sicherung_kopie_fehler';

    public const array ZIELE = ['' => 'Aus', 'ordner' => 'Ordner auf diesem Server (z. B. Netzlaufwerk)', 'ssh' => 'Anderer Server (SSH)'];

    private const string SSH_ORDNER = 'ssh';

    public function aktiv(): bool
    {
        return in_array($this->ziel(), ['ordner', 'ssh'], true) && filled(Einstellungen::get(self::PFAD));
    }

    public function ziel(): string
    {
        return (string) Einstellungen::get(self::ZIEL, '');
    }

    /** @return array<string, string> */
    public function werte(): array
    {
        return [
            self::ZIEL => $this->ziel(),
            self::HOST => (string) Einstellungen::get(self::HOST, ''),
            self::PORT => (string) Einstellungen::get(self::PORT, '22'),
            self::BENUTZER => (string) Einstellungen::get(self::BENUTZER, ''),
            self::PFAD => (string) Einstellungen::get(self::PFAD, ''),
        ];
    }

    /** @return array<string, list<mixed>> */
    public static function regeln(): array
    {
        return [
            self::ZIEL => ['nullable', 'in:ordner,ssh'],
            self::HOST => ['nullable', 'required_if:'.self::ZIEL.',ssh', 'max:190', 'regex:/^[A-Za-z0-9][A-Za-z0-9.:-]*$/'],
            self::PORT => ['nullable', 'integer', 'between:1,65535'],
            self::BENUTZER => ['nullable', 'required_if:'.self::ZIEL.',ssh', 'max:64', 'regex:/^[A-Za-z_][A-Za-z0-9_.-]*$/'],
            self::PFAD => ['nullable', 'required_with:'.self::ZIEL, 'max:250', 'regex:#^[A-Za-z0-9_./~-]+$#'],
        ];
    }

    public function speichern(array $validiert): void
    {
        $ziel = (string) ($validiert[self::ZIEL] ?? '');
        if ($ziel === 'ordner') {
            $pfad = rtrim((string) $validiert[self::PFAD], '/');
            if (! str_starts_with($pfad, '/') || str_starts_with($pfad, base_path())) {
                throw new RuntimeException('Der Ordner muss ein absoluter Pfad ausserhalb des Notenportals sein.');
            }
        }
        foreach ([self::ZIEL, self::HOST, self::PORT, self::BENUTZER, self::PFAD] as $k) {
            $wert = trim((string) ($validiert[$k] ?? ''));
            Einstellungen::set($k, $wert !== '' ? $wert : null);
        }
    }

    /** Spiegelt die Sicherungen zum Ziel. Wirft bei Fehler (Meldung ohne Geheimnisse). */
    public function kopieren(): void
    {
        if (! $this->aktiv()) {
            throw new RuntimeException('Keine Kopie ausser Haus eingerichtet.');
        }
        try {
            $this->ausfuehren($this->befehl(), 900);
        } catch (\Throwable $e) {
            Einstellungen::set(self::FEHLER, now()->format('d.m.Y H:i').' – '.mb_substr($e->getMessage(), 0, 300));
            throw $e;
        }
        Einstellungen::set(self::LETZTE, now()->toIso8601String());
        Einstellungen::set(self::FEHLER, null);
    }

    /** Verbindung und Schreibrecht am Ziel prüfen, ohne etwas zu kopieren. */
    public function testen(): void
    {
        $w = $this->werte();
        if ($this->ziel() === 'ordner') {
            if (! is_dir($w[self::PFAD]) && ! @mkdir($w[self::PFAD], 0770, true)) {
                throw new RuntimeException('Ordner '.$w[self::PFAD].' existiert nicht und lässt sich nicht anlegen.');
            }
            if (! is_writable($w[self::PFAD])) {
                throw new RuntimeException('Ordner '.$w[self::PFAD].' ist für den Webserver nicht beschreibbar.');
            }

            return;
        }
        $this->ausfuehren([...$this->ssh(), $w[self::BENUTZER].'@'.$w[self::HOST], 'mkdir -p '.escapeshellarg($w[self::PFAD]).' && test -w '.escapeshellarg($w[self::PFAD])], 30);
    }

    /** @return list<string> rsync-Aufruf als Argumentliste (keine Shell). */
    public function befehl(): array
    {
        $w = $this->werte();
        $quelle = Storage::disk('local')->path(Sicherung::ORDNER).'/';
        $basis = ['rsync', '-a', '--timeout=120', '--include=notenportal-*.zip', '--exclude=*', '--delete'];

        if ($this->ziel() === 'ordner') {
            return [...$basis, $quelle, rtrim($w[self::PFAD], '/').'/'];
        }

        return [...$basis, '-e', implode(' ', $this->ssh()), $quelle, $w[self::BENUTZER].'@'.$w[self::HOST].':'.rtrim($w[self::PFAD], '/').'/'];
    }

    /** Öffentlicher Schlüssel (legt das Schlüsselpaar beim ersten Aufruf an). */
    public function oeffentlicherSchluessel(): string
    {
        $privat = $this->schluesselPfad();
        if (! is_file($privat)) {
            File::ensureDirectoryExists(dirname($privat), 0700);
            $this->ausfuehren(['ssh-keygen', '-q', '-t', 'ed25519', '-N', '', '-C', 'notenportal@'.gethostname(), '-f', $privat], 20);
        }

        return trim((string) @file_get_contents($privat.'.pub'));
    }

    public function schluesselVorhanden(): bool
    {
        return is_file($this->schluesselPfad());
    }

    public function letzte(): ?Carbon
    {
        $wert = Einstellungen::get(self::LETZTE);

        return $wert ? Carbon::parse($wert) : null;
    }

    public function fehler(): ?string
    {
        return Einstellungen::get(self::FEHLER);
    }

    /** @return list<string> */
    private function ssh(): array
    {
        $ordner = dirname($this->schluesselPfad());

        return ['ssh', '-i', $this->schluesselPfad(), '-p', (string) (int) (Einstellungen::get(self::PORT) ?: 22),
            '-o', 'BatchMode=yes', '-o', 'StrictHostKeyChecking=accept-new', '-o', 'UserKnownHostsFile='.$ordner.'/known_hosts', '-o', 'ConnectTimeout=15'];
    }

    private function schluesselPfad(): string
    {
        return Storage::disk('local')->path(self::SSH_ORDNER.'/id_ed25519');
    }

    private function ausfuehren(array $befehl, int $timeout): void
    {
        $prozess = new Process($befehl, base_path(), ['LC_ALL' => 'C']);
        $prozess->setTimeout($timeout);
        $prozess->run();
        if (! $prozess->isSuccessful()) {
            $meldung = trim($prozess->getErrorOutput() ?: $prozess->getOutput());
            throw new RuntimeException('Kopie fehlgeschlagen: '.mb_substr($meldung !== '' ? $meldung : 'Exit-Code '.$prozess->getExitCode(), 0, 250));
        }
    }
}
