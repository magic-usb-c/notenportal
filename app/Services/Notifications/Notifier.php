<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\DigestItem;
use App\Models\MailLog;
use App\Models\NotificationMark;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Notifications\PortalMail;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

/**
 * Einziger Weg, eine Benachrichtigung auszulösen:
 *   Notifier::send($user, NotificationCatalog::COMMENT_ADDED, fn () => CommentAdded::content(...));
 * Den Inhalt als Closure übergeben: er wird in der Sprache des Empfängers gebaut (User::preferredLocale()),
 * weil Betreff, Mailtext und Eintrag der Tageszusammenfassung als fertige Strings gespeichert werden.
 * Ein fertiges MailContent geht weiterhin, bleibt dann aber in der Sprache, in der es gebaut wurde.
 * Prüft Admin-Regel und persönliche Wahl, legt sofortige Mails in die Queue (mit Protokoll)
 * oder sammelt sie für die Tageszusammenfassung. Wirft nie – ein Mailproblem darf keine Aktion abbrechen.
 */
final class Notifier
{
    /** Code der RuntimeException aus retry(), wenn die Adresse nicht zustellbar ist. */
    public const int NICHT_ZUSTELLBAR = 1;

    /** Domains, die nie zustellbar sind (Test- und Demo-Konten). */
    private const array UNZUSTELLBAR = ['.local', '.example', '.test', '.invalid', '.localhost', '@example.com', '@example.org', '@example.net'];

    /** @return string|null gewählte Frequenz (immediate/daily) oder null, wenn nichts verschickt wird */
    /** @param  MailContent|Closure(): MailContent  $content */
    public static function send(User $user, string $type, MailContent|Closure $content): ?string
    {
        try {
            if (! $user->aktiv || $user->trashed() || blank($user->email)) {
                return null;
            }
            if (! NotificationCatalog::policy($type)['enabled']) {
                return null;
            }

            $frequency = self::frequencyFor($user, $type);
            if ($frequency === NotificationCatalog::NEVER) {
                return null;
            }
            $content = self::inhalt($user, $content);
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

    /** Baut den Inhalt in der Sprache des Empfängers (Closure) oder nimmt ihn, wie er ist. */
    public static function inhalt(User $user, MailContent|Closure $content): MailContent
    {
        if (! $content instanceof Closure) {
            return $content;
        }
        $vorher = App::getLocale();
        App::setLocale($user->preferredLocale());
        Carbon::setLocale(App::getLocale());
        try {
            return $content();
        } finally {
            App::setLocale($vorher);
            Carbon::setLocale($vorher);
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
     *
     * @param  MailContent|Closure(): MailContent  $content
     */
    public static function dispatch(User|string $to, string $type, MailContent|Closure $content, bool $now = false): MailLog
    {
        $content = $to instanceof User ? self::inhalt($to, $content) : ($content instanceof Closure ? $content() : $content);
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
            throw new \RuntimeException(__('Das Konto ist deaktiviert oder gelöscht – kein erneuter Versand.'));
        }
        // Testadresse ohne Umleitung: gar nicht erst eine weitere «Nicht zustellbar»-Zeile anlegen
        if (! self::deliverable((string) ($user?->email ?? $log->recipient)) && ! MailSettings::redirectTo()) {
            throw new \RuntimeException('Adresse nicht zustellbar', self::NICHT_ZUSTELLBAR);
        }

        // Der Link steckt fertig im gespeicherten Inhalt: abgelaufen oder eingelöst → nicht erneut verschicken
        if (in_array($log->type, [NotificationCatalog::PASSWORD_RESET, NotificationCatalog::ACCOUNT_CREATED], true)
            && ! self::linkNochGueltig($user, (array) $log->payload)) {
            throw new \RuntimeException(__('Der Link ist abgelaufen – Passwort im Benutzerkonto neu zurücksetzen.'));
        }

        $neu = self::dispatch($user ?? $log->recipient, $log->type, MailContent::fromArray((array) $log->payload));
        self::fehlgeschlageneJobsAbraeumen($log->id);

        return $neu;
    }

    /** Token aus dem gespeicherten Link der Passwort-/Konto-Mail: in einem der beiden Broker («users», «invites») noch gültig? */
    private static function linkNochGueltig(?User $user, array $payload): bool
    {
        $url = $payload['actionUrl'] ?? null;
        $token = is_string($url) ? rawurldecode(basename((string) parse_url($url, PHP_URL_PATH))) : '';
        if ($user === null || $token === '') {
            return false;
        }

        return Password::broker('users')->tokenExists($user, $token)
            || Password::broker('invites')->tokenExists($user, $token);
    }

    /**
     * Entfernt den fehlgeschlagenen Queue-Job einer Protokollzeile, deren Mail neu gesendet wurde –
     * sonst bleibt «Fehlgeschlagene Jobs» (Übersicht, Bereitschaft) dauerhaft rot.
     * Zuordnung über die logId im serialisierten PortalMail; Wirft nie.
     */
    private static function fehlgeschlageneJobsAbraeumen(int $logId): void
    {
        try {
            $muster = '/s:5:"logId";i:'.$logId.';/';
            $ids = [];
            foreach (DB::table('failed_jobs')->select('id', 'payload')->orderByDesc('id')->limit(500)->get() as $job) {
                $befehl = (string) (json_decode((string) $job->payload, true)['data']['command'] ?? '');
                if ($befehl !== '' && preg_match($muster, $befehl) === 1) {
                    $ids[] = $job->id;
                }
            }
            if ($ids !== []) {
                DB::table('failed_jobs')->whereIn('id', $ids)->delete();
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** true beim ersten Aufruf je Benutzer/Anlass/Gegenstand, danach false. */
    public static function once(User $user, string $type, string $key): bool
    {
        return NotificationMark::query()->insertOrIgnore([
            'user_id' => $user->benutzer_id,
            'type' => $type,
            'subject_key' => self::merkerSchluessel($key),
            'created_at' => now(),
        ]) > 0;
    }

    /** Würde send() diesem Empfänger überhaupt etwas zustellen (Konto, Admin-Richtlinie, persönliche Wahl)? Für Vorschauen. */
    public static function wuerdeZustellen(User $user, string $type): bool
    {
        return $user->aktiv && ! $user->trashed() && ! blank($user->email)
            && NotificationCatalog::policy($type)['enabled']
            && self::frequencyFor($user, $type) !== NotificationCatalog::NEVER;
    }

    /** Wie once(), setzt aber keinen Merker: true, wenn der Fall schon gemeldet wurde (Vorschau von notifications:check). */
    public static function bereitsGemeldet(User $user, string $type, string $key): bool
    {
        return NotificationMark::query()
            ->where('user_id', $user->benutzer_id)
            ->where('type', $type)
            ->where('subject_key', self::merkerSchluessel($key))
            ->exists();
    }

    private static function merkerSchluessel(string $key): string
    {
        return mb_substr($key, 0, 191);
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
