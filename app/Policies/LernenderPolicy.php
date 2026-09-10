<?php

namespace App\Policies;

use App\Models\Lernender;
use App\Models\User;

/**
 * Berechtigungen auf Lernende. Sichtbarkeit selbst regelt Lernender::sichtbarFuer();
 * Controller laden Lernende ausschliesslich darüber (sonst 404).
 */
class LernenderPolicy
{
    public function view(User $user, Lernender $lernender): bool
    {
        return Lernender::sichtbarFuer($user)->whereKey($lernender->getKey())->exists();
    }

    public function update(User $user, Lernender $lernender): bool
    {
        return $this->view($user, $lernender);
    }

    /** Konto (aktiv, Passwort), Tracks. */
    public function verwalten(User $user, Lernender $lernender): bool
    {
        return $this->view($user, $lernender);
    }

    public function betreuungVerwalten(User $user, Lernender $lernender): bool
    {
        return $this->view($user, $lernender);
    }

    /** Noten legen Lernende selbst an; Admin darf nachtragen. */
    public function noteAnlegen(User $user, Lernender $lernender): bool
    {
        return $user->hasRole('Admin') && $this->view($user, $lernender);
    }

    public function noteKorrigieren(User $user, Lernender $lernender): bool
    {
        return $this->view($user, $lernender);
    }

    public function noteLoeschen(User $user, Lernender $lernender): bool
    {
        return $user->hasRole('Admin') && $this->view($user, $lernender);
    }
}
