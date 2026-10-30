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
        $status = is_string($status = $request->input('status')) ? $status : '';
        $type = is_string($type = $request->input('type')) ? $type : '';
        $q = is_string($q = $request->input('q')) ? trim($q) : '';

        $seit = now()->subDays(7);
        $kennzahlen = [
            'verschickt' => MailLog::where('status', MailLog::SENT)->where('created_at', '>=', $seit)->count(),
            'fehlgeschlagen' => MailLog::where('status', MailLog::FAILED)->where('created_at', '>=', $seit)->count(),
            'nicht_zustellbar' => MailLog::where('status', MailLog::SKIPPED)->where('created_at', '>=', $seit)->count(),
            'warteschlange' => DB::table('jobs')->count() + DB::table('failed_jobs')->where('failed_at', '>=', $seit)->count(),
        ];

        $eintraege = MailLog::query()
            ->when($status !== '', fn ($qq) => $qq->where('status', $status))
            ->when($type !== '', fn ($qq) => $qq->where('type', $type))
            ->when($q !== '', function ($qq) use ($q) {
                $like = '%'.addcslashes($q, '%_\\').'%';
                $qq->where(fn ($w) => $w->where('recipient', 'like', $like)->orWhere('redirected_to', 'like', $like));
            })
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        return view('admin.mail-log.index', [
            'eintraege' => $eintraege,
            'leer' => $eintraege->isEmpty() && ! MailLog::query()->exists(),
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
        $vorher = $log->status;

        // Zeile atomar beanspruchen: Doppelklick oder zweiter Admin kann dieselbe Mail nicht noch einmal auslösen
        $beansprucht = MailLog::whereKey($id)
            ->whereIn('status', [MailLog::FAILED, MailLog::SKIPPED])
            ->update(['status' => MailLog::RETRIED]);
        if ($beansprucht === 0) {
            return redirect()->route('admin.mail-log.index')
                ->with('error', __('Nur fehlgeschlagene oder nicht zustellbare Mails können erneut gesendet werden.'));
        }

        try {
            $neu = Notifier::retry($log);
        } catch (\Throwable $e) {
            MailLog::whereKey($id)->update(['status' => $vorher]);
            if (! $e instanceof \RuntimeException) {
                throw $e;
            }

            return redirect()->route('admin.mail-log.index')->with('error', $e->getCode() === Notifier::NICHT_ZUSTELLBAR
                ? __('Adresse nicht zustellbar – Mail-Umleitung setzen oder Adresse korrigieren.')
                : __($e->getMessage()));
        }

        if ($neu->status === MailLog::FAILED) {
            return redirect()->route('admin.mail-log.index')
                ->with('error', __('Erneuter Versand fehlgeschlagen: :fehler', ['fehler' => mb_substr((string) $neu->error, 0, 150)]));
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
