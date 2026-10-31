<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DigestItem;
use App\Models\User;
use App\Services\Notifications\MailContent;
use App\Services\Notifications\NotificationCatalog;
use App\Services\Notifications\Notifier;
use Illuminate\Console\Command;

/** Tageszusammenfassung: gesammelte Einträge je Benutzer als eine Mail, gruppiert nach Anlass. */
class SendDigests extends Command
{
    protected $signature = 'notifications:digest';

    protected $description = 'Tageszusammenfassungen verschicken';

    public function handle(): int
    {
        $anzahl = 0;
        DigestItem::query()->whereNull('sent_at')->orderBy('created_at')->get()->groupBy('user_id')
            ->each(function ($eintraege, $userId) use (&$anzahl) {
                $user = User::find($userId);
                // Anlässe, die seit dem Eintrag ausgeschaltet oder abbestellt wurden, gehören nicht mehr in die Mail
                $zustellbar = $user ? $eintraege->filter(fn (DigestItem $i) => ! NotificationCatalog::exists($i->type)
                    || (NotificationCatalog::policy($i->type)['enabled'] && Notifier::frequencyFor($user, $i->type) !== NotificationCatalog::NEVER)) : $eintraege;
                if ($user && $user->aktiv && $zustellbar->isNotEmpty()) {
                    // In der Sprache des Empfängers bauen (Gruppenlabel aus dem Katalog, Betreff, Titel)
                    Notifier::dispatch($user, NotificationCatalog::DAILY_DIGEST, function () use ($zustellbar) {
                        $items = $zustellbar->map(fn (DigestItem $i) => [
                            'group' => NotificationCatalog::exists($i->type) ? NotificationCatalog::get($i->type)['label'] : '',
                            'title' => $i->title,
                            'text' => $i->body,
                            'url' => $i->url,
                        ])->sortBy('group')->values()->all();
                        $n = count($items);

                        return new MailContent(
                            subject: $n === 1 ? __('Neu im Notenportal: :titel', ['titel' => $items[0]['title']]) : __(':anzahl Neuigkeiten im Notenportal', ['anzahl' => $n]),
                            lines: [__('Das ist seit der letzten Zusammenfassung passiert:')],
                            actionLabel: __('Notenportal öffnen'),
                            actionUrl: route('dashboard'),
                            preheader: collect($items)->pluck('title')->take(3)->implode(' · '),
                            title: __('Deine Zusammenfassung vom :datum', ['datum' => now()->format('d.m.Y')]),
                            items: $items,
                        );
                    });
                    $anzahl++;
                }
                DigestItem::whereKey($eintraege->pluck('id'))->update(['sent_at' => now()]);
            });

        $this->info("{$anzahl} Zusammenfassungen verschickt.");

        return self::SUCCESS;
    }
}
