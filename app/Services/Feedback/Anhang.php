<?php

declare(strict_types=1);

namespace App\Services\Feedback;

use App\Exceptions\DateiNichtGespeichert;
use App\Models\Feedback;
use App\Models\FeedbackAnhang;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Eigene Anhänge zu einer Feedback-Meldung (Block G): bis zu drei Dateien, je max. 5 MB, PNG/JPG/
 * WebP/PDF/TXT/LOG. Der Inhalt wird geprüft (nicht nur die Endung): Bilder wie beim Betriebslogo
 * mit GD neu kodiert (App\Support\Betriebslogo::neuKodieren – gleiches Vorgehen), aber – anders als
 * beim (nur für Admins zugänglichen) Betriebslogo – ohne GD-Erweiterung oder bei einem trotz
 * gültigem Kopf nicht dekodierbaren Bild abgelehnt statt ungeprüft übernommen (breitere Nutzerschaft,
 * siehe Anhang::speichern()). PDF an der Signatur erkannt, Text-Dateien müssen gültiges UTF-8 ohne
 * Nullbytes sein (schliesst getarnten Binärcode mit .txt/.log-Endung aus). Abgelegt auf der privaten
 * Disk, ausgeliefert nur an Admins und die meldende Person
 * (App\Http\Controllers\FeedbackController::anhang) mit Content-Disposition attachment, nosniff und
 * einer sandboxenden CSP.
 */
final class Anhang
{
    public const int MAX_KB = 5120; // 5 MB

    public const int MAX_DATEIEN = 3;

    /** Je Seite (wie App\Support\Betriebslogo::MAX_PX), verhindert eine GD-Dekompressionsbombe: ein
     * kleines PNG/WebP mit sehr hoher Auflösung würde in imagecreatefromstring() ein Vielfaches des
     * Dateigewichts an Speicher belegen (Breite × Höhe × 4 Byte). */
    public const int MAX_PX = 4000;

    public const string DISK = 'local';

    private const string ORDNER = 'feedback/anhaenge';

    /** getimagesize()-Bildtyp => [Endung, MIME]. Bewusst keine weiteren Formate (kein SVG/HTML – XSS). */
    private const array BILD_TYPEN = [
        IMAGETYPE_PNG => ['png', 'image/png'],
        IMAGETYPE_JPEG => ['jpg', 'image/jpeg'],
        IMAGETYPE_WEBP => ['webp', 'image/webp'],
    ];

    /** @return array<string, list<mixed>> */
    public static function regeln(): array
    {
        return [
            'anhaenge' => ['nullable', 'array', 'max:'.self::MAX_DATEIEN],
            'anhaenge.*' => [
                'file', 'max:'.self::MAX_KB,
                'extensions:png,jpg,jpeg,webp,pdf,txt,log',
                self::inhaltPruefen(...),
            ],
        ];
    }

    private static function inhaltPruefen(string $attribute, mixed $wert, Closure $fail): void
    {
        if (! $wert instanceof UploadedFile || ! $wert->isValid()) {
            return;
        }

        $pfad = $wert->getRealPath();
        $info = @getimagesize($pfad);
        if ($info !== false && isset(self::BILD_TYPEN[$info[2]])) {
            if ($info[0] > self::MAX_PX || $info[1] > self::MAX_PX) {
                $fail(__('Das Bild darf höchstens :px × :px Pixel gross sein.', ['px' => self::MAX_PX]));
            }

            return;
        }

        if (self::istPdf($pfad) || self::istText($pfad)) {
            return;
        }

        $fail(__('Die Datei ist kein gültiges Bild, PDF oder Textdokument (PNG, JPG, WebP, PDF, TXT, LOG).'));
    }

    /** @return array{typ: int, endung: string, mime: string}|null Null auch bei zu grossen Massen (MAX_PX, Schutz vor GD-Dekompressionsbomben – siehe inhaltPruefen()). */
    private static function bildInfo(string $pfad): ?array
    {
        $info = @getimagesize($pfad);
        if ($info === false || ! isset(self::BILD_TYPEN[$info[2]]) || $info[0] > self::MAX_PX || $info[1] > self::MAX_PX) {
            return null;
        }
        [$endung, $mime] = self::BILD_TYPEN[$info[2]];

        return ['typ' => $info[2], 'endung' => $endung, 'mime' => $mime];
    }

    private static function istPdf(string $pfad): bool
    {
        return @file_get_contents($pfad, false, null, 0, 5) === '%PDF-';
    }

