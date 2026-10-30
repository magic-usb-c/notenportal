<?php

declare(strict_types=1);

namespace App\Services\Notifications\Messages;

use App\Models\Lernender;
use App\Services\Notifications\MailContent;
use Illuminate\Support\Carbon;

/** Ein Lernender wurde einem Berufsbildner zur Betreuung zugewiesen. */
final class LearnerAssigned
{
    /**
     * @param  Carbon|null  $ab  Beginn der Betreuung. Liegt er in der Zukunft, ist der Lernende erst dann
     *                           sichtbar: der Text nennt das Datum und der Knopf führt zur Liste statt ins Cockpit.
     */
    public static function content(Lernender $lernender, ?Carbon $ab = null): MailContent
    {
        $name = trim($lernender->benutzer->vorname.' '.$lernender->benutzer->nachname);
        $beruf = $lernender->lehrberuf?->name;
        $kuenftig = $ab !== null && $ab->copy()->startOfDay()->gt(today());
        $datum = $kuenftig ? $ab->format('d.m.Y') : null;

        $zeile = match (true) {
            $kuenftig && $beruf !== null => __('Du betreust ab dem :datum :name («:beruf»).', ['datum' => $datum, 'name' => $name, 'beruf' => $beruf]),
            $kuenftig => __('Du betreust ab dem :datum :name.', ['datum' => $datum, 'name' => $name]),
            $beruf !== null => __('Du betreust ab sofort :name («:beruf»).', ['name' => $name, 'beruf' => $beruf]),
            default => __('Du betreust ab sofort :name.', ['name' => $name]),
        };

        return new MailContent(
            subject: __('Neue Betreuung: :name', ['name' => $name]),
            lines: [$zeile],
            facts: array_filter([
                __('Lehrberuf') => $beruf,
                __('Lehrbeginn') => $lernender->lehrbeginn?->format('d.m.Y'),
                __('Betreuung ab') => $datum,
            ]),
            actionLabel: $kuenftig ? __('Meine Lernenden') : __('Zum Cockpit'),
            actionUrl: $kuenftig ? route('trainer.learners.index') : route('trainer.learners.show', $lernender->lernender_id),
            digestTitle: __('Neue Betreuung: :name', ['name' => $name]),
        );
    }
}
