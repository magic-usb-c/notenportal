<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\MailLog;
use App\Models\User;
use App\Services\Notifications\MailContent;
use App\Services\Notifications\MailSettings;
use App\Services\Notifications\NotificationCatalog;
use App\Support\Betriebslogo;
use App\Support\Einstellungen;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;
use Symfony\Component\Mime\Email;
use Throwable;

/**
 * Alle Mails des Portals: ein Layout (resources/views/mail/portal*.blade.php), Inhalt aus MailContent.
 * Nicht direkt verwenden – immer über Notifier::send()/dispatch().
 */
class PortalMail extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public string $type, public MailContent $content, public ?int $logId = null)
    {
        $this->afterCommit();
    }

    /** @return list<int> Sekunden bis zum nächsten Versuch */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        MailSettings::apply();
        if ($this->logId) {
            MailLog::whereKey($this->logId)->increment('attempts');
        }

        $user = $notifiable instanceof User ? $notifiable : null;
        $katalog = NotificationCatalog::exists($this->type) ? NotificationCatalog::get($this->type) : null;
        $abmelden = $user && $katalog && ! NotificationCatalog::policy($this->type)['mandatory']
            ? URL::signedRoute('notifications.unsubscribe', ['user' => $user->benutzer_id, 'type' => $this->type])
            : null;

        return (new MailMessage)
            ->subject($this->content->subject)
            ->view(['html' => 'mail.portal', 'text' => 'mail.portal-text'], [
                'c' => $this->content,
                'vorname' => $user?->vorname,
                'anlass' => $katalog['label'] ?? null,
                'abmeldenUrl' => $abmelden,
                'einstellungenUrl' => $user ? route('notifications.settings') : null,
                'betrieb' => (string) Einstellungen::get(Einstellungen::BETRIEB_NAME, ''),
                'logoUrl' => Betriebslogo::url(),
                'portalUrl' => url('/'),
            ])
            ->withSymfonyMessage(function (Email $message) use ($abmelden) {
                $message->getHeaders()->addTextHeader('X-Notenportal-Log', (string) $this->logId);
                if ($abmelden) {
                    $message->getHeaders()->addTextHeader('List-Unsubscribe', '<'.$abmelden.'>');
                    $message->getHeaders()->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
                }
            });
    }

    public function failed(Throwable $e): void
    {
        if ($this->logId) {
            MailLog::whereKey($this->logId)->update([
                'status' => MailLog::FAILED,
                'error' => mb_substr($e->getMessage(), 0, 1000),
                'failed_at' => now(),
            ]);
        }
    }
}
