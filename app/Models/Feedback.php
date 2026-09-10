<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Feedback extends Model
{
    use HasFactory;

    protected $table = 'feedback';
    protected $primaryKey = 'feedback_id';

    public const CREATED_AT = 'erstellt_am';
    public const UPDATED_AT = 'aktualisiert_am';

    public const KATEGORIE_FEEDBACK = 'feedback';
    public const KATEGORIE_IDEE = 'idee';
    public const KATEGORIE_BUG = 'bug';

    public const KATEGORIEN = [
        self::KATEGORIE_FEEDBACK => 'Feedback',
        self::KATEGORIE_IDEE => 'Idee',
        self::KATEGORIE_BUG => 'Bug',
    ];

    public const STATUS_OFFEN = 'offen';
    public const STATUS_IN_ARBEIT = 'in_arbeit';
    public const STATUS_ERLEDIGT = 'erledigt';

    public const STATUS = [
        self::STATUS_OFFEN => 'Offen',
        self::STATUS_IN_ARBEIT => 'In Arbeit',
        self::STATUS_ERLEDIGT => 'Erledigt',
    ];

    protected $fillable = [
        'benutzer_id',
        'kategorie',
        'text',
        'route_name',
        'url',
        'user_agent',
        'viewport',
        'status',
        'admin_notiz',
        'erledigt_am',
    ];

    protected function casts(): array
    {
        return [
            'erstellt_am' => 'datetime',
            'aktualisiert_am' => 'datetime',
            'erledigt_am' => 'datetime',
        ];
    }

    public function benutzer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'benutzer_id', 'benutzer_id');
    }
}
