<?php

namespace Tests\Feature\Verwaltung;

use App\Models\Betreuung;
use App\Models\Lernender;
use App\Models\LernenderTrack;
use App\Models\User;

trait VerwaltungTestHilfen
{
    /** @return array<string, array{0: string}> */
    public static function verwalterRollen(): array
    {
        return [
            'Admin' => ['admin'],
            'Berufsbildner' => ['trainer'],
        ];
    }

    protected function neuerLernender(array $benutzer = [], array $lernender = []): Lernender
    {
        return User::factory()->lernender($lernender)->create($benutzer)->lernender;
    }

    /** @param  'aktiv'|'abgelaufen'|'zukuenftig'  $zustand */
    protected function betreue(User $bb, Lernender $lernender, string $zustand = 'aktiv'): Betreuung
    {
        $factory = match ($zustand) {
            'abgelaufen' => Betreuung::factory()->abgelaufen(),
            'zukuenftig' => Betreuung::factory()->zukuenftig(),
            default => Betreuung::factory(),
        };

        return $factory->create([
            'berufsbildner_id' => $bb->berufsbildner->berufsbildner_id,
            'lernender_id' => $lernender->lernender_id,
        ]);
    }

    /** Admin oder BB, der den Lernenden aktiv betreut. */
    protected function verwalter(string $rolle, Lernender $lernender): User
    {
        $user = User::factory()->{$rolle === 'trainer' ? 'berufsbildner' : $rolle}()->create();

        if ($rolle === 'trainer') {
            $this->betreue($user, $lernender);
        }

        return $user;
    }

    protected function bmsTrack(Lernender $lernender, int $semesterId): LernenderTrack
    {
        return LernenderTrack::create([
            'lernender_id' => $lernender->lernender_id,
            'track_typ' => 'BMS',
            'start_datum' => '2024-08-01',
            'start_semester_id' => $semesterId,
        ]);
    }
}
