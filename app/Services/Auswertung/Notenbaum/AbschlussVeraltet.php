<?php

declare(strict_types=1);

namespace App\Services\Auswertung\Notenbaum;

use RuntimeException;

/**
 * Der Notenbaum wurde zwischen Formular und Speichern gewechselt: die Knoten des Formulars gehören zu
 * einem abgelösten Baum. Die Controller zeigen die Meldung als Fehler-Toast.
 */
class AbschlussVeraltet extends RuntimeException
{
    public function __construct()
    {
        parent::__construct(__('Der Abschluss wurde eben umgestellt. Bitte die Seite neu laden und nochmals speichern.'));
    }
}
