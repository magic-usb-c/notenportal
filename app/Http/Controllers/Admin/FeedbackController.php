<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
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
                '(SELECT GROUP_CONCAT(r.name SEPARATOR ", ") FROM benutzer_rollen br ' .
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
        $feedback = Feedback::query()->findOrFail($feedback_id);

        $validated = $request->validate([
            'status' => ['required', 'in:' . implode(',', array_keys(Feedback::STATUS))],
            'admin_notiz' => ['nullable', 'string', 'max:5000'],
        ]);

        $feedback->status = $validated['status'];
        $feedback->admin_notiz = $validated['admin_notiz'] ?? null;
        $feedback->erledigt_am = $validated['status'] === Feedback::STATUS_ERLEDIGT ? now() : null;
        $feedback->save();

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

    public function export(Request $request): StreamedResponse
    {
        $rows = Feedback::query()
            ->join('benutzer as b', 'b.benutzer_id', '=', 'feedback.benutzer_id')
            ->orderByDesc('feedback.erstellt_am')
            ->select([
                'feedback.erstellt_am',
                'b.nachname', 'b.vorname', 'b.email',
                'feedback.kategorie', 'feedback.status',
                'feedback.text', 'feedback.route_name', 'feedback.url',
                'feedback.viewport',
            ])
            ->get();

        $filename = 'feedback_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Datum', 'Nachname', 'Vorname', 'E-Mail', 'Kategorie', 'Status', 'Text', 'Route', 'URL', 'Viewport'], ';');

            foreach ($rows as $r) {
                fputcsv($out, [
                    $r->erstellt_am ? Carbon::parse($r->erstellt_am)->format('d.m.Y H:i') : '',
                    Csv::safe($r->nachname),
                    Csv::safe($r->vorname),
                    Csv::safe($r->email),
                    Feedback::KATEGORIEN[$r->kategorie] ?? $r->kategorie,
                    Feedback::STATUS[$r->status] ?? $r->status,
                    Csv::safe($r->text),
                    Csv::safe($r->route_name ?? ''),
                    Csv::safe($r->url ?? ''),
                    $r->viewport ?? '',
                ], ';');
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
