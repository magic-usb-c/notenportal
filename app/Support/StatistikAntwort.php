<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Antworten der Statistikseiten (docs/auftrag/GUI-R6.md §6): dieselbe GET-Route liefert HTML oder mit
 * `Accept: application/json` das Paket {filter, diagramm, tabelle, zusammenfassung, meta}. Beide Antworten
 * tragen `Vary: Accept`, damit ein Zwischenspeicher JSON nicht für HTML ausliefert.
 *
 * Ein Teil (siehe StatistikDaten) hat die Form {schluessel, diagramm, tabelle, zusammenfassung, meta}. Mehrere
 * Statistiken einer Seite stehen unter ihrem Schlüssel nebeneinander (diagramm.verlauf, diagramm.wostehe …),
 * ein Filterformular und ein Ereignis je Route.
 */
final class StatistikAntwort
{
    /**
     * @param  array{schluessel: string, diagramm: mixed, tabelle: array{spalten: list<string>, zeilen: list<list<string>>}, zusammenfassung: string, meta: array<string, mixed>}  ...$teile
     * @return array{filter: array<string, mixed>, diagramm: array<string, mixed>, tabelle: array<string, mixed>, zusammenfassung: array<string, string>, meta: array<string, mixed>}
     */
    public static function paket(StatistikFilter $filter, array ...$teile): array
    {
        $paket = ['filter' => $filter->toArray(), 'diagramm' => [], 'tabelle' => [], 'zusammenfassung' => [], 'meta' => []];
        foreach ($teile as $teil) {
            $paket['diagramm'][$teil['schluessel']] = $teil['diagramm'];
            $paket['tabelle'][$teil['schluessel']] = $teil['tabelle'];
            $paket['zusammenfassung'][$teil['schluessel']] = $teil['zusammenfassung'];
            $paket['meta'][$teil['schluessel']] = $teil['meta'];
        }

        return $paket;
    }

    /** @param  array<string, mixed>  $paket */
    public static function json(array $paket): JsonResponse
    {
        return response()->json($paket)
            ->header('Vary', 'Accept')
            ->header('Cache-Control', 'private, no-store');
    }

    /** @param  array<string, mixed>  $daten */
    public static function view(string $view, array $daten): Response
    {
        return response()->view($view, $daten)->header('Vary', 'Accept');
    }
}
