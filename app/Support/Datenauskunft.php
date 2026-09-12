<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Betreuung;
use App\Models\CalendarEvent;
use App\Models\CalendarFeed;
use App\Models\DigestItem;
use App\Models\Dokument;
use App\Models\Feedback;
use App\Models\FeedbackStimme;
use App\Models\Lernender;
use App\Models\LernenderTrack;
use App\Models\MailLog;
use App\Models\ModulBelegung;
use App\Models\Note;
use App\Models\NotenGesehen;
use App\Models\NotenKommentar;
use App\Models\NotificationPreference;
use App\Models\Pruefung;
use App\Models\User;
use App\Models\Ziel;
use App\Services\Dokumente\Ablage;
use Illuminate\Support\Collection;
use Illuminate\Support\Traits\Localizable;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Datenauskunft nach Art. 25 DSG: ein ZIP mit allen Daten, die das Notenportal über ein Konto
 * gespeichert hat – ausgelöst vom Konto selbst (Profil) oder, für ein beliebiges Konto, vom
 * Admin (routes/web.php admin.users.data-export). Enthält nie Daten anderer Personen, ausser
 * deren Namen, wo das zum Verständnis nötig ist (z. B. «geändert von»). Wird komplett in der
 * Sprache des Kontos gebaut, dessen Daten exportiert werden (nicht der auslösenden Person).
 */
final class Datenauskunft
{
    use Localizable;

    /** Oberhalb dieser Grösse (Summe dokumente.groesse) wird statt eines ZIP eine freundliche Meldung angezeigt. Für Tests veränderbar. */
    public static int $maxDokumenteBytes = 524_288_000; // 500 MB

    /** Sicherheitsmarge auf dem freien Speicherplatz, damit das Bauen des ZIP nicht die Disk füllt. */
    private const int FREIER_SPEICHER_RESERVE = 104_857_600; // 100 MB

    public function __construct(private readonly Ablage $ablage) {}

    /** True, wenn die Dokumente des Kontos zu gross fürs ZIP sind (Limit oder zu wenig freier Speicher). */
    public function zuGross(User $user): bool
    {
        $groesse = $this->dokumenteGroesse($user);

        if ($groesse > self::$maxDokumenteBytes) {
            return true;
        }

        $frei = @disk_free_space(sys_get_temp_dir());

        return $frei !== false && $frei < ($groesse + self::FREIER_SPEICHER_RESERVE);
    }

    private function dokumenteGroesse(User $user): int
    {
        $lernender = $user->lernender;
        if (! $lernender) {
            return 0;
        }

        return (int) Dokument::where('lernender_id', $lernender->lernender_id)->sum('groesse');
    }

