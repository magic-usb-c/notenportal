<?php

declare(strict_types=1);

namespace Tests\Feature\I18n;

use App\Models\Dokument;
use App\Models\Feedback;
use App\Models\MailLog;
use App\Services\Betrieb\SicherungKopie;
use App\Services\Notifications\MailSettings;
use App\Services\Notifications\NotificationCatalog;
use App\Support\Darstellung;
use App\Support\Protokoll;
use Symfony\Component\Finder\Finder;

/**
 * Sammelt literale Übersetzungsschlüssel (__('…'), @lang('…'), trans('…'), np.t('…')/t('…') in JS)
 * aus app/ und resources/ und liest die EN-Übersetzungen (lang/en.json, lang/areas/*\/en.json).
 */
final class Schluessel
{
    private static function basis(string $pfad = ''): string
    {
        return rtrim((string) realpath(dirname(__DIR__, 3)).'/'.$pfad, '/');
    }

    /** Schlüssel aus PHP-Sprachdateien (validation.required, passwords.sent) – keine JSON-Schlüssel. */
    public static function istGruppenSchluessel(string $schluessel): bool
    {
        return (bool) preg_match('/^[a-z0-9_-]+(\.[a-z0-9_-]+)+$/', $schluessel);
    }

    /** @return array<string, list<string>> Schlüssel => Dateien (relativ) */
    public static function verwendet(): array
    {
        $php = '/(?<![\w$>:])(?:__|@lang|trans)\(\s*(?:\'((?:[^\'\\\\]|\\\\.)*)\'|"((?:[^"\\\\$]|\\\\.)*)")\s*[,)]/';
        $js = '/(?<![\w$])(?:np\.)?t\(\s*(?:\'((?:[^\'\\\\]|\\\\.)*)\'|"((?:[^"\\\\]|\\\\.)*)")\s*[,)]/';
        $funde = [];

        $finder = (new Finder)->files()->in([self::basis('app'), self::basis('resources')])->name(['*.php', '*.js']);
        foreach ($finder as $datei) {
            $relativ = str_replace('\\', '/', substr($datei->getRealPath(), strlen(self::basis()) + 1));
            $inhalt = $datei->getContents();
            $muster = str_ends_with($relativ, '.js') ? [$js] : [$php, '/(?<![\w$])np\.t\(\s*\'((?:[^\'\\\\]|\\\\.)*)\'()\s*[,)]/'];
            foreach ($muster as $regex) {
                preg_match_all($regex, $inhalt, $treffer, PREG_SET_ORDER);
                foreach ($treffer as $t) {
                    $schluessel = ($t[1] ?? '') !== '' ? stripslashes($t[1]) : stripslashes($t[2] ?? '');
                    if ($schluessel !== '' && ! self::istGruppenSchluessel($schluessel)) {
                        $funde[$schluessel][$relativ] = true;
                    }
                }
            }
        }

        return array_map('array_keys', $funde);
    }

    /** @return array<string, list<string>> np.t-Schlüssel aus resources/js */
    public static function javascript(): array
    {
        return array_filter(
            array_map(fn ($dateien) => array_values(array_filter($dateien, fn ($d) => str_starts_with($d, 'resources/js/'))), self::verwendet()),
            fn ($dateien) => $dateien !== [],
        );
    }

    /** Werte, die per __($variable) übersetzt werden und deshalb nicht literal im Code stehen. */
    public static function dynamisch(): array
    {
        return [
            ...array_values(Feedback::KATEGORIEN),
            ...array_values(Feedback::STATUS),
            ...array_values(Darstellung::AKZENTE),
            ...array_values(Protokoll::LABELS),
            ...array_values(Protokoll::DETAIL_LABELS),
            ...array_values(MailLog::STATUS),
            ...array_values(NotificationCatalog::FREQUENCIES),
            ...array_values(NotificationCatalog::GROUPS),
            ...array_values(SicherungKopie::ZIELE),
            ...array_values(MailSettings::ENCRYPTIONS),
            ...array_values(Dokument::ARTEN),
        ];
    }

    /** @return array<string, array<string, string>> Datei (relativ) => Übersetzungen */
    public static function dateien(): array
    {
        $dateien = ['lang/en.json' => self::basis('lang/en.json')];
        foreach (glob(self::basis('lang/areas/*/en.json')) as $pfad) {
            $dateien['lang/areas/'.basename(dirname($pfad)).'/en.json'] = $pfad;
        }

        return array_map(fn ($pfad) => json_decode((string) file_get_contents($pfad), true, flags: JSON_THROW_ON_ERROR), $dateien);
    }

    /** @return array<string, list<string>> */
    public static function offen(): array
    {
        return require __DIR__.'/offen.php';
    }

    public static function istOffen(string $datei): bool
    {
        foreach (self::offen() as $praefixe) {
            foreach ($praefixe as $praefix) {
                if (str_starts_with($datei, $praefix)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Übersetzungsdateien, die voll geprüft werden: gemeinsame Datei und Bereiche, die nicht mehr offen sind. */
    public static function geschlossen(): array
    {
        return array_filter(self::dateien(), function ($_, string $datei) {
            return $datei === 'lang/en.json' || ! array_key_exists(basename(dirname($datei)), self::offen());
        }, ARRAY_FILTER_USE_BOTH);
    }
}
