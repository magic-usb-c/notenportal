<?php

declare(strict_types=1);

namespace Tests\Feature\Performance;

use App\Models\Berufsbildner;
use App\Models\Betreuung;
use App\Models\Fach;
use App\Models\Feedback;
use App\Models\FeedbackStimme;
use App\Models\Kategorie;
use App\Models\Lehrberuf;
use App\Models\Lernender;
use App\Models\MailLog;
use App\Models\Modul;
use App\Models\Semester;
use App\Models\User;
use App\Services\Auswertung\Konfiguration;
use App\Support\Protokoll;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Vor dem Go-Live: Abfragenanzahl pro Seite darf nicht mit der Datenmenge wachsen (keine N+1).
 * Jede Seite wird einmal mit wenig und einmal mit ~5x mehr Daten (Noten/Lernende/Semester)
 * aufgerufen; die Abfragenzahl darf dabei nur um eine kleine Toleranz wachsen und muss unter
 * einer festen Obergrenze bleiben.
 */
class AbfragenAnzahlTest extends TestCase
{
    /** Erlaubtes Wachstum der Abfragenzahl zwischen kleinem und grossem Datensatz. */
    private const int TOLERANZ = 2;

    /**
     * Zählt die DB-Abfragen, die innerhalb von $aktion ausgeführt werden.
     * Konfiguration ist prozessweit gecacht (ein Request lädt Stammdaten einmal) – vor jeder
     * Messung zurückgesetzt, damit beide Messungen (klein/gross) gleich "kalt" starten.
     */
    private function abfragenFuer(\Closure $aktion): int
    {
        Konfiguration::vergessen();
        DB::flushQueryLog();
        DB::enableQueryLog();

        $aktion();

        $anzahl = count(DB::getQueryLog());
        DB::flushQueryLog();

        return $anzahl;
    }

    private function assertWaechstNicht(int $klein, int $gross, int $obergrenze, string $seite): void
    {
        self::assertLessThanOrEqual(
            $klein + self::TOLERANZ,
            $gross,
            "{$seite}: Abfragenzahl wächst mit der Datenmenge ({$klein} -> {$gross}) – vermutlich N+1."
        );
        self::assertLessThanOrEqual($obergrenze, $gross, "{$seite}: {$gross} Abfragen übersteigen die Obergrenze von {$obergrenze}.");
    }

    /** Lehrberuf mit einem Fach (ohne Track, direkt freigegeben) und einem Modul. */
    private function betrieb(): object
    {
        $kategorieId = Kategorie::where('code', 'FACH')->value('kategorie_id');
        $lehrberuf = Lehrberuf::factory()->create();
        $fach = Fach::factory()->create(['track_typ' => null, 'kategorie_id' => $kategorieId]);
        $modul = Modul::factory()->create();

        DB::table('lehrberuf_faecher')->insert(['lehrberuf_id' => $lehrberuf->lehrberuf_id, 'fach_id' => $fach->fach_id, 'aktiv' => 1]);
        DB::table('lehrberuf_module')->insert([
            'lehrberuf_id' => $lehrberuf->lehrberuf_id, 'modul_id' => $modul->modul_id,
            'kategorie_id' => $kategorieId, 'pflicht' => 1, 'aktiv' => 1,
        ]);

        return (object) ['lehrberuf' => $lehrberuf, 'fach' => $fach, 'modul' => $modul, 'kategorieId' => $kategorieId];
    }

    /** Fortlaufende, nicht überlappende Semester (je 6 Monate), Standardbeginn 2020-08-01. */
    private function semesterReihe(int $anzahl, ?Carbon $start = null, int $sortierungAb = 1): Collection
    {
        $start = $start?->copy() ?? Carbon::parse('2020-08-01');
        $liste = collect();
        for ($i = 0; $i < $anzahl; $i++) {
            $ende = $start->copy()->addMonths(6)->subDay();
            $liste->push(Semester::factory()->create([
                'bezeichnung' => 'S'.($sortierungAb + $i).'-'.fake()->unique()->numberBetween(1, 99999),
                'start_datum' => $start->toDateString(),
                'end_datum' => $ende->toDateString(),
                'sortierung' => $sortierungAb + $i,
            ]));
            $start = $ende->copy()->addDay();
        }

        return $liste;
    }

