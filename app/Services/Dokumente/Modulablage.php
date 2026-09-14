<?php

declare(strict_types=1);

namespace App\Services\Dokumente;

use App\Models\Modul;
use App\Models\ModulDokument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Private Ablage für Unterlagen zu einem Modul: Disk «local» (storage/app/private, nie öffentlich),
 * Pfad module/{modul_id}/{uuid}.{endung}; der Originalname steht nur in der Datenbank.
 *
 * Anders als App\Services\Dokumente\Ablage hängen diese Dateien am Modul, nicht an einer Person:
 * wer eine Modulbeschreibung hochlädt, stellt sie allen Angemeldeten zur Verfügung. Deshalb wird
 * eine Datei auch nie kopiert – es bleibt bei einer Datei je Modul-Unterlage.
 */
final class Modulablage
{
    public const int MAX_KB = 20480;

    public const array ENDUNGEN = ['pdf', 'jpg', 'jpeg', 'png', 'docx', 'odt'];

    /** Vom Server erkannte Typen (finfo), nicht die Angabe des Browsers. */
    private const array MIMES = [
        'application/pdf', 'image/jpeg', 'image/png',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.oasis.opendocument.text', 'application/zip',
    ];

    private const string DISK = 'local';

    /** @return array<string, list<string>> */
    public static function regeln(): array
    {
        return [
            'datei' => ['required', 'file', 'max:'.self::MAX_KB, 'extensions:'.implode(',', self::ENDUNGEN), 'mimetypes:'.implode(',', self::MIMES)],
            'titel' => ['nullable', 'string', 'max:150'],
        ];
    }

    public function speichern(Modul $modul, UploadedFile $datei, ?string $titel, int $benutzerId): ModulDokument
    {
        $endung = strtolower($datei->getClientOriginalExtension()) ?: ($datei->guessExtension() ?? 'bin');
        $original = mb_substr($datei->getClientOriginalName(), 0, 255);
        $pfad = $datei->storeAs(
            'module/'.$modul->modul_id,
            Str::uuid()->toString().'.'.$endung,
            self::DISK,
        );

        return ModulDokument::create([
            'modul_id' => $modul->modul_id,
            'titel' => filled($titel) ? $titel : (pathinfo($original, PATHINFO_FILENAME) ?: 'Unterlage'),
            'originalname' => $original,
            'pfad' => $pfad,
            'mime' => $datei->getMimeType() ?? 'application/octet-stream',
            'groesse' => (int) $datei->getSize(),
            'sha256' => hash_file('sha256', (string) $datei->getRealPath()),
            'hochgeladen_von_benutzer_id' => $benutzerId,
        ]);
    }

    public function ausliefern(ModulDokument $dokument, bool $anzeigen): StreamedResponse
    {
        $inline = $anzeigen && in_array($dokument->mime, ModulDokument::INLINE, true);

        return Storage::disk(self::DISK)->response($dokument->pfad, $this->dateiname($dokument), [
            'Content-Type' => $dokument->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => 'sandbox; default-src \'none\'; img-src \'self\'; style-src \'unsafe-inline\'; object-src \'self\'',
            'Cache-Control' => 'private, no-store',
        ], $inline ? 'inline' : 'attachment');
    }

    public function loeschen(ModulDokument $dokument): void
    {
        Storage::disk(self::DISK)->delete($dokument->pfad);
        $dokument->delete();
    }

    /** Sprechender Name beim Herunterladen, z. B. M431_Modulbeschreibung.pdf */
    public function dateiname(ModulDokument $dokument): string
    {
        $teile = array_filter([$dokument->modul?->modul_nummer, $dokument->titel]);

        return Str::slug(implode(' ', $teile), '_').'.'.$dokument->endung();
    }
}
