<?php
declare(strict_types=1);

/*
  app/validate.php
  Zweck:
  - zentrale Validierung/Sanitizing für Form-Inputs
*/

function v_int_id($value): ?int
{
    if ($value === null) return null;
    $s = trim((string)$value);
    if ($s === '' || !ctype_digit($s)) return null;
    $i = (int)$s;
    return $i > 0 ? $i : null;
}

function v_date_ymd($value): ?string
{
    $s = trim((string)$value);
    if ($s === '') return null;

    $dt = DateTimeImmutable::createFromFormat('Y-m-d', $s);
    if (!$dt) return null;

    $errs = DateTimeImmutable::getLastErrors();
    if ($errs && ($errs['warning_count'] > 0 || $errs['error_count'] > 0)) {
        return null;
    }

    return $dt->format('Y-m-d');
}

function v_grade($value): ?string
{
    $s = str_replace(',', '.', trim((string)$value));
    if ($s === '') return null;

    // erlaubt: 1 bis 6, optional .0 bis .9
    if (!preg_match('/^\d(\.\d)?$/', $s)) {
        return null;
    }

    $f = (float)$s;
    if ($f < 1.0 || $f > 6.0) return null;

    return number_format($f, 1, '.', '');
}

/**
 * Gewichtung in %: DECIMAL(6,2) in DB -> erlaubt z.B. 50, 12.5, 33.33
 * Rückgabe als string mit 2 Dezimalstellen (passt sauber für DECIMAL).
 */
function v_weight_percent($value): ?string
{
    if ($value === null) return null;

    $s = str_replace(',', '.', trim((string)$value));
    if ($s === '') return null;

    // Zahl mit optional bis 2 Nachkommastellen
    if (!preg_match('/^\d{1,3}(\.\d{1,2})?$/', $s)) {
        return null;
    }

    $f = (float)$s;
    if ($f < 0.0 || $f > 100.0) return null;

    return number_format($f, 2, '.', '');
}

function v_title($value, int $maxLen = 150): ?string
{
    if ($value === null) return null;
    $s = trim((string)$value);
    if ($s === '') return null;

    // simple length limit (UTF-8 safe genug für unseren Zweck)
    if (mb_strlen($s, 'UTF-8') > $maxLen) {
        $s = mb_substr($s, 0, $maxLen, 'UTF-8');
    }

    return $s;
}

/**
 * Validiert Note-Formular (neues Schema noten).
 *
 * Erwartet Keys:
 * - kategorie_id (required)
 * - semester_id (required)
 * - fach_id ODER modul_belegung_id (XOR required)
 * - gruppe_id (optional, nur sinnvoll wenn modul_belegung_id gesetzt)
 * - titel (optional)
 * - pruefungsdatum (required)
 * - note_wert (required)
 * - gewichtung_prozent (optional, default 100.00)
 * - lernender_id (required für Admin, sonst fixedLernenderId)
 */
function validate_note_form(array $in, bool $isAdmin, ?int $fixedLernenderId = null): array
{
    $errors = [];
    $out = [];

    $out['kategorie_id'] = v_int_id($in['kategorie_id'] ?? null);
    if (!$out['kategorie_id']) $errors['kategorie_id'] = 'Kategorie fehlt.';

    $out['semester_id'] = v_int_id($in['semester_id'] ?? null);
    if (!$out['semester_id']) $errors['semester_id'] = 'Semester fehlt.';

    $out['pruefungsdatum'] = v_date_ymd($in['pruefungsdatum'] ?? null);
    if (!$out['pruefungsdatum']) $errors['pruefungsdatum'] = 'Datum ist ungültig.';

    $out['note_wert'] = v_grade($in['note_wert'] ?? null);
    if (!$out['note_wert']) $errors['note_wert'] = 'Note muss zwischen 1.0 und 6.0 liegen.';

    $out['gewichtung_prozent'] = v_weight_percent($in['gewichtung_prozent'] ?? null);
    if ($out['gewichtung_prozent'] === null) {
        $out['gewichtung_prozent'] = '100.00';
    }

    $out['titel'] = v_title($in['titel'] ?? null, 150);

    $out['fach_id'] = v_int_id($in['fach_id'] ?? null);
    $out['modul_belegung_id'] = v_int_id($in['modul_belegung_id'] ?? ($in['modul_id'] ?? null)); // fallback falls altes Feld noch kommt
    $out['gruppe_id'] = v_int_id($in['gruppe_id'] ?? null);

    // XOR: fach_id oder modul_belegung_id
    if (!$out['fach_id'] && !$out['modul_belegung_id']) {
        $errors['objekt'] = 'Wähle ein Fach oder eine Modul-Belegung.';
    }
    if ($out['fach_id'] && $out['modul_belegung_id']) {
        $errors['objekt'] = 'Wähle entweder Fach oder Modul-Belegung, nicht beides.';
    }

    // gruppe_id nur wenn Modul-Note
    if ($out['gruppe_id'] && !$out['modul_belegung_id']) {
        $errors['gruppe_id'] = 'Eine Gruppe ist nur bei einer Modul-Note erlaubt.';
    }

    if ($isAdmin) {
        $out['lernender_id'] = v_int_id($in['lernender_id'] ?? null);
        if (!$out['lernender_id']) $errors['lernender_id'] = 'Lernender fehlt.';
    } else {
        $out['lernender_id'] = $fixedLernenderId;
        if (!$out['lernender_id']) $errors['lernender_id'] = 'Kein Lernenden-Profil gefunden.';
    }

    return ['ok' => !$errors, 'data' => $out, 'errors' => $errors];
}