    private function lernender(object $betrieb, Carbon $lehrbeginn, ?Carbon $lehrende = null): Lernender
    {
        return User::factory()->lernender([
            'lehrberuf_id' => $betrieb->lehrberuf->lehrberuf_id,
            'lehrbeginn' => $lehrbeginn->toDateString(),
            'lehrende' => $lehrende?->toDateString(),
        ])->create()->lernender;
    }

    /** Noten gleichmässig über die Semester verteilt (direkter Insert, ohne Formularvalidierung). */
    private function noten(object $betrieb, Lernender $lernender, Collection $semester, int $anzahl): void
    {
        $rows = [];
        for ($i = 0; $i < $anzahl; $i++) {
            $sem = $semester[$i % $semester->count()];
            $rows[] = [
                'lernender_id' => $lernender->lernender_id,
                'kategorie_id' => $betrieb->kategorieId,
                'semester_id' => $sem->semester_id,
                'fach_id' => $betrieb->fach->fach_id,
                'modul_belegung_id' => null,
                'titel' => 'Note '.$i,
                'pruefungsdatum' => Carbon::parse($sem->start_datum)->addDays(5 + ($i % 30))->toDateString(),
                'note_wert' => 4.5,
                'gewichtung_prozent' => 100,
                'erfasst_von_benutzer_id' => $lernender->benutzer_id,
            ];
        }
        DB::table('noten')->insert($rows);
    }

    /** Kommentare zu den (zuletzt eingefügten) Noten eines Lernenden. */
    private function kommentare(Lernender $lernender, int $proNote): void
    {
        $noteIds = DB::table('noten')->where('lernender_id', $lernender->lernender_id)->pluck('note_id');
        $rows = [];
        foreach ($noteIds as $noteId) {
            for ($i = 0; $i < $proNote; $i++) {
                $rows[] = [
                    'note_id' => $noteId,
                    'autor_benutzer_id' => $lernender->benutzer_id,
                    'kommentar_text' => 'Kommentar '.$i,
                    'erstellt_am' => now(),
                ];
            }
        }
        if ($rows !== []) {
            DB::table('noten_kommentare')->insert($rows);
        }
    }

    /** Offene (geplante) Prüfungen, je zur Hälfte überfällig und künftig. */
    private function pruefungen(object $betrieb, Lernender $lernender, int $anzahl): void
    {
        $rows = [];
        for ($i = 0; $i < $anzahl; $i++) {
            $datum = $i % 2 === 0 ? now()->subDays(3 + $i) : now()->addDays(3 + $i);
            $rows[] = [
                'lernender_id' => $lernender->lernender_id,
                'fach_id' => $betrieb->fach->fach_id,
                'modul_id' => null,
                'titel' => 'Prüfung '.$i,
                'datum' => $datum->toDateString(),
                'gewichtung_prozent' => 100,
                'quelle' => 'manuell',
            ];
        }
        DB::table('pruefungen')->insert($rows);
    }

    // --- Lernender: Dashboard, Notenliste, Rechner, Prüfungen, Druckansicht ------------------

    #[Test]
    public function lernender_dashboard_abfragenzahl_unabhaengig_von_datenmenge(): void
    {
        $betrieb = $this->betrieb();
        $semester = $this->semesterReihe(2);
        $user = User::factory()->lernender([
            'lehrberuf_id' => $betrieb->lehrberuf->lehrberuf_id,
            'lehrbeginn' => $semester->first()->start_datum,
            'lehrende' => $semester->last()->end_datum,
        ])->create();
        $lernender = $user->lernender;
        $this->noten($betrieb, $lernender, $semester, 5);
        $this->pruefungen($betrieb, $lernender, 2);
        $this->kommentare($lernender, 1);

        $klein = $this->abfragenFuer(fn () => $this->actingAs($user)->get(route('learner.dashboard'))->assertOk());

        $semesterGross = $this->semesterReihe(8, Carbon::parse($semester->last()->end_datum)->addDay(), $semester->count() + 1);
        $lernender->update(['lehrende' => $semesterGross->last()->end_datum]);
        $this->noten($betrieb, $lernender, $semester->concat($semesterGross), 20);
        $this->pruefungen($betrieb, $lernender, 8);
        $this->kommentare($lernender, 1);

        $gross = $this->abfragenFuer(fn () => $this->actingAs($user)->get(route('learner.dashboard'))->assertOk());

        $this->assertWaechstNicht($klein, $gross, 40, 'Lernender-Dashboard');
    }

