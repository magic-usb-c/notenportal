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

    // strict check
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

    if (!preg_match('/^\d(\.\d)?$/', $s)) {
        return null;
    }

    $f = (float)$s;
    if ($f < 1.0 || $f > 6.0) return null;

    // als string mit einer Dezimalstelle zurückgeben (DB DECIMAL kompatibel)
    return number_format($f, 1, '.', '');
}

function v_percent($value): ?int
{
    $s = trim((string)$value);
    if ($s === '') return null;
    if (!ctype_digit($s)) return null;
    $i = (int)$s;
    if ($i < 0 || $i > 100) return null;
    return $i;
}

/**
 * Validiert Note-Formular.
 * Erwartet Keys: kategorie_id, semester_id, fach_id, modul_id, pruefungsdatum, note_wert, gewichtung_prozent, lernender_id(optional)
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

    $out['gewichtung_prozent'] = v_percent($in['gewichtung_prozent'] ?? null);
    if ($out['gewichtung_prozent'] === null) {
        $out['gewichtung_prozent'] = 100; // Default
    }

    $out['fach_id'] = v_int_id($in['fach_id'] ?? null);
    $out['modul_id'] = v_int_id($in['modul_id'] ?? null);

    if (!$out['fach_id'] && !$out['modul_id']) {
        $errors['objekt'] = 'Wähle ein Fach oder ein Modul.';
    }
    if ($out['fach_id'] && $out['modul_id']) {
        $errors['objekt'] = 'Wähle entweder Fach oder Modul, nicht beides.';
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
