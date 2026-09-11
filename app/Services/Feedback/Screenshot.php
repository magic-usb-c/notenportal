<?php

declare(strict_types=1);

namespace App\Services\Feedback;

use App\Models\Feedback;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Optionaler Screenshot einer Meldung: privat auf Disk «local» (storage/app/private, nie öffentlich),
 * Pfad feedback/{jahr}/{uuid}.{endung}. Client verkleinert bereits (max. ~1600 px, JPEG/WebP);
 * hier nur serverseitige Prüfung von Typ und Grösse sowie die Auslieferung an Admins.
 */
final class Screenshot
{
    public const int MAX_KB = 1536;

    private const string DISK = 'local';

    /** @return array<string, list<mixed>> */
    public static function regeln(): array
    {
        return [
            'screenshot' => ['nullable', 'file', 'image', 'max:'.self::MAX_KB, 'mimes:jpg,jpeg,webp,png'],
        ];
    }

    /** @return array{pfad: string, mime: string, groesse: int} */
    public function speichern(UploadedFile $datei): array
    {
        $endung = strtolower($datei->extension() ?: $datei->guessExtension() ?: 'jpg');
        $pfad = $datei->storeAs('feedback/'.now()->format('Y'), Str::uuid()->toString().'.'.$endung, self::DISK);

        return [
            'pfad' => $pfad,
            'mime' => $datei->getMimeType() ?? 'application/octet-stream',
            'groesse' => (int) $datei->getSize(),
        ];
    }

    public function ausliefern(Feedback $feedback): StreamedResponse
    {
        return Storage::disk(self::DISK)->response($feedback->screenshot_pfad, 'feedback-'.$feedback->feedback_id.'.jpg', [
            'Content-Type' => $feedback->screenshot_mime ?? 'image/jpeg',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => 'sandbox; default-src \'none\'; img-src \'self\'; style-src \'unsafe-inline\'; object-src \'self\'',
            'Cache-Control' => 'private, no-store',
        ], 'inline');
    }

    public function loeschen(Feedback $feedback): void
    {
        if ($feedback->screenshot_pfad) {
            Storage::disk(self::DISK)->delete($feedback->screenshot_pfad);
        }
    }
}
