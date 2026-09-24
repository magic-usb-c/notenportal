<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Datenauskunft;
use App\Support\Protokoll;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/** Datenauskunft (Art. 25 DSG) für ein beliebiges Konto, ausgelöst vom Admin. */
class DatenauskunftController extends Controller
{
    public function __construct(private readonly Datenauskunft $datenauskunft) {}

    public function zeigen(Request $request, int $benutzer_id): BinaryFileResponse|RedirectResponse
    {
        $user = User::withTrashed()->findOrFail($benutzer_id);

        if ($this->datenauskunft->zuGross($user)) {
            return back()->with('error', __('Die Datenauskunft ist zu gross zum Herunterladen. Bitte bei einem Admin melden.'));
        }

        try {
            $pfad = $this->datenauskunft->erzeugen($user);
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', __('Die Datenauskunft liess sich nicht erstellen. Bitte später erneut versuchen.'));
        }

        Log::info('Datenauskunft erstellt', ['benutzer_id' => $user->benutzer_id, 'ausgeloest_von' => $request->user()->benutzer_id]);
        Protokoll::schreiben(Protokoll::ADMIN_DATENAUSKUNFT_ERSTELLT, $user);

        return response()->download($pfad, $this->datenauskunft->dateiname($user))->deleteFileAfterSend();
    }
}
