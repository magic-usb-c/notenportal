<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Feedback;
use App\Models\FeedbackAnhang;
use App\Services\Feedback\Anhang;
use App\Services\Feedback\Screenshot;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Löscht Dateien unter feedback/ (Screenshots, Anhänge), zu denen keine Datenbankzeile mehr gehört.
 * Im Normalfall gibt es die nicht: Feedback::booted() räumt beim Löschen über das Model auf. Verwaist
 * bleibt nur, was an Eloquent vorbei verschwand (Fremdschlüssel-Kaskade, Raw-Delete, abgebrochener
 * Upload) – darum wöchentlich im Schedule (routes/console.php). Dateien jünger als ein Tag bleiben
 * stehen: ein Upload, dessen Datenbankzeile noch nicht geschrieben ist, wäre sonst weg.
 */
#[Description('Verwaiste Feedback-Dateien ohne Datenbankzeile löschen')]
#[Signature('notenportal:feedback-aufraeumen {--vorschau : Nur auflisten, nichts löschen}')]
class FeedbackAufraeumen extends Command
{
    public const string ORDNER = 'feedback';

    public function handle(): int
    {
        if (! Schema::hasTable('feedback')) {
            $this->line('Feedback noch nicht eingerichtet – nichts zu tun.');

            return self::SUCCESS;
        }

        $bekannt = Feedback::query()->whereNotNull('screenshot_pfad')->pluck('screenshot_pfad');
        if (Feedback::hatAnhaengeTabelle()) {
            $bekannt = $bekannt->merge(FeedbackAnhang::query()->pluck('pfad'));
        }
        $bekannt = $bekannt->flip()->all();
        $grenze = now()->subDay()->getTimestamp();
        $vorschau = (bool) $this->option('vorschau');
        $anzahl = 0;

        foreach (array_unique([Screenshot::DISK, Anhang::DISK]) as $diskName) {
            $disk = Storage::disk($diskName);
            $verwaist = array_values(array_filter(
                $disk->allFiles(self::ORDNER),
                fn (string $pfad) => ! isset($bekannt[$pfad]) && $disk->lastModified($pfad) < $grenze,
            ));
            foreach ($verwaist as $pfad) {
                $this->line(($vorschau ? 'verwaist: ' : 'gelöscht: ').$diskName.'/'.$pfad);
            }
            if (! $vorschau && $verwaist !== []) {
                $disk->delete($verwaist);
            }
            $anzahl += count($verwaist);
        }

        $this->line($anzahl === 0
            ? 'Keine verwaisten Dateien.'
            : $anzahl.' Dateien '.($vorschau ? 'verwaist (Vorschau, nichts gelöscht).' : 'gelöscht.'));

        return self::SUCCESS;
    }
}