    #[Test]
    public function lernender_notenliste_abfragenzahl_unabhaengig_von_datenmenge(): void
    {
        $betrieb = $this->betrieb();
        $semester = $this->semesterReihe(2);
        $user = User::factory()->lernender([
            'lehrberuf_id' => $betrieb->lehrberuf->lehrberuf_id,
            'lehrbeginn' => $semester->first()->start_datum,
            'lehrende' => $semester->last()->end_datum,
        ])->create();
        $lernender = $user->lernender;
        $this->noten($betrieb, $lernender, $semester, 5);
        $this->kommentare($lernender, 1);

        $klein = $this->abfragenFuer(fn () => $this->actingAs($user)
            ->get(route('learner.grades.index', ['semester_id' => $semester->first()->semester_id]))->assertOk());

        $this->noten($betrieb, $lernender, $semester, 20);
        $this->kommentare($lernender, 1);

        $gross = $this->abfragenFuer(fn () => $this->actingAs($user)
            ->get(route('learner.grades.index', ['semester_id' => $semester->first()->semester_id]))->assertOk());

        $this->assertWaechstNicht($klein, $gross, 40, 'Lernender-Notenliste');
    }

    #[Test]
    public function lernender_rechner_abfragenzahl_unabhaengig_von_datenmenge(): void
    {
        $betrieb = $this->betrieb();
        $semester = $this->semesterReihe(2);
        $user = User::factory()->lernender([
            'lehrberuf_id' => $betrieb->lehrberuf->lehrberuf_id,
            'lehrbeginn' => $semester->first()->start_datum,
            'lehrende' => $semester->last()->end_datum,
        ])->create();
        $lernender = $user->lernender;
        $this->noten($betrieb, $lernender, $semester, 5);

        $klein = $this->abfragenFuer(fn () => $this->actingAs($user)->get(route('learner.grades.calculator'))->assertOk());

        $this->noten($betrieb, $lernender, $semester, 20);

        $gross = $this->abfragenFuer(fn () => $this->actingAs($user)->get(route('learner.grades.calculator'))->assertOk());

        $this->assertWaechstNicht($klein, $gross, 40, 'Lernender-Rechner');
    }

    #[Test]
    public function lernender_pruefungen_abfragenzahl_unabhaengig_von_datenmenge(): void
    {
        $betrieb = $this->betrieb();
        $semester = $this->semesterReihe(2);
        $user = User::factory()->lernender([
            'lehrberuf_id' => $betrieb->lehrberuf->lehrberuf_id,
            'lehrbeginn' => $semester->first()->start_datum,
            'lehrende' => $semester->last()->end_datum,
        ])->create();
        $lernender = $user->lernender;
        $this->pruefungen($betrieb, $lernender, 4);

        $klein = $this->abfragenFuer(fn () => $this->actingAs($user)->get(route('learner.exams.index'))->assertOk());

        $this->pruefungen($betrieb, $lernender, 16);

        $gross = $this->abfragenFuer(fn () => $this->actingAs($user)->get(route('learner.exams.index'))->assertOk());

        $this->assertWaechstNicht($klein, $gross, 40, 'Lernender-Prüfungen');
    }

