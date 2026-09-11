<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'benutzer_id',
    'rolle',
    'kategorie',
    'text',
    'route_name',
    'url',
    'user_agent',
    'browser',
    'viewport',
    'js_fehler',
    'screenshot_pfad',
    'screenshot_mime',
    'screenshot_groesse',
    'status',
    'admin_notiz',
    'erledigt_am',
])]
#[Table(name: 'feedback', key: 'feedback_id')]
class Feedback extends Model
{
    use HasFactory;

    public const CREATED_AT = 'erstellt_am';

    public const UPDATED_AT = 'aktualisiert_am';

    public const KATEGORIE_FEHLER = 'fehler';

    public const KATEGORIE_IDEE = 'idee';

    public const KATEGORIE_FRAGE = 'frage';

    public const KATEGORIE_LOB = 'lob';

    public const KATEGORIEN = [
        self::KATEGORIE_FEHLER => 'Fehler',
        self::KATEGORIE_IDEE => 'Idee',
        self::KATEGORIE_FRAGE => 'Frage',
        self::KATEGORIE_LOB => 'Lob',
    ];

    public const STATUS_OFFEN = 'offen';

    public const STATUS_IN_ARBEIT = 'in_arbeit';

    public const STATUS_ERLEDIGT = 'erledigt';

    public const STATUS = [
        self::STATUS_OFFEN => 'Offen',
        self::STATUS_IN_ARBEIT => 'In Arbeit',
        self::STATUS_ERLEDIGT => 'Erledigt',
    ];

    protected function casts(): array
    {
        return [
            'erstellt_am' => 'datetime',
            'aktualisiert_am' => 'datetime',
            'erledigt_am' => 'datetime',
            'js_fehler' => 'array',
        ];
    }

    public function benutzer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'benutzer_id', 'benutzer_id');
    }

    public function hatScreenshot(): bool
    {
        return filled($this->screenshot_pfad);
    }
}
