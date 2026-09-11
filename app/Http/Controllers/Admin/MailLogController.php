<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MailLog;
use App\Services\Notifications\NotificationCatalog;
use App\Services\Notifications\Notifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Versandprotokoll: Kennzahlen, Filter, erneuter Versand. */
class MailLogController extends Controller
{
    public function index(Request $request): View
    {
        $status = (string) $request->input('status', '');
        $type = (string) $request->input('type', '');
        $q = trim((string) $request->input('q', ''));

        $seit = now()->subDays(7);
        $kennzahlen = [
            'verschickt' => MailLog::where('status', MailLog::SENT)->where('created_at', '>=', $seit)->count(),
            'fehlgeschlagen' => MailLog::where('status', MailLog::FAILED)->where('created_at', '>=', $seit)->count(),
            'nicht_zustellbar' => MailLog::where('status', MailLog::SKIPPED)->where('created_at', '>=', $seit)->count(),
            'warteschlange' => DB::table('jobs')->count() + DB::table('failed_jobs')->count(),
        ];

        $eintraege = MailLog::query()
            ->when($status !== '', fn ($qq) => $qq->where('status', $status))
            ->when($type !== '', fn ($qq) => $qq->where('type', $type))
            ->when($q !== '', fn ($qq) => $qq->where(fn ($qqq) => $qqq
                ->where('recipient', 'like', '%'.$q.'%')
                ->orWhere('redirected_to', 'like', '%'.$q.'%')))
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        return view('admin.mail-log.index', [
            'eintraege' => $eintraege,
            'kennzahlen' => $kennzahlen,
            'status' => $status,
            'type' => $type,
            'q' => $q,
            'anlaesse' => $this->anlaesse(),
        ]);
    }

    public function retry(int $id): RedirectResponse
    {
        $log = MailLog::findOrFail($id);

        if (! in_array($log->status, [MailLog::FAILED, MailLog::SKIPPED], true)) {
            return redirect()->route('admin.mail-log.index')
                ->with('error', __('Nur fehlgeschlagene oder nicht zustellbare Mails können erneut gesendet werden.'));
        }

        try {
            Notifier::retry($log);
        } catch (\RuntimeException $e) {
            return redirect()->route('admin.mail-log.index')->with('error', __($e->getMessage()));
        }

        return redirect()->route('admin.mail-log.index')->with('success', __('Erneut in die Warteschlange gestellt.'));
    }

    /** @return array<string, string> */
    private function anlaesse(): array
    {
        $liste = [];
        foreach (NotificationCatalog::all() as $type => $def) {
            $liste[$type] = $def['label'];
        }
        $liste[NotificationCatalog::TEST] = __('Testmail');
        $liste[NotificationCatalog::DAILY_DIGEST] = __('Tageszusammenfassung');

        return $liste;
    }
}