    /** Baut das ZIP in eine temporäre Datei und gibt deren Pfad zurück; der Aufrufer löscht sie nach dem Versand. */
    public function erzeugen(User $user): string
    {
        $ziel = tempnam(sys_get_temp_dir(), 'np-auskunft');
        if ($ziel === false) {
            throw new RuntimeException('Temporäre Datei lässt sich nicht anlegen.');
        }

        $zip = new ZipArchive;
        if ($zip->open($ziel, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($ziel);
            throw new RuntimeException('ZIP-Datei lässt sich nicht anlegen.');
        }

        try {
            $this->withLocale($user->preferredLocale(), fn () => $this->befuellen($zip, $user));

            if (! $zip->close()) {
                throw new RuntimeException('ZIP-Datei lässt sich nicht schreiben.');
            }
        } catch (Throwable $e) {
            @$zip->close();
            @unlink($ziel);
            throw $e;
        }

        return $ziel;
    }

    public function dateiname(User $user): string
    {
        return 'datenauskunft-'.$user->benutzername.'-'.now()->format('Y-m-d').'.zip';
    }

    /** Baut den gesamten Inhalt des ZIP; läuft innerhalb withLocale, damit alle __()-Aufrufe die Sprache des Kontos verwenden. */
    private function befuellen(ZipArchive $zip, User $user): void
    {
        $enthalten = ['konto.json'];
        $lernender = $user->lernender;

        $zip->addFromString('konto.json', $this->json($this->konto($user)));

        if ($lernender) {
            $enthalten = [...$enthalten, ...$this->lernendenDaten($zip, $lernender)];
        } else {
            $betreuungen = $this->betreuungen($user);
            $this->csv($zip, 'betreuungen.csv', [__('Lernender'), __('Von'), __('Bis')], $betreuungen);
            $enthalten[] = 'betreuungen.csv';

            $kommentare = $this->eigeneKommentare($user);
            $this->csv($zip, 'kommentare.csv', [__('Datum'), __('Lernender'), __('Kommentar')], $kommentare);
            $enthalten[] = 'kommentare.csv';
        }

        $zip->addFromString('feedback.json', $this->json($this->feedback($user)));
        $enthalten[] = 'feedback.json';

        $stimmen = $this->feedbackStimmen($user);
        if ($stimmen !== []) {
            $zip->addFromString('feedback_stimmen.json', $this->json($stimmen));
            $enthalten[] = 'feedback_stimmen.json';
        }

        $this->csv($zip, 'versandprotokoll.csv',
            [__('Betreff'), __('Erstellt am'), __('Verschickt am'), __('Status')],
            $this->versandprotokoll($user));
        $enthalten[] = 'versandprotokoll.csv';

        $gesehen = $this->gesehen($user);
        if ($gesehen !== []) {
            $this->csv($zip, 'gesehen.csv', [__('Datum'), __('Fach / Modul'), __('Lernender')], $gesehen);
            $enthalten[] = 'gesehen.csv';
        }

        $zusammenfassungen = $this->zusammenfassungen($user);
        if ($zusammenfassungen !== []) {
            $zip->addFromString('zusammenfassungen.json', $this->json($zusammenfassungen));
            $enthalten[] = 'zusammenfassungen.json';
        }

        $zip->addFromString('README.txt', $this->readme($user, $enthalten));
    }

    // -----------------------------------------------------------------------------------------
    // Konto (alle Rollen)
    // -----------------------------------------------------------------------------------------

    /** @return array<string, mixed> */
    private function konto(User $user): array
    {
        return [
            'benutzer_id' => $user->benutzer_id,
            'benutzername' => $user->benutzername,
            'email' => $user->email,
            'vorname' => $user->vorname,
            'nachname' => $user->nachname,
            'rollen' => $user->rollen()->pluck('name')->all(),
            'aktiv' => (bool) $user->aktiv,
            'sprache' => $user->locale,
            'darstellung' => $user->darstellung,
            'hoher_kontrast' => (bool) $user->kontrast,
            'praeferenzen' => $user->praeferenzen,
            'erstellt_am' => optional($user->erstellt_am)->toIso8601String(),
            'aktualisiert_am' => optional($user->aktualisiert_am)->toIso8601String(),
            'benachrichtigungen' => NotificationPreference::query()
                ->where('user_id', $user->benutzer_id)
                ->get(['type', 'frequency'])
                ->map(fn (NotificationPreference $p) => ['anlass' => $p->type, 'frequenz' => $p->frequency])
                ->all(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function feedback(User $user): array
    {
        $mitAnhaengen = Feedback::hatAnhaengeTabelle();

        return Feedback::query()
            ->where('benutzer_id', $user->benutzer_id)
            ->when($mitAnhaengen, fn ($q) => $q->with('anhaenge'))
            ->orderByDesc('erstellt_am')
            ->get()
            ->map(fn (Feedback $f) => [
                'erstellt_am' => optional($f->erstellt_am)->toIso8601String(),
                'kategorie' => $f->kategorie,
                'text' => $f->text,
                'seite' => $f->route_name,
                'url' => $f->url,
                'user_agent' => $f->user_agent,
                'ansicht' => $f->viewport,
                'status' => $f->status,
                'antwort' => $f->admin_notiz,
                'erledigt_am' => optional($f->erledigt_am)->toIso8601String(),
                // Nur Dateinamen (keine Inhalte) – die Dateien selbst liegen auf der privaten Disk.
                'anhaenge' => $mitAnhaengen ? $f->anhaenge->pluck('dateiname')->all() : [],
            ])
            ->all();
    }

    /** @return list<array<string, mixed>> Nur eigene Stimmen «Betrifft mich auch», ohne fremden Meldungstext. */
    private function feedbackStimmen(User $user): array
    {
        if (! FeedbackStimme::tabelleVorhanden()) {
            return [];
        }

        return FeedbackStimme::query()
            ->where('benutzer_id', $user->benutzer_id)
            ->with('feedback')
            ->orderByDesc('erstellt_am')
            ->get()
            ->filter(fn (FeedbackStimme $s) => $s->feedback !== null)
            ->map(fn (FeedbackStimme $s) => [
                'erstellt_am' => optional($s->erstellt_am)->toIso8601String(),
                'meldung_id' => $s->feedback_id,
                'kategorie' => $s->feedback->kategorie,
            ])
            ->values()
            ->all();
    }

    /** @return list<list<string>> */
    private function versandprotokoll(User $user): array
    {
        return MailLog::query()
            ->where('user_id', $user->benutzer_id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (MailLog $log) => [
                Csv::safe($log->subject),
                optional($log->created_at)->format('d.m.Y H:i'),
                optional($log->sent_at)->format('d.m.Y H:i') ?? '',
                __(MailLog::STATUS[$log->status] ?? $log->status),
            ])
            ->all();
    }

    /** @return list<list<string>> Datum, Fach/Modul, Lernender (leer, wenn es die eigene Note ist). */
    private function gesehen(User $user): array
    {
        $eigenerLernenderId = $user->lernender?->lernender_id;

        return NotenGesehen::where('viewer_benutzer_id', $user->benutzer_id)
            ->with(['note.fach', 'note.modulBelegung.modul', 'note.lernender.benutzer'])
            ->orderByDesc('gesehen_am')
            ->get()
            ->filter(fn (NotenGesehen $g) => $g->note !== null)
            ->map(function (NotenGesehen $g) use ($eigenerLernenderId) {
                $note = $g->note;
                $fachModul = $note->fach?->name ?? trim(($note->modulBelegung?->modul?->modul_nummer ?? '').' '.($note->modulBelegung?->modul?->titel ?? ''));
                $eigene = $eigenerLernenderId !== null && $note->lernender_id === $eigenerLernenderId;

                return [
                    optional($g->gesehen_am)->format('d.m.Y H:i') ?? '',
                    Csv::safe($fachModul),
                    $eigene ? '' : Csv::safe(trim(($note->lernender?->benutzer?->nachname ?? '').' '.($note->lernender?->benutzer?->vorname ?? ''))),
                ];
            })
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function zusammenfassungen(User $user): array
    {
        return DigestItem::where('user_id', $user->benutzer_id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (DigestItem $d) => [
                'titel' => $d->title,
                'text' => $d->body,
                'erstellt_am' => optional($d->created_at)->toIso8601String(),
                'verschickt_am' => optional($d->sent_at)->toIso8601String(),
            ])
            ->all();
    }

    // -----------------------------------------------------------------------------------------
    // Lernende
    // -----------------------------------------------------------------------------------------

    /** @return list<string> tatsächlich hinzugefügte Dateien/Ordner, für die README. */
    private function lernendenDaten(ZipArchive $zip, Lernender $lernender): array
    {
        $enthalten = [];

        $noten = $this->noten($lernender);
        $zip->addFromString('noten.json', $this->json($noten->map(fn (array $n) => $n['json'])->all()));
        $this->csv($zip, 'noten.csv', [
            __('Datum'), __('Semester'), __('Kategorie'), __('Fach / Modul'), __('Titel'), __('Note'),
            __('Gewichtung %'), __('Erfasst von'), __('Geändert von'), __('Kommentare'),
        ], $noten->map(fn (array $n) => $n['csv'])->all());
        $enthalten[] = 'noten.csv, noten.json';

        $this->csv($zip, 'pruefungen.csv', [
            __('Datum'), __('Uhrzeit'), __('Fach / Modul'), __('Titel'), __('Gewichtung %'), __('Note'), __('Abgesagt'),
            __('Prüfungsart'), __('Raum'), __('Lehrperson'), __('Erlaubte Hilfsmittel'), __('Prüfungsstoff'), __('Eigene Notizen'),
        ], $this->pruefungen($lernender));
        $enthalten[] = 'pruefungen.csv';

        $zip->addFromString('ziele.json', $this->json($this->ziele($lernender)));
        $enthalten[] = 'ziele.json';

        $zip->addFromString('belegungen.json', $this->json($this->belegungen($lernender)));
        $enthalten[] = 'belegungen.json';

        $dokumente = $this->dokumente($zip, $lernender);
        if ($dokumente !== []) {
            $enthalten[] = 'dokumente/';
            $zip->addFromString('dokumente.json', $this->json($dokumente));
            $enthalten[] = 'dokumente.json';
        }

        $kalender = $this->kalender($lernender);
        if ($kalender !== null) {
            $zip->addFromString('kalender.json', $this->json($kalender));
            $enthalten[] = 'kalender.json';
        }

        return $enthalten;
    }

    /** @return Collection<int, array{csv: list<string>, json: array<string, mixed>}> */
    private function noten(Lernender $lernender): Collection
    {
        return Note::query()
            ->where('lernender_id', $lernender->lernender_id)
            ->with(['kategorie', 'semester', 'fach', 'modulBelegung.modul', 'erfasstVonBenutzer', 'aktualisiertVonBenutzer', 'kommentare.autor'])
            ->orderByDesc('pruefungsdatum')
            ->orderByDesc('note_id')
            ->get()
            ->map(function (Note $n) {
                $fachModul = $n->fach?->name ?? trim(($n->modulBelegung?->modul?->modul_nummer ?? '').' '.($n->modulBelegung?->modul?->titel ?? ''));
                $erfasstVon = $n->erfasstVonBenutzer ? $n->erfasstVonBenutzer->vorname.' '.$n->erfasstVonBenutzer->nachname : '';
                $geaendertVon = $n->aktualisiertVonBenutzer ? $n->aktualisiertVonBenutzer->vorname.' '.$n->aktualisiertVonBenutzer->nachname : '';
                $kommentare = $n->kommentare->map(fn (NotenKommentar $k) => [
                    'erstellt_am' => optional($k->erstellt_am)->toIso8601String(),
                    'autor' => $k->autor ? $k->autor->vorname.' '.$k->autor->nachname : null,
                    'text' => $k->kommentar_text,
                ]);

                return [
                    'csv' => [
                        $n->pruefungsdatum?->format('d.m.Y') ?? '',
                        $n->semester?->bezeichnung ?? '',
                        $n->kategorie?->name ?? '',
                        Csv::safe($fachModul),
                        Csv::safe($n->titel ?? ''),
                        (string) $n->note_wert,
                        (string) ($n->gewichtung_prozent ?? 100),
                        Csv::safe($erfasstVon),
                        Csv::safe($geaendertVon),
                        Csv::safe($kommentare->pluck('text')->implode(' | ')),
                    ],
                    'json' => [
                        'note_id' => $n->note_id,
                        'datum' => $n->pruefungsdatum?->toDateString(),
                        'semester' => $n->semester?->bezeichnung,
                        'kategorie' => $n->kategorie?->name,
                        'fach_oder_modul' => $fachModul,
                        'titel' => $n->titel,
                        'note' => $n->note_wert,
                        'gewichtung_prozent' => (float) ($n->gewichtung_prozent ?? 100),
                        'erfasst_von' => $erfasstVon ?: null,
                        'geaendert_von' => $geaendertVon ?: null,
                        'kommentare' => $kommentare->all(),
                    ],
                ];
            });
    }

    /** @return list<list<string>> */
    private function pruefungen(Lernender $lernender): array
    {
        return Pruefung::query()
            ->where('lernender_id', $lernender->lernender_id)
            ->with(['fach', 'modul', 'note'])
            ->orderByDesc('datum')
            ->get()
            ->map(fn (Pruefung $p) => [
                $p->datum->format('d.m.Y'),
                $p->uhrzeit ?? '',
                Csv::safe($p->bezeichnung()),
                Csv::safe($p->titel ?? ''),
                (string) ($p->gewichtung_prozent ?? 100),
                $p->note?->note_wert ?? '',
                optional($p->abgesagt_am)->format('d.m.Y') ?? '',
                Csv::safe($p->pruefungsart ?? ''),
                Csv::safe($p->raum ?? ''),
                Csv::safe($p->lehrperson ?? ''),
                Csv::safe($p->hilfsmittel ?? ''),
                Csv::safe($p->stoff ?? ''),
                Csv::safe($p->notizen ?? ''),
            ])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function ziele(Lernender $lernender): array
    {
        return Ziel::query()
            ->where('lernender_id', $lernender->lernender_id)
            ->get()
            ->map(fn (Ziel $z) => [
                'ebene' => $z->ebene,
                'kategorie_id' => $z->kategorie_id,
                'fach_id' => $z->fach_id,
                'modul_id' => $z->modul_id,
                'zielwert' => $z->zielwert,
            ])
            ->all();
    }

    /** @return array{modul_belegungen: list<array<string, mixed>>, tracks: list<array<string, mixed>>} */
    private function belegungen(Lernender $lernender): array
    {
        $belegungen = ModulBelegung::query()
            ->where('lernender_id', $lernender->lernender_id)
            ->with('modul')
            ->get()
            ->map(fn (ModulBelegung $b) => [
                'modul_nummer' => $b->modul?->modul_nummer,
                'modul_titel' => $b->modul?->titel,
                'start_datum' => $b->start_datum?->toDateString(),
                'end_datum' => $b->end_datum?->toDateString(),
            ]);

        $tracks = LernenderTrack::query()
            ->where('lernender_id', $lernender->lernender_id)
            ->with(['startSemester', 'endSemester'])
            ->get()
            ->map(fn (LernenderTrack $t) => [
                'typ' => $t->track_typ,
                'start_datum' => $t->start_datum?->toDateString(),
                'end_datum' => $t->end_datum?->toDateString(),
                'start_semester' => $t->startSemester?->bezeichnung,
                'end_semester' => $t->endSemester?->bezeichnung,
            ]);

        return ['modul_belegungen' => $belegungen->all(), 'tracks' => $tracks->all()];
    }

    /**
     * Eigene Dokumente mit demselben sprechenden Namen wie beim Download (Ablage::dateiname), unkomprimiert
     * (CM_STORE) im ZIP abgelegt, damit die Datei byteidentisch bleibt. Gibt die Metadaten für dokumente.json zurück.
     *
     * @return list<array<string, mixed>>
     */
    private function dokumente(ZipArchive $zip, Lernender $lernender): array
    {
        $lernender->loadMissing('benutzer');

        $verwendet = [];
        $metadaten = [];
        foreach (Dokument::where('lernender_id', $lernender->lernender_id)->with(['semester', 'hochgeladenVon'])->get() as $dokument) {
            $dokument->setRelation('lernender', $lernender);
            $name = $this->ablage->dateiname($dokument);

            if (isset($verwendet[$name])) {
                $verwendet[$name]++;
                $teile = pathinfo($name);
                $name = $teile['filename'].'-'.$verwendet[$name].'.'.($teile['extension'] ?? $dokument->endung());
            } else {
                $verwendet[$name] = 1;
            }

            $pfad = $this->ablage->pfad($dokument);
            if (is_file($pfad)) {
                $eintrag = 'dokumente/'.$name;
                $zip->addFile($pfad, $eintrag);
                $zip->setCompressionName($eintrag, ZipArchive::CM_STORE);
            }

            $hochgeladenVon = $dokument->hochgeladenVon
                ? trim($dokument->hochgeladenVon->vorname.' '.$dokument->hochgeladenVon->nachname)
                : null;

            $metadaten[] = [
                'dateiname' => $name,
                'originalname' => $dokument->originalname,
                'titel' => $dokument->titel,
                'art' => Dokument::label($dokument->art),
                'semester' => $dokument->semester?->bezeichnung,
                'hochgeladen_von' => $hochgeladenVon,
                'erstellt_am' => optional($dokument->erstellt_am)->toIso8601String(),
            ];
        }

        return $metadaten;
    }

    /** Abonnierte Kalender (Label/URL, keine internen Tokens) und ihre Termine. */
    private function kalender(Lernender $lernender): ?array
    {
        $feeds = CalendarFeed::where('lernender_id', $lernender->lernender_id)->get();
        if ($feeds->isEmpty()) {
            return null;
        }

        $termine = CalendarEvent::where('lernender_id', $lernender->lernender_id)
            ->orderBy('starts_at')
            ->get()
            ->map(fn (CalendarEvent $e) => [
                'art' => CalendarEvent::KINDS[$e->kind] ?? $e->kind,
                'titel' => $e->summary,
                'beschreibung' => $e->description,
                'ort' => $e->location,
                'beginn' => optional($e->starts_at)->toIso8601String(),
                'ende' => optional($e->ends_at)->toIso8601String(),
                'ganztags' => (bool) $e->all_day,
            ]);

        return [
            'abonnements' => $feeds->map(fn (CalendarFeed $f) => [
                'label' => $f->label,
                'url' => $f->url,
                'zuletzt_synchronisiert' => optional($f->last_synced_at)->toIso8601String(),
                'status' => $f->last_status,
            ])->all(),
            'termine' => $termine->all(),
        ];
    }

    // -----------------------------------------------------------------------------------------
    // Berufsbildner / Admin
    // -----------------------------------------------------------------------------------------

    /** @return list<list<string>> */
    private function betreuungen(User $user): array
    {
        $berufsbildnerId = $user->berufsbildner?->berufsbildner_id;
        if (! $berufsbildnerId) {
            return [];
        }

        return Betreuung::query()
            ->where('berufsbildner_id', $berufsbildnerId)
            ->with('lernender.benutzer')
            ->orderByDesc('gueltig_von')
            ->get()
            ->map(fn (Betreuung $b) => [
                Csv::safe(trim(($b->lernender?->benutzer?->nachname ?? '').' '.($b->lernender?->benutzer?->vorname ?? ''))),
                $b->gueltig_von?->format('d.m.Y') ?? '',
                $b->gueltig_bis?->format('d.m.Y') ?? '',
            ])
            ->all();
    }

    /** @return list<list<string>> */
    private function eigeneKommentare(User $user): array
    {
        return NotenKommentar::query()
            ->where('autor_benutzer_id', $user->benutzer_id)
            ->with('note.lernender.benutzer')
            ->orderByDesc('erstellt_am')
            ->get()
            ->map(fn (NotenKommentar $k) => [
                optional($k->erstellt_am)->format('d.m.Y H:i'),
                Csv::safe(trim(($k->note?->lernender?->benutzer?->nachname ?? '').' '.($k->note?->lernender?->benutzer?->vorname ?? ''))),
                Csv::safe($k->kommentar_text),
            ])
            ->all();
    }

    // -----------------------------------------------------------------------------------------
    // Gemeinsame Hilfsmittel
    // -----------------------------------------------------------------------------------------

    private function json(mixed $daten): string
    {
        return (string) json_encode(
            $daten,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE
        );
    }

    /** @param  list<string>  $kopf @param iterable<list<string>> $zeilen */
    private function csv(ZipArchive $zip, string $dateiname, array $kopf, iterable $zeilen): void
    {
        $out = fopen('php://temp', 'r+');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $kopf, ';');
        foreach ($zeilen as $zeile) {
            fputcsv($out, $zeile, ';');
        }
        rewind($out);
        $inhalt = (string) stream_get_contents($out);
        fclose($out);

        $zip->addFromString($dateiname, $inhalt);
    }

    /** @param  list<string>  $enthalten  Dateien/Ordner, die tatsächlich im ZIP stecken. */
    private function readme(User $user, array $enthalten): string
    {
        $name = trim($user->vorname.' '.$user->nachname);
        $zeilen = [
            'Notenportal – '.__('Datenauskunft'),
            str_repeat('=', 20),
            '',
            __('Konto').': '.$name.' ('.$user->benutzername.')',
            __('Erstellt am').' '.now()->format('d.m.Y H:i'),
            '',
            __('Diese Datei enthält alle Daten, die das Notenportal zu diesem Konto gespeichert hat.'),
            '',
            __('Enthaltene Dateien').':',
        ];

        // Datei/Ordner im ZIP => literal übersetzte Beschreibung (Reihenfolge wie im ZIP befüllt).
        $beschreibungen = [
            'konto.json' => __('Kontodaten: Profil, Rollen und persönliche Einstellungen (ohne Passwort).'),
            'noten.csv, noten.json' => __('Alle Noten mit Fach oder Modul, Datum, Gewichtung und Kommentaren.'),
            'pruefungen.csv' => __('Geplante und vergangene Prüfungen.'),
            'ziele.json' => __('Persönliche Notenziele.'),
            'belegungen.json' => __('Modulbelegungen und Ausbildungsabschnitte.'),
            'dokumente/' => __('Hochgeladene Dokumente.'),
            'dokumente.json' => __('Metadaten zu den hochgeladenen Dokumenten.'),
            'kalender.json' => __('Abonnierte Kalender und ihre Termine.'),
            'betreuungen.csv' => __('Betreute Lernende mit Beginn und Ende der Betreuung.'),
            'kommentare.csv' => __('Eigene Kommentare zu Noten.'),
            'feedback.json' => __('Eigene Feedback-Meldungen und Antworten.'),
            'feedback_stimmen.json' => __('Eigene Stimmen «Betrifft mich auch» zu fremden Meldungen.'),
            'versandprotokoll.csv' => __('Versandprotokoll der eigenen E-Mails.'),
            'gesehen.csv' => __('Wann welche Note angesehen wurde.'),
            'zusammenfassungen.json' => __('Eigene Tageszusammenfassungen.'),
        ];

        foreach ($beschreibungen as $datei => $beschreibung) {
            if (in_array($datei, $enthalten, true)) {
                $zeilen[] = '- '.$datei.': '.$beschreibung;
            }
        }

        return implode("\n", $zeilen)."\n";
    }
}
