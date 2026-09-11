<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use Carbon\CarbonImmutable;

/**
 * Zerlegt Termintexte aus dem Schulnetz (iCal SUMMARY/DESCRIPTION) in Prüfungsfelder.
 *
 * Beispiel DESCRIPTION:
 *   Prüfung
 *   159-INPE 24 B-diemar LB1: Verzeichnisdienste und DNS
 *   Prüfungsstoff
 *   Prüfungsart: Onlineprüfung in Microsoft Teams
 *   Dauer: 45 Minuten
 *   Hilfsmittel: Keine Unterlagen erlaubt
 *   Sie können:
 *   - …
 *   Gewichtung
 *   0.33333333
 *   Prüfungsdatum festgelegt am
 *   01.09.2026 13:54
 *
 * Tolerant: unbekannte Abschnitte und Zeilen landen im Prüfungsstoff, nichts wirft.
 */
final class SchoolNetDescriptionParser
{
    /** Überschrift (klein, ohne Doppelpunkt) => Abschnitt */
    private const array HEADINGS = [
        'prüfung' => 'exam',
        'pruefung' => 'exam',
        'prüfungsstoff' => 'material',
        'pruefungsstoff' => 'material',
        'lernziele' => 'material',
        'gewichtung' => 'weight',
        'prüfungsdatum festgelegt am' => 'scheduled',
        'pruefungsdatum festgelegt am' => 'scheduled',
    ];

    /** Schlüssel in «Schlüssel: Wert»-Zeilen => Feld */
    private const array KEYS = [
        'prüfungsart' => 'exam_type',
        'pruefungsart' => 'exam_type',
        'art' => 'exam_type',
        'dauer' => 'duration',
        'hilfsmittel' => 'aids',
        'erlaubte hilfsmittel' => 'aids',
        'gewichtung' => 'weight',
        'raum' => 'room',
        'ort' => 'room',
    ];

    /**
     * @return array{course_code: ?string, module_number: ?string, class_name: ?string, teacher: ?string, label: ?string,
     *               title: ?string, exam_type: ?string, duration_minutes: ?int, aids: ?string, material: ?string,
     *               weight_percent: ?float, scheduled_at: ?CarbonImmutable, room: ?string, is_exam: bool}
     */
    public function parse(?string $summary, ?string $description): array
    {
        $result = [
            'course_code' => null, 'module_number' => null, 'class_name' => null, 'teacher' => null, 'label' => null,
            'title' => null, 'exam_type' => null, 'duration_minutes' => null, 'aids' => null, 'material' => null,
            'weight_percent' => null, 'scheduled_at' => null, 'room' => null, 'is_exam' => false,
        ];

        $lines = $this->lines($description);
        $section = null;
        $material = [];
        $headerLine = null;

        foreach ($lines as $line) {
            $key = mb_strtolower(rtrim(trim($line), ':'));
            if (isset(self::HEADINGS[$key])) {
                $section = self::HEADINGS[$key];
                $result['is_exam'] = $result['is_exam'] || $section === 'exam';
                continue;
            }
            if (trim($line) === '' ) {
                if ($section === 'material' && $material !== []) {
                    $material[] = '';
                }
                continue;
            }

            if (preg_match('/^\s*([\p{L} ]{2,30}):\s*(.+)$/u', $line, $m) && isset(self::KEYS[mb_strtolower(trim($m[1]))])) {
                $this->assign($result, self::KEYS[mb_strtolower(trim($m[1]))], trim($m[2]));
                continue;
            }

            match ($section) {
                'exam' => $headerLine === null ? $headerLine = trim($line) : $material[] = rtrim($line),
                'weight' => $result['weight_percent'] ??= $this->weight($line),
                'scheduled' => $result['scheduled_at'] ??= $this->dateTime($line),
                default => $material[] = rtrim($line),
            };
        }

        // Kopfzeile: aus dem Abschnitt «Prüfung», sonst aus SUMMARY
        $header = $this->header($headerLine ?? trim((string) $summary));
        if ($header['course_code'] === null && $headerLine !== null && filled($summary)) {
            $header = $this->header(trim((string) $summary));
        }
        $result = array_merge($result, array_filter($header, fn ($v) => $v !== null));
        if ($result['title'] === null && filled($summary)) {
            $result['title'] = trim((string) $summary);
        }

        $stoff = trim(implode("\n", $material));
        $stoff = preg_replace("/\n{3,}/", "\n\n", $stoff);
        $result['material'] = $stoff !== '' ? $stoff : null;

        return $result;
    }

