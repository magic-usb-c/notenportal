<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\Betreuung;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Empfängerkreise, die mehrere Auslöser brauchen: aktive Betreuer eines Lernenden, aktive Admins.
 */
final class Empfaenger
{
    /** @return Collection<int, User> Benutzer der Berufsbildner, die den Lernenden heute aktiv betreuen. */
    public static function aktiveBetreuer(int $lernenderId): Collection
    {
        $berufsbildnerIds = Betreuung::query()
            ->aktiv()
            ->where('lernender_id', $lernenderId)
            ->pluck('berufsbildner_id');

        if ($berufsbildnerIds->isEmpty()) {
            return new Collection;
        }

        return User::query()
            ->whereHas('berufsbildner', fn ($q) => $q->whereIn('berufsbildner_id', $berufsbildnerIds))
            ->where('aktiv', true)
            ->get();
    }

    /** @return Collection<int, User> aktive Admin-Benutzer. */
    public static function aktiveAdmins(): Collection
    {
        return User::query()
            ->where('aktiv', true)
            ->whereHas('rollen', fn ($q) => $q->whereRaw('LOWER(name) = ?', ['admin']))
            ->get();
    }
}