    #[Test]
    public function lernender_druckansicht_abfragenzahl_unabhaengig_von_datenmenge(): void
    {
        $betrieb = $this->betrieb();
        $semester = $this->semesterReihe(2);
        $user = User::factory()->lernender([
            'lehrberuf_id' => $betrieb->lehrberuf->lehrberuf_id,
            'lehrbeginn' => $semester->first()->start_datum,
            'lehrende' => $semester->last()->end_datum,
        ])->create();
        $lernender = $user->lernender;
        $this->noten($betrieb, $lernender, $semester, 5);

        $klein = $this->abfragenFuer(fn () => $this->actingAs($user)->get(route('learner.grades.print'))->assertOk());

        $semesterGross = $this->semesterReihe(8, Carbon::parse($semester->last()->end_datum)->addDay(), $semester->count() + 1);
        $lernender->update(['lehrende' => $semesterGross->last()->end_datum]);
        $this->noten($betrieb, $lernender, $semester->concat($semesterGross), 20);

        $gross = $this->abfragenFuer(fn () => $this->actingAs($user)->get(route('learner.grades.print'))->assertOk());

        $this->assertWaechstNicht($klein, $gross, 40, 'Lernender-Druckansicht');
    }

    // --- Berufsbildner: Dashboard, Lernenden-Detail ------------------------------------------

    #[Test]
    public function berufsbildner_dashboard_abfragenzahl_unabhaengig_von_datenmenge(): void
    {
        $betrieb = $this->betrieb();
        $semester = $this->semesterReihe(2);
        $bbUser = User::factory()->berufsbildner()->create();
        $bbId = $bbUser->berufsbildner->berufsbildner_id;

        $macheLernende = function (int $anzahl) use ($betrieb, $semester, $bbId) {
            for ($i = 0; $i < $anzahl; $i++) {
                $lernender = $this->lernender($betrieb, Carbon::parse($semester->first()->start_datum), Carbon::parse($semester->last()->end_datum));
                Betreuung::factory()->create(['berufsbildner_id' => $bbId, 'lernender_id' => $lernender->lernender_id]);
                $this->noten($betrieb, $lernender, $semester, 3);
                $this->pruefungen($betrieb, $lernender, 1);
            }
        };

        $macheLernende(3);
        $klein = $this->abfragenFuer(fn () => $this->actingAs($bbUser)->get(route('trainer.dashboard'))->assertOk());

        $macheLernende(12);
        $gross = $this->abfragenFuer(fn () => $this->actingAs($bbUser)->get(route('trainer.dashboard'))->assertOk());

        $this->assertWaechstNicht($klein, $gross, 40, 'Berufsbildner-Dashboard');
    }

    #[Test]
    public function lernenden_detail_abfragenzahl_unabhaengig_von_datenmenge(): void
    {
        $betrieb = $this->betrieb();
        $semester = $this->semesterReihe(2);
        $bbUser = User::factory()->berufsbildner()->create();
        $bbId = $bbUser->berufsbildner->berufsbildner_id;

        $lernender = $this->lernender($betrieb, Carbon::parse($semester->first()->start_datum), Carbon::parse($semester->last()->end_datum));
        Betreuung::factory()->create(['berufsbildner_id' => $bbId, 'lernender_id' => $lernender->lernender_id]);
        $this->noten($betrieb, $lernender, $semester, 5);
        $this->pruefungen($betrieb, $lernender, 2);

        $klein = $this->abfragenFuer(fn () => $this->actingAs($bbUser)
            ->get(route('trainer.learners.show', $lernender->lernender_id))->assertOk());

        $semesterGross = $this->semesterReihe(8, Carbon::parse($semester->last()->end_datum)->addDay(), $semester->count() + 1);
        $lernender->update(['lehrende' => $semesterGross->last()->end_datum]);
        $this->noten($betrieb, $lernender, $semester->concat($semesterGross), 20);
        $this->pruefungen($betrieb, $lernender, 8);

        $gross = $this->abfragenFuer(fn () => $this->actingAs($bbUser)
            ->get(route('trainer.learners.show', $lernender->lernender_id))->assertOk());

        $this->assertWaechstNicht($klein, $gross, 60, 'Lernenden-Detail');
    }

