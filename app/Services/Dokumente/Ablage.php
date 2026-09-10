<?php

declare(strict_types=1);

namespace App\Services\Dokumente;

use App\Models\Dokument;
use App\Models\Lernender;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Private Ablage: Disk «local» (storage/app/private, nie öffentlich ausgeliefert),
 * Pfad lernende/{id}/dokumente/{jahr}/{uuid}.{endung}; Originalname nur in der DB.
 */
final class Ablage
{
    public const int MAX_KB = 10240;

    public const array ENDUNGEN = ['pdf', 'jpg', 'jpeg', 'png', 'xlsx', 'xls', 'ods', 'csv', 'docx'];

    /** Vom Server erkannte Typen (finfo), nicht die Angabe des Browsers. */
    private const array MIMES = [
        'application/pdf', 'image/jpeg', 'image/png', 'text/plain', 'text/csv', 'application/csv',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel',
        'application/vnd.oasis.opendocument.spreadsheet', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/zip', 'application/CDFV2', 'application/x-ole-storage',
    ];

    private const string DISK = 'local';

    /** @return array<string, list<mixed>> */
    public static function regeln(): array
    {
        return [
            'datei' => ['required', 'file', 'max:'.self::MAX_KB, 'extensions:'.implode(',', self::ENDUNGEN), 'mimetypes:'.implode(',', self::MIMES)],
            'art' => ['required', Rule::in(array_keys(Dokument::ARTEN))],
            'semester_id' => ['nullable', 'integer', 'exists:semester,semester_id'],
            'titel' => ['nullable', 'string', 'max:150'],
        ];
    }

    public function speichern(Lernender $lernender, UploadedFile $datei, array $daten, int $benutzerId): Dokument
    {
        $endung = strtolower($datei->getClientOriginalExtension()) ?: ($datei->guessExtension() ?? 'bin');
        $original = mb_substr($datei->getClientOriginalName(), 0, 255);
        $pfad = $datei->storeAs(
            'lernende/'.$lernender->lernender_id.'/dokumente/'.now()->format('Y'),
            Str::uuid()->toString().'.'.$endung,
            self::DISK,
        );

        return Dokument::create([
            'lernender_id' => $lernender->lernender_id,
            'semester_id' => $daten['semester_id'] ?? null,
            'art' => $daten['art'],
            'titel' => filled($daten['titel'] ?? null) ? $daten['titel'] : (pathinfo($original, PATHINFO_FILENAME) ?: 'Dokument'),
            'originalname' => $original,
            'pfad' => $pfad,
            'mime' => $datei->getMimeType() ?? 'application/octet-stream',
            'groesse' => (int) $datei->getSize(),
            'sha256' => hash_file('sha256', $datei->getRealPath()),
            'hochgeladen_von_benutzer_id' => $benutzerId,
        ]);
    }

    public function pfad(Dokument $dokument): string
    {
        return Storage::disk(self::DISK)->path($dokument->pfad);
    }

    public function ausliefern(Dokument $dokument, bool $anzeigen): StreamedResponse
    {
        $inline = $anzeigen && in_array($dokument->mime, Dokument::INLINE, true);

        return Storage::disk(self::DISK)->response($dokument->pfad, $this->dateiname($dokument), [
            'Content-Type' => $dokument->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => 'sandbox; default-src \'none\'; img-src \'self\'; style-src \'unsafe-inline\'; object-src \'self\'',
            'Cache-Control' => 'private, no-store',
        ], $inline ? 'inline' : 'attachment');
    }

    public function loeschen(Dokument $dokument): void
    {
        Storage::disk(self::DISK)->delete($dokument->pfad);
        $dokument->delete();
    }

    /** Sprechender Name beim Herunterladen, z. B. Huber_Nina_Zeugnis_25-26-2.pdf */
    public function dateiname(Dokument $dokument): string
    {
        $benutzer = $dokument->lernender?->benutzer;
        $teile = array_filter([
            $benutzer?->nachname, $benutzer?->vorname,
            Dokument::ARTEN[$dokument->art] ?? null,
            $dokument->semester?->bezeichnung,
            $dokument->art === 'sonstiges' ? $dokument->titel : null,
        ]);

        return Str::slug(implode(' ', $teile), '_').'.'.$dokument->endung();
    }
}
