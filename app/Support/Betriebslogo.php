<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Betriebslogo (Block AH): PNG/JPG/WebP, max. 1 MB, max. 1024×1024 px, feste Ablage unter
 * betrieb/logo.<endung> auf der privaten Disk (storage/app/private), Einstellung `logo_datei`
 * (Einstellungen). Ausgeliefert öffentlich über GET /branding/logo (BrandingController, ohne
 * Login, für Login-Seite und Mail-Kopf). Der Bildtyp wird beim Hochladen am Inhalt geprüft
 * (getimagesize deckt SVG/HTML und getarnte Textdateien ab), nicht nur an Endung oder
 * Client-Mime-Angabe.
 */
final class Betriebslogo
{
    public const int MAX_KB = 1024;

    public const int MAX_PX = 1024;

    public const string ORDNER = 'betrieb';

    private const string DISK = 'local';

    /** getimagesize()-Bildtyp => [Endung, MIME]. Bewusst keine weiteren Formate (kein SVG/HTML – XSS). */
    private const array TYPEN = [
        IMAGETYPE_PNG => ['png', 'image/png'],
        IMAGETYPE_JPEG => ['jpg', 'image/jpeg'],
        IMAGETYPE_WEBP => ['webp', 'image/webp'],
    ];

    /** @return array<string, list<mixed>> */
    public static function regeln(): array
    {
        return [
            'logo' => [
                'required', 'file', 'max:'.self::MAX_KB,
                'extensions:png,jpg,jpeg,webp',
                'mimetypes:image/png,image/jpeg,image/webp',
                self::bildPruefen(...),
            ],
        ];
    }

    private static function bildPruefen(string $attribute, mixed $wert, Closure $fail): void
    {
        if (! $wert instanceof UploadedFile || ! $wert->isValid()) {
            return;
        }

        $info = @getimagesize($wert->getRealPath());
        if ($info === false || ! isset(self::TYPEN[$info[2]])) {
            $fail(__('Die Datei ist kein gültiges Bild (PNG, JPG oder WebP).'));

            return;
        }

        if ($info[0] > self::MAX_PX || $info[1] > self::MAX_PX) {
            $fail(__('Das Bild darf höchstens :px × :px Pixel gross sein.', ['px' => self::MAX_PX]));
        }
    }

    /**
     * Speichert das geprüfte Bild unter betrieb/logo.<endung> (ersetzt eine vorhandene Datei, auch bei
     * Typwechsel). Wird mit GD neu kodiert (Pixeldaten neu geschrieben statt der hochgeladenen Bytes) –
     * das entfernt EXIF/Metadaten und an den Bildstrom angehängte Fremddaten (Polyglot-Dateien, z. B. ein
     * gültiger PNG-Kopf mit angehängtem Skript). Fehlt die GD-Erweiterung, wird das geprüfte Original
     * unverändert gespeichert (weiterhin sicher, da Inhalt und Grösse bereits geprüft sind).
     */
    public static function speichern(UploadedFile $datei): void
    {
        $info = getimagesize($datei->getRealPath());
        $typ = $info[2] ?? null;
        $endung = self::TYPEN[$typ][0] ?? null;
        if ($endung === null || $typ === null) {
            return; // regeln() hat das schon geprüft – kommt praktisch nie vor
        }

        $inhalt = self::neuKodieren($datei->getRealPath(), $typ) ?? file_get_contents($datei->getRealPath());

        self::datenLoeschen();
        Storage::disk(self::DISK)->put(self::ORDNER.'/logo.'.$endung, $inhalt);
        Einstellungen::set(Einstellungen::LOGO_DATEI, 'logo.'.$endung);
    }

    /** Dekodiert und kodiert das Bild mit GD neu (verwirft alles ausser den reinen Pixeldaten). Null, wenn GD fehlt oder das Bild nicht dekodierbar ist. */
    private static function neuKodieren(string $pfad, int $typ): ?string
    {
        if (! extension_loaded('gd')) {
            return null;
        }

        $roh = file_get_contents($pfad);
        $bild = $roh !== false ? @imagecreatefromstring($roh) : false;
        if ($bild === false) {
            return null;
        }

        imagealphablending($bild, false);
        imagesavealpha($bild, true); // Transparenz (PNG/WebP) erhalten

        ob_start();
        $ok = match ($typ) {
            IMAGETYPE_PNG => imagepng($bild, null, 6),
            IMAGETYPE_JPEG => imagejpeg($bild, null, 90),
            IMAGETYPE_WEBP => imagewebp($bild, null, 90),
            default => false,
        };
        $inhalt = ob_get_clean();
        imagedestroy($bild);

        return ($ok && is_string($inhalt) && $inhalt !== '') ? $inhalt : null;
    }

    public static function entfernen(): void
    {
        self::datenLoeschen();
        Einstellungen::set(Einstellungen::LOGO_DATEI, null);
    }

    private static function datenLoeschen(): void
    {
        $aktuell = Einstellungen::get(Einstellungen::LOGO_DATEI);
        if ($aktuell) {
            Storage::disk(self::DISK)->delete(self::ORDNER.'/'.$aktuell);
        }
    }

    /** Absoluter Pfad, nur wenn Einstellung UND Datei vorhanden sind (schützt vor verwaister Einstellung). */
    public static function pfad(): ?string
    {
        $datei = Einstellungen::get(Einstellungen::LOGO_DATEI);
        if (! $datei || ! Storage::disk(self::DISK)->exists(self::ORDNER.'/'.$datei)) {
            return null;
        }

        return Storage::disk(self::DISK)->path(self::ORDNER.'/'.$datei);
    }

    public static function vorhanden(): bool
    {
        return self::pfad() !== null;
    }

    /** Dateiendung der aktuellen Datei (ohne Punkt), null ohne Logo. */
    public static function endung(): ?string
    {
        $datei = Einstellungen::get(Einstellungen::LOGO_DATEI);

        return $datei ? strtolower(pathinfo($datei, PATHINFO_EXTENSION)) : null;
    }

    public static function mime(): ?string
    {
        return match (self::endung()) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => null,
        };
    }

    /** Letzte Änderung der Datei (Unix-Zeitstempel), null ohne Logo – für Cache-Busting in url(). */
    public static function version(): ?int
    {
        $pfad = self::pfad();

        return $pfad !== null ? (int) filemtime($pfad) : null;
    }

    /**
     * Öffentliche URL für <img src>, null ohne Logo (Aufrufer zeigt dann den Platzhalter). Trägt
     * `?v=` (Änderungszeitpunkt der Datei) – so darf die Route lange gecacht werden (siehe
     * BrandingController), ein neu hochgeladenes Logo erscheint trotzdem sofort überall.
     */
    public static function url(): ?string
    {
        return self::vorhanden() ? route('branding.logo', ['v' => self::version()]) : null;
    }
}
