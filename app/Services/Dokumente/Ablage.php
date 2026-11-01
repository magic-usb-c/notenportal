<?php

declare(strict_types=1);

namespace App\Services\Dokumente;

use App\Exceptions\DateiNichtGespeichert;
use App\Models\Dokument;
use App\Models\Lernender;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Private Ablage: Disk «local» (storage/app/private, nie öffentlich ausgeliefert),
 * Pfad lernende/{id}/dokumente/{jahr}/{uuid}.{endung}; Originalname nur in der DB.
 */
final class Ablage
{
    public const int MAX_KB = 10240;

    /** Länge der Spalte dokumente.titel; ein Dateiname als Titel wird darauf gekürzt. */
    public const int TITEL_MAX = 150;

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
            'datei' => self::dateiregeln(self::MAX_KB, self::ENDUNGEN, self::MIMES, static fn (): string => __('Erlaubt sind PDF, Bild, Word, Excel und CSV.')),
            'art' => ['required', Rule::in(array_keys(Dokument::ARTEN))],
            'semester_id' => ['nullable', 'integer', 'exists:semester,semester_id'],
            'titel' => ['nullable', 'string', 'max:150'],
        ];
    }

    /**
     * Regeln für ein hochgeladenes Dokument mit Meldungen in Worten statt Kilobyte und Medientyp-Liste.
     * Gleiche Prüfungen wie max/extensions/mimetypes: Endung nach dem Namen, Inhalt nach finfo.
     *
     * @param  list<string>  $endungen
     * @param  list<string>  $mimes
     * @param  Closure(): string  $erlaubt  erlaubte Arten in Worten, erst bei einem Fehler übersetzt
     * @return list<mixed>
     */
    public static function dateiregeln(int $maxKb, array $endungen, array $mimes, Closure $erlaubt): array
    {
        $pruefen = static fn (Closure $ok, Closure $meldung): Closure => static function (string $attribut, mixed $wert, Closure $fail) use ($ok, $meldung): void {
            if ($wert instanceof UploadedFile && ! $ok($wert)) {
                $fail($meldung());
            }
        };

        return [
            'bail', 'required', 'file',
            $pruefen(
                static fn (UploadedFile $d): bool => ($d->getSize() ?: 0) / 1024 <= $maxKb,
                static fn (): string => __('Die Datei ist zu gross (höchstens :mb MB).', ['mb' => intdiv($maxKb, 1024)]),
            ),
            $pruefen(
                static fn (UploadedFile $d): bool => in_array(strtolower($d->getClientOriginalExtension()), $endungen, true),
                static fn (): string => __('Die Endung dieser Datei ist nicht erlaubt.').' '.$erlaubt(),
            ),
            $pruefen(
                static fn (UploadedFile $d): bool => in_array($d->getMimeType(), $mimes, true),
                static fn (): string => __('Der Inhalt der Datei passt nicht zur Endung.').' '.$erlaubt(),
            ),
        ];
    }

    public function speichern(Lernender $lernender, UploadedFile $datei, array $daten, int $benutzerId, ?int $pruefungId = null): Dokument
    {
        $endung = strtolower($datei->getClientOriginalExtension()) ?: ($datei->guessExtension() ?? 'bin');
        $original = mb_substr($datei->getClientOriginalName(), 0, 255);
        $pfad = DateiNichtGespeichert::pruefen($datei->storeAs(
            'lernende/'.$lernender->lernender_id.'/dokumente/'.now()->format('Y'),
            Str::uuid()->toString().'.'.$endung,
            self::DISK,
        ), 'lernende/'.$lernender->lernender_id.'/dokumente/'.now()->format('Y'));

        try {
            return Dokument::create([
                'lernender_id' => $lernender->lernender_id,
                'semester_id' => $daten['semester_id'] ?? null,
                'pruefung_id' => $pruefungId,
                'art' => $daten['art'],
                'titel' => filled($daten['titel'] ?? null) ? $daten['titel'] : mb_substr(pathinfo($original, PATHINFO_FILENAME) ?: 'Dokument', 0, self::TITEL_MAX),
                'originalname' => $original,
                'pfad' => $pfad,
                'mime' => $datei->getMimeType() ?? 'application/octet-stream',
                'groesse' => (int) $datei->getSize(),
                'sha256' => hash_file('sha256', $datei->getRealPath()),
                'hochgeladen_von_benutzer_id' => $benutzerId,
            ]);
        } catch (Throwable $e) {
            Storage::disk(self::DISK)->delete($pfad);

            throw $e;
        }
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
            isset(Dokument::ARTEN[$dokument->art]) ? Dokument::label($dokument->art) : null,
            $dokument->semester?->bezeichnung,
            $dokument->art === 'sonstiges' ? $dokument->titel : null,
        ]);

        return Str::slug(implode(' ', $teile), '_').'.'.$dokument->endung();
    }
}
