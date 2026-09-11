<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use App\Services\Feedback\Screenshot;
use App\Services\Notifications\Messages\FeedbackAnswered;
use App\Services\Notifications\NotificationCatalog;
use App\Services\Notifications\Notifier;
use App\Support\Csv;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FeedbackController extends Controller
{
    public function index(Request $request): View
    {
        $status = (string) $request->input('status', '');
        $kategorie = (string) $request->input('kategorie', '');
        $rolle = (string) $request->input('rolle', '');

        $q = Feedback::query()
            ->join('benutzer as b', 'b.benutzer_id', '=', 'feedback.benutzer_id')
            ->select('feedback.*', 'b.vorname', 'b.nachname', 'b.email')
            ->selectRaw(
                '(SELECT GROUP_CONCAT(r.name SEPARATOR ", ") FROM benutzer_rollen br '.
                'JOIN rollen r ON r.rolle_id = br.rolle_id WHERE br.benutzer_id = feedback.benutzer_id) as rollen'
            )
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
            })
            ->orderByDesc('feedback.erstellt_am');

        $meldungen = $q->paginate(25)->withQueryString();

        return view('admin.feedback.index', compact('meldungen', 'status', 'kategorie', 'rolle'));
    }

    public function update(Request $request, int $feedback_id): JsonResponse|RedirectResponse
    {
        $feedback = Feedback::query()->with('benutzer')->findOrFail($feedback_id);

        $validated = $request->validate([
            'status' => ['required', 'in:'.implode(',', array_keys(Feedback::STATUS))],
            'admin_notiz' => ['nullable', 'string', 'max:5000'],
        ]);

        $geaendert = $feedback->status !== $validated['status'] || $feedback->admin_notiz !== ($validated['admin_notiz'] ?? null);

        $feedback->status = $validated['status'];
        $feedback->admin_notiz = $validated['admin_notiz'] ?? null;
        $feedback->erledigt_am = $validated['status'] === Feedback::STATUS_ERLEDIGT ? now() : null;
        $feedback->save();

        if ($geaendert && $feedback->benutzer) {
            Notifier::send($feedback->benutzer, NotificationCatalog::FEEDBACK_ANSWERED, FeedbackAnswered::content($feedback));
        }

        if ($request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'status' => $feedback->status,
                'status_label' => Feedback::STATUS[$feedback->status] ?? $feedback->status,
                'erledigt_am' => $feedback->erledigt_am?->format('d.m.Y H:i'),
            ]);
        }

        return redirect()->back()->with('success', 'Status aktualisiert.');
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
            fputcsv($out, ['Datum', 'Nachname', 'Vorname', 'E-Mail', 'Rolle', 'Kategorie', 'Status', 'Text', 'Route', 'URL', 'Viewport', 'Browser'], ';');

            foreach ($rows as $r) {
                fputcsv($out, [
                    $r->erstellt_am ? Carbon::parse($r->erstellt_am)->format('d.m.Y H:i') : '',
                    Csv::safe($r->nachname),
                    Csv::safe($r->vorname),
                    Csv::safe($r->email),
                    Csv::safe($r->rolle ?? ''),
                    Feedback::KATEGORIEN[$r->kategorie] ?? $r->kategorie,
                    Feedback::STATUS[$r->status] ?? $r->status,
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