    #[Test]
    public function berufsbildner_pruefungstermine_abfragenzahl_unabhaengig_von_datenmenge(): void
    {
        $betrieb = $this->betrieb();
        $semester = $this->semesterReihe(2);
        $bbUser = User::factory()->berufsbildner()->create();
        $bbId = $bbUser->berufsbildner->berufsbildner_id;

        $macheLernende = function (int $anzahl) use ($betrieb, $semester, $bbId) {
            for ($i = 0; $i < $anzahl; $i++) {
                $lernender = $this->lernender($betrieb, Carbon::parse($semester->first()->start_datum), Carbon::parse($semester->last()->end_datum));
                Betreuung::factory()->create(['berufsbildner_id' => $bbId, 'lernender_id' => $lernender->lernender_id]);
                $this->pruefungen($betrieb, $lernender, 2);
            }
        };

        $macheLernende(3);
        $klein = $this->abfragenFuer(fn () => $this->actingAs($bbUser)->get(route('trainer.exams.index'))->assertOk());

        $macheLernende(12);
        $gross = $this->abfragenFuer(fn () => $this->actingAs($bbUser)->get(route('trainer.exams.index'))->assertOk());

        $this->assertWaechstNicht($klein, $gross, 40, 'Berufsbildner-Prüfungstermine');
    }

    // --- Admin: Dashboard, Lernende, Berichte, Mail-Log --------------------------------------

    #[Test]
    public function admin_dashboard_abfragenzahl_unabhaengig_von_datenmenge(): void
    {
        $betrieb = $this->betrieb();
        $semester = $this->semesterReihe(2);
        $admin = User::factory()->admin()->create();

        $macheBetrieb = function (int $anzahlLernende, int $anzahlBb) use ($betrieb, $semester) {
            for ($i = 0; $i < $anzahlBb; $i++) {
                $bbUser = User::factory()->berufsbildner()->create();
                Betreuung::factory()->create([
                    'berufsbildner_id' => $bbUser->berufsbildner->berufsbildner_id,
                    'lernender_id' => $this->lernender($betrieb, Carbon::parse($semester->first()->start_datum), Carbon::parse($semester->last()->end_datum))->lernender_id,
                ]);
            }
            for ($i = 0; $i < $anzahlLernende; $i++) {
                $lernender = $this->lernender($betrieb, Carbon::parse($semester->first()->start_datum), Carbon::parse($semester->last()->end_datum));
                $this->noten($betrieb, $lernender, $semester, 3);
            }
        };

        $macheBetrieb(3, 2);
        $klein = $this->abfragenFuer(fn () => $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk());

        $macheBetrieb(12, 8);
        $gross = $this->abfragenFuer(fn () => $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk());

        $this->assertWaechstNicht($klein, $gross, 40, 'Admin-Dashboard');
    }

    #[Test]
    public function admin_lernende_abfragenzahl_unabhaengig_von_datenmenge(): void
    {
        $betrieb = $this->betrieb();
        $semester = $this->semesterReihe(2);
        $admin = User::factory()->admin()->create();
        $bbUser = User::factory()->berufsbildner()->create();

        $macheLernende = function (int $anzahl) use ($betrieb, $semester, $bbUser) {
            for ($i = 0; $i < $anzahl; $i++) {
                $lernender = $this->lernender($betrieb, Carbon::parse($semester->first()->start_datum), Carbon::parse($semester->last()->end_datum));
                Betreuung::factory()->create(['berufsbildner_id' => $bbUser->berufsbildner->berufsbildner_id, 'lernender_id' => $lernender->lernender_id]);
                $this->noten($betrieb, $lernender, $semester, 3);
            }
        };

        $macheLernende(4);
        $klein = $this->abfragenFuer(fn () => $this->actingAs($admin)->get(route('admin.learners.index'))->assertOk());

        $macheLernende(16);
        $gross = $this->abfragenFuer(fn () => $this->actingAs($admin)->get(route('admin.learners.index'))->assertOk());

        $this->assertWaechstNicht($klein, $gross, 40, 'Admin-Lernende');
    }

