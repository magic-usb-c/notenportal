<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Whitelist für die Filter der Statistikseiten (?zeitraum=…&kategorie=…): unbekannte Schlüssel werden
 * ignoriert, unbekannte oder doppelte Werte fallen auf den Standard – nie ein Fehler.
 *
 * Regeln je Schlüssel: Liste erlaubter Werte (['semester', 'lehrjahr', 'alles']) oder erlaubte IDs
 * (['id' => [1, 2, 3]]); IDs kommen als int zurück. Die Menge der IDs bestimmt der Aufrufer aus dem,
 * was die Person sehen darf – hier wird nur dagegen geprüft.
 */
final class StatistikFilter
{
    /**
     * @param  array<string, mixed>  $werte
     * @param  array<string, mixed>  $standard
     */
    private function __construct(private readonly array $werte, private readonly array $standard) {}

    /**
     * @param  array<string, list<int|string>|array{id: list<int>}>  $regeln
     * @param  array<string, mixed>  $standard
     */
    public static function aus(Request $request, array $regeln, array $standard = []): self
    {
        $werte = [];
        foreach ($regeln as $schluessel => $regel) {
            $roh = $request->query((string) $schluessel);
            $werte[$schluessel] = self::pruefen($roh, $regel) ?? ($standard[$schluessel] ?? null);
        }

        return new self($werte, $standard);
    }

    public function wert(string $schluessel): mixed
    {
        return $this->werte[$schluessel] ?? null;
    }

    /** Nur, was vom Standard abweicht: kurze Adressen für Links und history.replaceState. */
    public function query(): array
    {
        return array_filter(
            $this->werte,
            fn ($wert, $schluessel) => $wert !== null && $wert !== ($this->standard[$schluessel] ?? null),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /** Alle Filter mit ihrem wirksamen Wert (Standard eingesetzt). */
    public function toArray(): array
    {
        return $this->werte;
    }

    /** @param  list<int|string>|array{id: list<int>}  $regel */
    private static function pruefen(mixed $roh, array $regel): int|string|null
    {
        if (! is_string($roh) || $roh === '') {
            return null;
        }

        if (array_key_exists('id', $regel)) {
            if (! preg_match('/^[1-9][0-9]{0,18}$/', $roh)) {
                return null;
            }
            $id = (int) $roh;

            return in_array($id, array_map('intval', $regel['id']), true) ? $id : null;
        }

        foreach ($regel as $erlaubt) {
            if ((string) $erlaubt === $roh) {
                return $erlaubt;
            }
        }

        return null;
    }
}