    /** Reine Textdatei: gültiges UTF-8, keine Nullbytes. */
    private static function istText(string $pfad): bool
    {
        $inhalt = @file_get_contents($pfad, false, null, 0, 65536);

        return $inhalt !== false && ! str_contains($inhalt, "\0") && mb_check_encoding($inhalt, 'UTF-8');
    }

    /** @return array{dateiname: string, pfad: string, mime: string, groesse: int} */
    public function speichern(UploadedFile $datei): array
    {
        $original = self::sichererDateiname(mb_substr($datei->getClientOriginalName(), 0, 180));
        $quelle = $datei->getRealPath();
        $bild = self::bildInfo($quelle);

        if ($bild !== null) {
            $endung = $bild['endung'];
            $mime = $bild['mime'];
            $inhalt = self::neuKodieren($quelle, $bild['typ']);
            if ($inhalt === null) {
                // Neukodieren ist die Schutzmassnahme gegen eingebettete Nutzlast in Bilddateien
                // (z. B. ein gültiger Bildkopf mit angehängtem Skript). Fehlt sie – GD nicht
                // installiert oder das Bild liess sich trotz gültigem Kopf nicht dekodieren –,
                // dürfen nie die ungeprüften Originalbytes übernommen werden.
                throw ValidationException::withMessages([
                    'anhaenge' => __('Das Bild liess sich nicht verarbeiten. Bitte ein anderes Bild oder ein PDF anhängen.'),
                ]);
            }
        } elseif (self::istPdf($quelle)) {
            $endung = 'pdf';
            $mime = 'application/pdf';
            $inhalt = file_get_contents($quelle);
        } else {
            $endung = strtolower($datei->extension() ?: 'txt') === 'log' ? 'log' : 'txt';
            $mime = 'text/plain';
            $inhalt = file_get_contents($quelle);
        }

        $inhalt = $inhalt === false ? '' : $inhalt;
        $pfad = self::ORDNER.'/'.now()->format('Y').'/'.Str::uuid()->toString().'.'.$endung;
        DateiNichtGespeichert::pruefen(Storage::disk(self::DISK)->put($pfad, $inhalt), $pfad);

        return [
            'dateiname' => $original !== '' ? $original : 'anhang.'.$endung,
            'pfad' => $pfad,
            'mime' => $mime,
            'groesse' => strlen($inhalt),
        ];
    }

    /**
     * Für Content-Disposition sicherer Name (der Klientname ist nicht vertrauenswürdig): Symfonys
     * HeaderUtils::makeDisposition() verlangt reines druckbares ASCII und lehnt «/» und «\» ab – ein
     * Steuerzeichen oder Pfadtrenner im ungeprüften Originalnamen würde die Auslieferung sonst dauerhaft
     * mit 500 abbrechen. Umlaute/Akzente werden dabei transliteriert (wie Str::ascii es auch für den
     * Symfony-Fallback-Namen tut), nicht einfach verworfen.
     */
    private static function sichererDateiname(string $name): string
    {
        $bereinigt = preg_replace('/[^\x20-\x7E]/', '', Str::ascii($name)) ?? '';
        $bereinigt = trim(str_replace(['/', '\\'], '_', $bereinigt));

        return $bereinigt !== '' ? $bereinigt : 'anhang';
    }

    /** Wie App\Support\Betriebslogo::neuKodieren: verwirft alles ausser den reinen Pixeldaten (EXIF, angehängte Fremddaten). Null, wenn GD fehlt oder das Bild nicht dekodierbar ist. */
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
        imagesavealpha($bild, true);

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

    /** Nur für Admins und die meldende Person (Autorisierung: App\Http\Controllers\FeedbackController::anhang). */
    public function ausliefern(FeedbackAnhang $anhang): StreamedResponse
    {
        return Storage::disk(self::DISK)->response($anhang->pfad, $anhang->dateiname, [
            'Content-Type' => $anhang->mime ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => 'sandbox; default-src \'none\'',
            'Cache-Control' => 'private, no-store',
        ], 'attachment');
    }

    public function loeschen(FeedbackAnhang $anhang): void
    {
        Storage::disk(self::DISK)->delete($anhang->pfad);
    }

    /** Löscht alle Anhang-Dateien einer Meldung von der Disk (die Datenbankzeilen entfernt die Fremdschlüssel-Kaskade). */
    public function loeschenAlle(Feedback $feedback): void
    {
        foreach ($feedback->anhaenge as $anhang) {
            $this->loeschen($anhang);
        }
    }
}
