<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Benutzer\Startpasswort;
use App\Support\Einrichtung;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Installation: legt das erste Admin-Konto mit Startpasswort an und öffnet den Einrichtungsassistenten.
 * Mit --zuruecksetzen erhält der erste Admin ein neues Startpasswort (Zugang verloren).
 */
#[Description('Erstes Admin-Konto mit Startpasswort anlegen')]
#[Signature('notenportal:erstes-admin-konto {--email=admin@notenportal.local} {--zuruecksetzen : Startpasswort des ersten Admins neu setzen}')]
class ErstesAdminKonto extends Command
{
    public function handle(): int
    {
        $rolleId = DB::table('rollen')->where('name', 'Admin')->value('rolle_id');
        if (! $rolleId) {
            $this->error('Rollen fehlen – zuerst «php artisan db:seed --force» ausführen.');

            return self::FAILURE;
        }

        $admin = User::query()
            ->whereHas('rollen', fn ($q) => $q->where('rollen.rolle_id', $rolleId))
            ->orderBy('benutzer_id')
            ->first();

        if ($admin && ! $this->option('zuruecksetzen')) {
            $this->line('Admin-Konto vorhanden: '.$admin->email);

            return self::SUCCESS;
        }

        $passwort = Startpasswort::erzeugen(14);

        if ($admin) {
            $admin->update(['passwort_hash' => $passwort, 'passwort_wechsel_noetig' => true, 'aktiv' => true]);
        } else {
            $admin = DB::transaction(function () use ($passwort, $rolleId) {
                $user = User::create([
                    'vorname' => 'Admin',
                    'nachname' => 'Notenportal',
                    'email' => (string) $this->option('email'),
                    'benutzername' => Einrichtung::benutzername('admin', ''),
                    'passwort_hash' => $passwort,
                    'passwort_wechsel_noetig' => true,
                    'aktiv' => true,
                ]);
                $user->rollen()->attach((int) $rolleId);

                return $user;
            });
            Einrichtung::oeffnen();
        }

        $this->line('E-Mail:        '.$admin->email);
        $this->line('Startpasswort: '.$passwort);

        return self::SUCCESS;
    }
}