    #[Test]
    public function admin_berichte_abfragenzahl_unabhaengig_von_datenmenge(): void
    {
        $betrieb = $this->betrieb();
        $semester = $this->semesterReihe(2);
        $admin = User::factory()->admin()->create();

        $macheLernende = function (int $anzahl) use ($betrieb, $semester) {
            for ($i = 0; $i < $anzahl; $i++) {
                $lernender = $this->lernender($betrieb, Carbon::parse($semester->first()->start_datum), Carbon::parse($semester->last()->end_datum));
                $this->noten($betrieb, $lernender, $semester, 3);
            }
        };

        $macheLernende(4);
        $klein = $this->abfragenFuer(fn () => $this->actingAs($admin)->get(route('admin.reports.grades'))->assertOk());

        $macheLernende(16);
        $gross = $this->abfragenFuer(fn () => $this->actingAs($admin)->get(route('admin.reports.grades'))->assertOk());

        $this->assertWaechstNicht($klein, $gross, 40, 'Admin-Berichte');
    }

    #[Test]
    public function admin_mail_log_abfragenzahl_unabhaengig_von_datenmenge(): void
    {
        $admin = User::factory()->admin()->create();

        $macheMails = function (int $anzahl) {
            $rows = [];
            for ($i = 0; $i < $anzahl; $i++) {
                $rows[] = [
                    'type' => 'test',
                    'recipient' => 'empfaenger'.uniqid().'@example.test',
                    'subject' => 'Betreff '.$i,
                    'status' => MailLog::SENT,
                    'created_at' => now(),
                    'sent_at' => now(),
                ];
            }
            DB::table('mail_log')->insert($rows);
        };

        $macheMails(10);
        $klein = $this->abfragenFuer(fn () => $this->actingAs($admin)->get(route('admin.mail-log.index'))->assertOk());

        $macheMails(90);
        $gross = $this->abfragenFuer(fn () => $this->actingAs($admin)->get(route('admin.mail-log.index'))->assertOk());

        $this->assertWaechstNicht($klein, $gross, 40, 'Admin-Mail-Log');
    }

    #[Test]
    public function admin_aktivitaeten_abfragenzahl_unabhaengig_von_datenmenge(): void
    {
        $admin = User::factory()->admin()->create();
        $akteure = User::factory()->admin()->count(5)->create()->pluck('benutzer_id')->all();

        $macheEintraege = function (int $anzahl) use ($akteure) {
            $rows = [];
            for ($i = 0; $i < $anzahl; $i++) {
                $rows[] = [
                    'benutzer_id' => $akteure[$i % count($akteure)],
                    'aktion' => Protokoll::ADMIN_KONTO_ANGELEGT,
                    'ziel_typ' => null,
                    'ziel_id' => null,
                    'ziel_bezeichnung' => 'Test '.uniqid(),
                    'details' => null,
                    'ip' => '127.0.0.1',
                    'erstellt_am' => now(),
                ];
            }
            DB::table('aktivitaeten')->insert($rows);
        };

        $macheEintraege(10);
        $klein = $this->abfragenFuer(fn () => $this->actingAs($admin)->get(route('admin.activity.index'))->assertOk());

        $macheEintraege(90);
        $gross = $this->abfragenFuer(fn () => $this->actingAs($admin)->get(route('admin.activity.index'))->assertOk());

        $this->assertWaechstNicht($klein, $gross, 40, 'Admin-Aktivitätsprotokoll');
    }

    #[Test]
    public function admin_feedback_abfragenzahl_unabhaengig_von_datenmenge(): void
    {
        $admin = User::factory()->admin()->create();

        $macheMeldungen = function (int $anzahl) {
            for ($i = 0; $i < $anzahl; $i++) {
                $original = Feedback::factory()->create();
                $duplikat = Feedback::factory()->duplikatVon($original->feedback_id)->create();
                FeedbackStimme::query()->insert([
                    'feedback_id' => $original->feedback_id,
                    'benutzer_id' => User::factory()->lernender()->create()->benutzer_id,
                    'erstellt_am' => now(),
                ]);
            }
        };

        $macheMeldungen(5);
        $klein = $this->abfragenFuer(fn () => $this->actingAs($admin)->get(route('admin.feedback.index'))->assertOk());

        $macheMeldungen(20);
        $gross = $this->abfragenFuer(fn () => $this->actingAs($admin)->get(route('admin.feedback.index'))->assertOk());

        $this->assertWaechstNicht($klein, $gross, 25, 'Admin-Feedback-Liste');
    }
}
