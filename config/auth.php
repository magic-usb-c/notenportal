<?php

/*
 * Nur Ergänzungen zum Laravel-Standard (der Rest wird automatisch aus dem
 * Framework gemischt, siehe LoadConfiguration::mergeableOptions): ein zweiter
 * Passwort-Broker für Konto-Einladungen (Konto eröffnet, Admin-Reset), gültig
 * 7 Tage statt 60 Minuten wie der normale «Passwort vergessen»-Link.
 */
return [
    'passwords' => [
        'invites' => [
            'provider' => 'users',
            'table' => 'password_invite_tokens',
            'expire' => 10080,
            'throttle' => 60,
        ],
    ],
];
