<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Auswertung\Zielgroesse;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/** Zielwert eines Lernenden für Gesamtschnitt, Kategorie, Fach (Lehrzeit) oder Modul. */
#[Fillable(['lernender_id', 'ebene', 'kategorie_id', 'fach_id', 'modul_id', 'zielwert'])]
#[Table(name: 'ziele', key: 'ziel_id')]
class Ziel extends Model
{
    public const CREATED_AT = 'erstellt_am';

    public const UPDATED_AT = 'aktualisiert_am';

    /** Ebenen, die sich als Ziel speichern lassen (ohne Semesterbezug). */
    public const array EBENEN = ['gesamt', 'kategorie', 'fach', 'modul'];

    public function zielgroesse(): Zielgroesse
    {
        return new Zielgroesse($this->ebene, match ($this->ebene) {
            'kategorie' => (int) $this->kategorie_id,
            'fach' => (int) $this->fach_id,
            'modul' => (int) $this->modul_id,
            default => null,
        });
    }

    /** @return array{ebene: string, kategorie_id: ?int, fach_id: ?int, modul_id: ?int} */
    public static function spaltenFuer(Zielgroesse $z): array
    {
        return [
            'ebene' => $z->ebene,
            'kategorie_id' => $z->ebene === 'kategorie' ? $z->id : null,
            'fach_id' => $z->ebene === 'fach' ? $z->id : null,
            'modul_id' => $z->ebene === 'modul' ? $z->id : null,
        ];
    }

    protected function casts(): array
    {
        return [
            'zielwert' => 'float',
            'erstellt_am' => 'datetime',
            'aktualisiert_am' => 'datetime',
        ];
    }
}
