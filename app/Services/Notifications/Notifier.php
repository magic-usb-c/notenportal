<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\DigestItem;
use App\Models\MailLog;
use App\Models\NotificationMark;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Notifications\PortalMail;
use Illuminate\Support\Facades\Notification;

/**
 * Einziger Weg, eine Benachrichtigung auszulösen:
 *   Notifier::send($user, NotificationCatalog::COMMENT_ADDED, new MailContent(...));
 * Prüft Admin-Regel und persönliche Wahl, legt sofortige Mails in die Queue (mit Protokoll)
 * oder sammelt sie für die Tageszusammenfassung. Wirft nie – ein Mailproblem darf keine Aktion abbrechen.
 */
final class Notifier
{
    /** Domains, die nie zustellbar sind (Test- und Demo-Konten). */
    private const array UNZUSTELLBAR = ['.local', '.example', '.test', '.invalid', '.localhost', '@example.com', '@example.org', '@example.net'];

    /** @return string|null gewählte Frequenz (immediate/daily) oder null, wenn nichts verschickt wird */
    public static function send(User $user, string $type, MailContent $content): ?string
    {
        try {
            if (! $user->aktiv || $user->trashed() || blank($user->email)) {
                return null;
            }
            if (! NotificationCatalog::policy($type)['enabled']) {
                return null;
            }

            $frequency = self::frequencyFor($user, $type);
            if ($frequency === NotificationCatalog::DAILY) {
                DigestItem::create([
                    'user_id' => $user->benutzer_id,
                    'type' => $type,
                    'title' => $content->digestTitle ?? $content->subject,
                    'body' => $content->digestText,
                    'url' => $content->actionUrl,
                ]);
            } elseif ($frequency === NotificationCatalog::IMMEDIATE) {
                self::dispatch($user, $type, $content);
            }

            return $frequency === NotificationCatalog::NEVER ? null : $frequency;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /** Verpflichtend → Admin-Frequenz; sonst persönliche Wahl (falls erlaubt), sonst Admin-Standard. */
    public static function frequencyFor(User $user, string $type): string
    {
        $policy = NotificationCatalog::policy($type);
        if ($policy['mandatory']) {
            return $policy['frequency'];
        }
        $wahl = NotificationPreference::query()
            ->where('user_id', $user->benutzer_id)->where('type', $type)->value('frequency');

        return $wahl !== null && in_array($wahl, NotificationCatalog::get($type)['frequencies'], true) ? $wahl : $policy['frequency'];
    }

    /**
     * Mail sofort in die Queue (oder mit $now synchron, z. B. Testmail). Legt immer einen Protokolleintrag an.
     */
    public static function dispatch(User|string $to, string $type, MailContent $content, bool $now = false): MailLog
    {
        $email = $to instanceof User ? (string) $to->email : $to;
        $log = MailLog::create([
            'user_id' => $to instanceof User ? $to->benutzer_id : null,
            'type' => $type,
            'recipient' => $email,
            'subject' => mb_substr($content->subject, 0, 250),
            'status' => MailLog::QUEUED,
            'payload' => $content->toArray(),
        ]);

        if (! self::deliverable($email) && ! MailSettings::redirectTo()) {
            $log->update(['status' => MailLog::SKIPPED, 'error' => 'Testadresse ohne Mail-Umleitung']);

            return $log;
        }

        $notification = new PortalMail($type, $content, $log->id);
        $notifiable = $to instanceof User ? $to : Notification::route('mail', $email);
        try {
            $now ? $notifiable->notifyNow($notification) : $notifiable->notify($notification);
        } catch (\Throwable $e) {
            $notification->failed($e);
            if (! $now) {
                report($e);
            }
        }

        return $log->refresh();
    }

    /** Fehlgeschlagene Mail mit gleichem Inhalt erneut in die Queue. */
    public static function retry(MailLog $log): MailLog
    {
        // Konto inzwischen deaktiviert oder gelöscht: nicht an die alte Adresse ausweichen
        $user = $log->user_id ? User::withTrashed()->find($log->user_id) : null;
        if ($log->user_id && (! $user || ! $user->aktiv || $user->trashed())) {
            throw new \RuntimeException('Das Konto ist deaktiviert oder gelöscht – kein erneuter Versand.');
        }

        return self::dispatch($user ?? $log->recipient, $log->type, MailContent::fromArray((array) $log->payload));
    }

    /** true beim ersten Aufruf je Benutzer/Anlass/Gegenstand, danach false. */
    public static function once(User $user, string $type, string $key): bool
    {
        return NotificationMark::query()->insertOrIgnore([
            'user_id' => $user->benutzer_id,
            'type' => $type,
            'subject_key' => mb_substr($key, 0, 191),
            'created_at' => now(),
        ]) > 0;
    }

    public static function deliverable(string $email): bool
    {
        $email = mb_strtolower(trim($email));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        foreach (self::UNZUSTELLBAR as $endung) {
            if (str_ends_with($email, $endung)) {
                return false;
            }
        }

        return true;
    }
}
