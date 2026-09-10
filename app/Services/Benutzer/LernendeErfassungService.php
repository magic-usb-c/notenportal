<?php

declare(strict_types=1);

namespace App\Services\Benutzer;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Zentrale Logik zum Anlegen eines Lernenden-Accounts.
 *
 * Wird vom Admin (Benutzerverwaltung) und vom Berufsbildner
 * (eigene Lernende erfassen) gemeinsam genutzt, damit die
 * Insert-Kette (benutzer -> rolle -> lernende -> betreuung -> track)
 * nur an einer Stelle existiert.
 */
class LernendeErfassungService
{
    /**
     * Legt Benutzer + Lernender-Profil an (in einer Transaktion).
     *
     * Erwartete $data-Keys (bereits validiert):
     *   vorname, nachname, email, benutzername, passwort,
     *   lehrberuf_id, lehrbeginn, lehrende?,
     *   track_typ?, track_semester_id?
     *
     * @param  int|null  $berufsbildnerId  Wenn gesetzt: Betreuung ab Lehrbeginn anlegen.
     * @return int  lernender_id des neu angelegten Profils
     */
    public function erstellen(array $data, ?int $berufsbildnerId = null): int
    {
        return (int) DB::transaction(function () use ($data, $berufsbildnerId) {
            // 1) Benutzer-Account
            $user = User::create([
                'vorname'       => $data['vorname'],
                'nachname'      => $data['nachname'],
                'email'         => $data['email'],
                'benutzername'  => $data['benutzername'],
                'passwort_hash' => $data['passwort'],
                'passwort_wechsel_noetig' => true,
                'aktiv'         => true,
            ]);

            $benutzerId = (int) $user->benutzer_id;

            // 2) Rolle "Lernender" zuweisen (ID dynamisch, nicht hardcodiert)
            $rolleId = (int) DB::table('rollen')->where('name', 'Lernender')->value('rolle_id');

            DB::table('benutzer_rollen')->insert([
                'benutzer_id' => $benutzerId,
                'rolle_id'    => $rolleId,
            ]);

            // 3) Lernenden-Profil
            $lernenderId = (int) DB::table('lernende')->insertGetId([
                'benutzer_id'     => $benutzerId,
                'lehrberuf_id'    => (int) $data['lehrberuf_id'],
                'lehrbeginn'      => $data['lehrbeginn'],
                'lehrende'        => $data['lehrende'] ?? null,
                'erstellt_am'     => now(),
                'aktualisiert_am' => now(),
            ]);

            // 4) Optional: Betreuung. Gilt ab heute bzw. ab Lehrbeginn, falls dieser
            //    früher liegt — sonst wäre ein Lernender mit künftigem Lehrbeginn
            //    für den BB bis zum Lehrstart unsichtbar.
            if ($berufsbildnerId) {
                $gueltigVon = min(now()->toDateString(), (string) $data['lehrbeginn']);

                DB::table('betreuungen')->insert([
                    'lernender_id'     => $lernenderId,
                    'berufsbildner_id' => $berufsbildnerId,
                    'gueltig_von'      => $gueltigVon,
                    'gueltig_bis'      => null,
                ]);
            }

            // 5) Optional: BMS/ABU-Track ab Lehrbeginn
            if (!empty($data['track_typ'])) {
                DB::table('lernender_tracks')->insert([
                    'lernender_id'      => $lernenderId,
                    'track_typ'         => $data['track_typ'],
                    'start_datum'       => $data['lehrbeginn'],
                    'end_datum'         => null,
                    'start_semester_id' => (int) $data['track_semester_id'],
                    'end_semester_id'   => null,
                ]);
            }

            return $lernenderId;
        });
    }
}
