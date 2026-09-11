<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use App\Models\FeedbackStimme;
use App\Services\Feedback\Screenshot;
use App\Services\Notifications\Messages\FeedbackAnswered;
use App\Services\Notifications\NotificationCatalog;
use App\Services\Notifications\Notifier;
use App\Support\Csv;
use App\Support\Protokoll;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FeedbackController extends Controller
{
    public function index(Request $request): View
    {
        $status = (string) $request->input('status', '');
        $kategorie = (string) $request->input('kategorie', '');
        $rolle = (string) $request->input('rolle', '');
        $sort = in_array($request->input('sort'), ['datum', 'stimmen'], true) ? $request->input('sort') : 'datum';
        $dir = $request->input('dir') === 'asc' ? 'asc' : 'desc';
        $duplikate = $request->boolean('duplikate');
        $hatDuplikatSpalte = Feedback::hatDuplikatSpalte();
        $hatStimmenTabelle = FeedbackStimme::tabelleVorhanden();

        $q = Feedback::query()
            ->join('benutzer as b', 'b.benutzer_id', '=', 'feedback.benutzer_id')
            ->select('feedback.*', 'b.vorname', 'b.nachname', 'b.email')
            ->selectRaw(
                '(SELECT GROUP_CONCAT(r.name SEPARATOR ", ") FROM benutzer_rollen br '.
                'JOIN rollen r ON r.rolle_id = br.rolle_id WHERE br.benutzer_id = feedback.benutzer_id) as rollen'
            )
            ->selectRaw($hatStimmenTabelle
                ? '(SELECT COUNT(*) FROM feedback_stimmen fs WHERE fs.feedback_id = feedback.feedback_id) as stimmen_anzahl'
                : '0 as stimmen_anzahl')
            ->when($hatDuplikatSpalte, fn ($qq) => $qq->selectRaw(
                '(SELECT COUNT(*) FROM feedback d WHERE d.duplikat_von = feedback.feedback_id) as duplikate_anzahl'
            ))
            ->when($hatDuplikatSpalte && ! $duplikate, fn ($qq) => $qq->whereNull('feedback.duplikat_von'))
            ->when($status !== '', fn ($qq) => $qq->where('feedback.status', $status))
            ->when($kategorie !== '', fn ($qq) => $qq->where('feedback.kategorie', $kategorie))
            ->when($rolle !== '', function ($qq) use ($rolle) {
                $qq->whereExists(function ($sub) use ($rolle) {
                    $sub->select(DB::raw(1))
                        ->from('benutzer_rollen as br')
                        ->join('rollen as r', 'r.rolle_id', '=', 'br.rolle_id')
                        ->whereColumn('br.benutzer_id', 'feedback.benutzer_id')
                        ->whereRaw('LOWER(r.name) = LOWER(?)', [$rolle]);
                });
            });

        if ($sort === 'stimmen' && $hatStimmenTabelle) {
            $q->orderByDesc('stimmen_anzahl')->orderByDesc('feedback.erstellt_am');
        } else {
            $q->orderBy('feedback.erstellt_am', $dir);
        }

        $meldungen = $q->paginate(25)->withQueryString();
        $gibtEs = $meldungen->total() > 0 || Feedback::exists();

        return view('admin.feedback.index', compact('meldungen', 'status', 'kategorie', 'rolle', 'sort', 'dir', 'duplikate', 'gibtEs', 'hatDuplikatSpalte'));
    }

    public function update(Request $request, int $feedback_id): JsonResponse|RedirectResponse
    {
        $mitDuplikaten = Feedback::hatDuplikatSpalte();
        $feedback = Feedback::query()
            ->with($mitDuplikaten ? ['benutzer', 'duplikate.benutzer'] : ['benutzer'])
            ->findOrFail($feedback_id);

        $validated = $request->validate([
            'status' => ['required', 'in:'.implode(',', array_keys(Feedback::STATUS))],
            'admin_notiz' => ['nullable', 'string', 'max:5000'],
        ]);

        $geaendert = $feedback->status !== $validated['status'] || $feedback->admin_notiz !== ($validated['admin_notiz'] ?? null);

        $feedback->status = $validated['status'];
        $feedback->admin_notiz = $validated['admin_notiz'] ?? null;
        $feedback->erledigt_am = $validated['status'] === Feedback::STATUS_ERLEDIGT ? now() : null;
        $feedback->save();

        // Hat die Hauptmeldung Duplikate, übernehmen sie Status, erledigt_am und admin_notiz.
        $duplikate = $mitDuplikaten ? $feedback->duplikate : collect();
        if ($geaendert && $duplikate->isNotEmpty()) {
            Feedback::query()->where('duplikat_von', $feedback->feedback_id)->update([
                'status' => $feedback->status,
                'admin_notiz' => $feedback->admin_notiz,
                'erledigt_am' => $feedback->erledigt_am,
            ]);
        }

        if ($geaendert && $feedback->benutzer) {
            Notifier::send($feedback->benutzer, NotificationCatalog::FEEDBACK_ANSWERED, fn () => FeedbackAnswered::content($feedback));
        }

        // Jeder Absender eines Duplikats bekommt ebenfalls Bescheid – ausser er hat auch das Original gemeldet.
        if ($geaendert) {
            foreach ($duplikate as $duplikat) {
                if (! $duplikat->benutzer) {
                    continue;
                }
                if ($feedback->benutzer && (int) $duplikat->benutzer_id === (int) $feedback->benutzer_id) {
                    continue;
                }

                $duplikat->status = $feedback->status;
                $duplikat->admin_notiz = $feedback->admin_notiz;
                $duplikat->erledigt_am = $feedback->erledigt_am;
                Notifier::send($duplikat->benutzer, NotificationCatalog::FEEDBACK_ANSWERED, fn () => FeedbackAnswered::content($duplikat));
            }
        }

        if ($request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'status' => $feedback->status,
                'status_label' => __(Feedback::STATUS[$feedback->status] ?? $feedback->status),
                'erledigt_am' => $feedback->erledigt_am?->format('d.m.Y H:i'),
            ]);
        }

        return redirect()->back()->with('success', __('Status aktualisiert.'));
    }

    /**
     * Meldung als Duplikat einer anderen markieren (duplikat_von), Stimmen und Absender aufs Original
     * übernehmen. Zeigt das gewählte Ziel selbst schon auf ein Original, wird dieses verwendet, damit
     * keine Ketten entstehen. `duplikat_von: null` hebt die Markierung wieder auf.
     */
    public function duplikat(Request $request, int $feedback_id): JsonResponse|RedirectResponse
    {
        abort_unless(Feedback::hatDuplikatSpalte(), 404);

        $feedback = Feedback::query()->findOrFail($feedback_id);

        $validated = $request->validate([
            'duplikat_von' => ['nullable', 'integer', 'exists:feedback,feedback_id'],
        ]);
        $zielId = $validated['duplikat_von'] ?? null;

        if ($zielId !== null && (int) $zielId === $feedback_id) {
            throw ValidationException::withMessages(['duplikat_von' => __('Duplikat kann nicht auf sich selbst zeigen.')]);
        }

        DB::transaction(function () use (&$feedback, $zielId, $feedback_id) {
            // Auch die Quelle sperren: zwei gleichzeitige Markierungen (C → A, A → B) ergäben sonst eine Kette.
            $feedback = Feedback::query()->lockForUpdate()->findOrFail($feedback_id);

            if ($zielId === null) {
                $feedback->duplikat_von = null;
                $feedback->save();
                Protokoll::schreiben(Protokoll::ADMIN_FEEDBACK_DUPLIKAT_AUFGEHOBEN, $feedback);

                return;
            }

            $ziel = Feedback::query()->lockForUpdate()->findOrFail($zielId);
            if ($ziel->istDuplikat()) {
                $ziel = Feedback::query()->lockForUpdate()->findOrFail($ziel->duplikat_von);
            }

            if ($ziel->feedback_id === $feedback_id) {
                throw ValidationException::withMessages(['duplikat_von' => __('Duplikat kann nicht auf sich selbst zeigen.')]);
            }

            // Bestehende Duplikate dieser Meldung auf das neue Original umhängen (Ketten auflösen).
            Feedback::query()->where('duplikat_von', $feedback_id)->update(['duplikat_von' => $ziel->feedback_id]);

            $feedback->duplikat_von = $ziel->feedback_id;
            $feedback->save();

            DB::statement(
                'INSERT IGNORE INTO feedback_stimmen (feedback_id, benutzer_id, erstellt_am) '.
                'SELECT ?, benutzer_id, NOW() FROM feedback_stimmen WHERE feedback_id = ?',
                [$ziel->feedback_id, $feedback_id]
            );

            if ((int) $feedback->benutzer_id !== (int) $ziel->benutzer_id) {
                FeedbackStimme::query()->insertOrIgnore([
                    'feedback_id' => $ziel->feedback_id,
                    'benutzer_id' => $feedback->benutzer_id,
                    'erstellt_am' => now(),
                ]);
            }

            $feedback->status = $ziel->status;
            $feedback->erledigt_am = $ziel->erledigt_am;
            $feedback->save();

            Protokoll::schreiben(Protokoll::ADMIN_FEEDBACK_DUPLIKAT, $feedback, ['original' => $ziel->feedback_id]);
        });

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'duplikat_von' => $feedback->fresh()->duplikat_von]);
        }

        return redirect()->back()->with('success', __('Gespeichert.'));
    }

    /** Screenshot einer Meldung – nur für Admins, nie öffentlich oder für die meldende Person. */
    public function screenshot(int $feedback_id, Screenshot $screenshotService): StreamedResponse
    {
        $feedback = Feedback::query()->findOrFail($feedback_id);
        abort_unless($feedback->hatScreenshot(), 404);

        return $screenshotService->ausliefern($feedback);
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = Feedback::query()
            ->join('benutzer as b', 'b.benutzer_id', '=', 'feedback.benutzer_id')
            ->orderByDesc('feedback.erstellt_am')
            ->select([
                'feedback.erstellt_am',
                'b.nachname', 'b.vorname', 'b.email',
                'feedback.rolle', 'feedback.kategorie', 'feedback.status',
                'feedback.text', 'feedback.route_name', 'feedback.url',
                'feedback.viewport', 'feedback.browser',
            ])
            ->get();

        $filename = 'feedback_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [__('Datum'), __('Nachname'), __('Vorname'), __('E-Mail'), __('Rolle'), __('Kategorie'), __('Status'), __('Text'), __('Route'), __('URL'), __('Viewport'), __('Browser')], ';');

            foreach ($rows as $r) {
                fputcsv($out, [
                    $r->erstellt_am ? Carbon::parse($r->erstellt_am)->format('d.m.Y H:i') : '',
                    Csv::safe($r->nachname),
                    Csv::safe($r->vorname),
                    Csv::safe($r->email),
                    Csv::safe($r->rolle ?? ''),
                    __(Feedback::KATEGORIEN[$r->kategorie] ?? $r->kategorie),
                    __(Feedback::STATUS[$r->status] ?? $r->status),
                    Csv::safe($r->text),
                    Csv::safe($r->route_name ?? ''),
                    Csv::safe($r->url ?? ''),
                    Csv::safe($r->viewport ?? ''),
                    Csv::safe($r->browser ?? ''),
                ], ';');
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