    /**
     * «159-INPE 24 B-diemar LB1: Verzeichnisdienste und DNS»,
     * «ABU-INAP 24 A,INPE 24 B-spedeb», «SPO-INAP 24 A,INPE 24 B-wieand (hunjef)».
     *
     * @return array{course_code: ?string, module_number: ?string, class_name: ?string, teacher: ?string, label: ?string, title: ?string}
     */
    public function header(string $line): array
    {
        $leer = ['course_code' => null, 'module_number' => null, 'class_name' => null, 'teacher' => null, 'label' => null, 'title' => null];
        if (! preg_match('/^(?<code>[A-Za-z0-9]{2,10})-(?<klasse>[A-Za-z]{2,}[^-]*?)-(?<lp>[a-z]{3,10})(?:\s*\((?<lp2>[a-z]{3,10})\))?(?:\s+(?<rest>.*))?$/u', $line, $m)) {
            return $leer;
        }
        $rest = trim($m['rest'] ?? '');
        $label = null;
        $title = $rest !== '' ? $rest : null;
        if (preg_match('/^(?<label>[^:]{1,24}):\s*(?<title>.+)$/u', $rest, $t)) {
            $label = trim($t['label']);
            $title = trim($t['title']);
        }

        return [
            'course_code' => $m['code'],
            'module_number' => ctype_digit($m['code']) ? $m['code'] : null,
            'class_name' => trim(preg_replace('/\s*,\s*/', ', ', $m['klasse'])),
            'teacher' => $m['lp'].(! empty($m['lp2']) ? ' ('.$m['lp2'].')' : ''),
            'label' => $label,
            'title' => $title,
        ];
    }

    /** Bruchteil (0.33333333, 1/3) → Prozent; Werte > 1 gelten schon als Prozent. */
    public function weight(string $text): ?float
    {
        $t = str_replace([' ', "'"], '', trim(str_replace('%', '', $text)));
        if (preg_match('#^(\d+)/(\d+)$#', $t, $m) && (int) $m[2] > 0) {
            return round((int) $m[1] / (int) $m[2] * 100, 2);
        }
        $t = str_replace(',', '.', $t);
        if (! is_numeric($t) || (float) $t <= 0) {
            return null;
        }
        $wert = (float) $t;

        return round($wert <= 1 ? $wert * 100 : $wert, 2);
    }

    /** «45 Minuten», «90 min», «1.5 Stunden», «2 Lektionen» (à 45 min), «1 h 30 min» → Minuten */
    public function duration(string $text): ?int
    {
        $t = mb_strtolower(str_replace(',', '.', $text));
        $minuten = 0.0;
        $treffer = false;
        if (preg_match('/(\d+(?:\.\d+)?)\s*(?:h\b|std|stunde)/u', $t, $m)) {
            $minuten += (float) $m[1] * 60;
            $treffer = true;
        }
        if (preg_match('/(\d+(?:\.\d+)?)\s*(?:min|minute)/u', $t, $m)) {
            $minuten += (float) $m[1];
            $treffer = true;
        }
        if (! $treffer && preg_match('/(\d+(?:\.\d+)?)\s*lektion/u', $t, $m)) {
            $minuten = (float) $m[1] * 45;
            $treffer = true;
        }
        if (! $treffer && preg_match('/^\s*(\d{1,3})\s*$/', $t, $m)) {
            $minuten = (float) $m[1];
            $treffer = true;
        }

        return $treffer && $minuten > 0 ? (int) round($minuten) : null;
    }

    private function dateTime(string $text): ?CarbonImmutable
    {
        if (preg_match('/(\d{1,2})\.(\d{1,2})\.(\d{4})(?:\s+(\d{1,2}):(\d{2}))?/', $text, $m)) {
            try {
                return CarbonImmutable::create((int) $m[3], (int) $m[2], (int) $m[1], (int) ($m[4] ?? 0), (int) ($m[5] ?? 0), 0, config('app.timezone'));
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    private function assign(array &$result, string $field, string $value): void
    {
        match ($field) {
            'duration' => $result['duration_minutes'] ??= $this->duration($value),
            'weight' => $result['weight_percent'] ??= $this->weight($value),
            default => $result[$field] ??= $value,
        };
    }

    /** @return list<string> */
    private function lines(?string $text): array
    {
        $text = (string) $text;
        if (str_contains($text, '<')) {
            $text = html_entity_decode(strip_tags(preg_replace('#<br\s*/?>|</p>|</li>#i', "\n", $text)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        $text = str_replace(['\\n', '\\N', "\r\n", "\r"], "\n", $text);
        $text = str_replace(['\\,', '\\;'], [',', ';'], $text);

        return explode("\n", $text);
    }
}
