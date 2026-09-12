<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Feedback\Anhang;
use App\Services\Feedback\Screenshot;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

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
    'technik_details',
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

    /** Veraltet (vor Migration 2026_09_12_000012): siehe KATEGORIE_SONSTIGES und kategorieLabel(). */
    public const KATEGORIE_LOB = 'lob';

    public const KATEGORIE_SONSTIGES = 'sonstiges';

    public const KATEGORIEN = [
        self::KATEGORIE_FEHLER => 'Fehler',
        self::KATEGORIE_IDEE => 'Idee',
        self::KATEGORIE_FRAGE => 'Frage',
        self::KATEGORIE_SONSTIGES => 'Sonstiges',
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
            'technik_details' => 'array',
        ];
    }

    private static ?bool $hatDuplikatSpalte = null;

    private static ?bool $hatTechnikSpalte = null;

    private static ?bool $hatAnhaengeTabelle = null;

    private static ?bool $hatSonstigesWert = null;

    public function benutzer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'benutzer_id', 'benutzer_id');
    }

    public function stimmen(): HasMany
    {
        return $this->hasMany(FeedbackStimme::class, 'feedback_id', 'feedback_id');
    }

    public function anhaenge(): HasMany
    {
        return $this->hasMany(FeedbackAnhang::class, 'feedback_id', 'feedback_id');
    }

    /** Original, wenn diese Meldung ein Duplikat ist. */
    public function original(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplikat_von', 'feedback_id');
    }

    /** Meldungen, die auf diese als Duplikat zeigen. */
    public function duplikate(): HasMany
    {
        return $this->hasMany(self::class, 'duplikat_von', 'feedback_id');
    }

    public function istDuplikat(): bool
    {
        return $this->duplikat_von !== null;
    }

    public function hatScreenshot(): bool
    {
        return filled($this->screenshot_pfad);
    }

    /** Übersetzbares Label zu einer Kategorie – auch für die veraltete Datenbank-Kategorie «lob» (siehe KATEGORIE_SONSTIGES). */
    public static function kategorieLabel(?string $wert): string
    {
        if ($wert === self::KATEGORIE_LOB) {
            return 'Sonstiges';
        }

        return self::KATEGORIEN[$wert] ?? (string) $wert;
    }

    /** Nur Hauptmeldungen (keine Duplikate). Solange die Spalte duplikat_von fehlt (vor der Migration auf Prod), unverändert. */
    public function scopeHauptmeldungen(Builder $query): Builder
    {
        return self::hatDuplikatSpalte() ? $query->whereNull('duplikat_von') : $query;
    }

    /** Cachiert statisch, ob die Migration 2026_09_12_000011 schon gelaufen ist. */
    public static function hatDuplikatSpalte(): bool
    {
        if (self::$hatDuplikatSpalte === null) {
            try {
                self::$hatDuplikatSpalte = Schema::hasColumn('feedback', 'duplikat_von');
            } catch (Throwable) {
                self::$hatDuplikatSpalte = false;
            }
        }

        return self::$hatDuplikatSpalte;
    }

    /** Cachiert statisch, ob die Migration 2026_09_12_000012 (Spalte technik_details) schon gelaufen ist. */
    public static function hatTechnikSpalte(): bool
    {
        if (self::$hatTechnikSpalte === null) {
            try {
                self::$hatTechnikSpalte = Schema::hasColumn('feedback', 'technik_details');
            } catch (Throwable) {
                self::$hatTechnikSpalte = false;
            }
        }

        return self::$hatTechnikSpalte;
    }

    /** Cachiert statisch, ob die Migration 2026_09_12_000012 (Tabelle feedback_anhaenge) schon gelaufen ist. */
    public static function hatAnhaengeTabelle(): bool
    {
        if (self::$hatAnhaengeTabelle === null) {
            try {
                self::$hatAnhaengeTabelle = Schema::hasTable('feedback_anhaenge');
            } catch (Throwable) {
                self::$hatAnhaengeTabelle = false;
            }
        }

        return self::$hatAnhaengeTabelle;
    }

    /**
     * Cachiert statisch, ob die Kategorie-Spalte den neuen Wert «sonstiges» schon kennt (Migration
     * 2026_09_12_000012). Solange nicht, muss weiterhin der alte Wert «lob» geschrieben werden –
     * das Enum im Strict-Modus lehnt sonst unbekannte Werte ab.
     */
    public static function hatSonstigesWert(): bool
    {
        if (self::$hatSonstigesWert === null) {
            try {
                $typ = (string) DB::table('information_schema.COLUMNS')
                    ->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
                    ->where('TABLE_NAME', 'feedback')
                    ->where('COLUMN_NAME', 'kategorie')
                    ->value('COLUMN_TYPE');
                self::$hatSonstigesWert = str_contains($typ, "'sonstiges'");
            } catch (Throwable) {
                // Nicht-MySQL/MariaDB (kein information_schema.COLUMNS in dieser Form): bleibt dauerhaft
                // beim alten Wert «lob», siehe docs/audit-backlog.md (Block G) – für diese Anwendung ohne
                // praktische Bedeutung, da Prod und alle Testumgebungen MariaDB verwenden.
                self::$hatSonstigesWert = false;
            }
        }

        return self::$hatSonstigesWert;
    }

    /** Räumt beim (seltenen) Löschen einer Meldung Screenshot und Anhänge von der Disk – die Datenbankzeilen selbst löschen Fremdschlüssel-Kaskaden. */
    protected static function booted(): void
    {
        static::deleting(function (self $feedback) {
            app(Screenshot::class)->loeschen($feedback);
            if (self::hatAnhaengeTabelle()) {
                app(Anhang::class)->loeschenAlle($feedback);
            }
        });
    }
}
