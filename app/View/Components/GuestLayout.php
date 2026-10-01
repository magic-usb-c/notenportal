<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Rahmen der Seiten ohne Anmeldung: App-Symbol, Titel und Untertitel über einer Karte.
 * Ohne Titel steht der App-Name als Überschrift (Anmeldeseite), mit dem Betrieb darunter.
 */
class GuestLayout extends Component
{
    public function __construct(
        public ?string $titel = null,
        public ?string $text = null,
    ) {}

    public function render(): View
    {
        return view('layouts.guest');
    }
}
