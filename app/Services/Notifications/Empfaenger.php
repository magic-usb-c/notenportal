<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\Betreuung;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * Empfängerkreise, die mehrere Auslöser brauchen: aktive Betreuer eines Lernenden, aktive Admins.
 */
final class Empfaenger
{
    /**
     * @param  CarbonInterface|null  $stichtag  Tag, an dem die Betreuung gelten muss (Standard: heute). Der Semesterabschluss
     *                                          läuft erst nach dem Semesterende und braucht dessen Datum.
     * @return Collection<int, User> Benutzer der Berufsbildner, die den Lernenden am Stichtag aktiv betreuen.
     */
    public static function aktiveBetreuer(int $lernenderId, ?CarbonInterface $stichtag = null): Collection
    {
        $betreuungen = Betreuung::query()->where('lernender_id', $lernenderId);
        if ($stichtag === null) {
            $betreuungen->aktiv();
        } else {
            $tag = $stichtag->toDateString();
            $betreuungen->where('gueltig_von', '<=', $tag)
                ->where(fn ($q) => $q->whereNull('gueltig_bis')->orWhere('gueltig_bis', '>=', $tag));
        }
        $berufsbildnerIds = $betreuungen->pluck('berufsbildner_id');

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
