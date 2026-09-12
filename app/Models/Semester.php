<?php

namespace App\Models;

use App\Services\Auswertung\Konfiguration;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

#[Table(name: 'semester', key: 'semester_id')]
#[WithoutTimestamps]
class Semester extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'start_datum' => 'date',
            'end_datum' => 'date',
        ];
    }

    /**
     * Neutraler Name ohne Bezug auf einen Lernenden, z. B. «HS 2026/27» oder «FS 2027»
     * (Englisch «Autumn 2026» / «Spring 2027»), aus dem Startdatum. Für Listen mit
     * mehreren Lernenden, bei denen dasselbe Semester je nach Lehrbeginn eine andere
     * Semesternummer hätte.
     */
    public function anzeigeName(): string
    {
        return self::neutralerName($this->start_datum) ?? $this->bezeichnung;
    }

    /**
     * Die Jahreszeit (Herbst/Frühling) ergibt sich aus der chronologischen Position im
     * Semesterplan (1., 3., 5. … Semester = Herbst, 2., 4., 6. … = Frühling), nicht aus einer
     * festen Kalendermonatsgrenze – Semesterstarts sind frei konfigurierbar
     * (App\Support\Einrichtung::semesterPlan()), eine feste Grenze klassifiziert bei
     * abweichender Planung sonst falsch. Kollidiert der so gebildete Name trotzdem mit einem
     * anderen Semester (z. B. mehr als zwei Semester pro Jahr), wird das Startdatum ergänzt –
     * der neutrale Name bleibt dadurch garantiert eindeutig.
     *
     * @param  Carbon|string|null  $start
     */
    public static function neutralerName($start): ?string
    {
        if ($start === null) {
            return null;
        }

        $start = $start instanceof Carbon ? $start : Carbon::parse($start);

        return self::neutralerPlan()[$start->toDateString()] ?? null;
    }

    /**
     * Startdatum (Y-m-d) => eindeutiger neutraler Name, für alle Semester der aktuellen
     * Konfiguration. Nutzt denselben Zwischenspeicher wie Konfiguration::ausDb() und wird mit
     * diesem invalidiert (siehe Konfiguration::vergessen()) – kein eigener Cache nötig.
     *
     * @return array<string, string>
     */
    private static function neutralerPlan(): array
    {
        $reihen = collect(Konfiguration::ausDb()->semester)->sortBy('start')->values();

        $namen = [];
        $vergeben = [];
        foreach ($reihen as $i => $s) {
            $start = Carbon::parse($s['start']);
            $basis = $i % 2 === 0
                ? __('HS :a/:b', ['a' => $start->format('Y'), 'b' => $start->copy()->addYear()->format('y')])
                : __('FS :jahr', ['jahr' => $start->format('Y')]);
            $namen[$start->toDateString()] = isset($vergeben[$basis]) ? $basis.' ('.$start->format('d.m.Y').')' : $basis;
            $vergeben[$basis] = true;
        }

        return $namen;
    }
}
