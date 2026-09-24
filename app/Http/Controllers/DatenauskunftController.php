<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Datenauskunft;
use App\Support\Protokoll;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/** Eigene Datenauskunft (Art. 25 DSG): ZIP mit allen Daten zum eigenen Konto. */
class DatenauskunftController extends Controller
{
    public function __construct(private readonly Datenauskunft $datenauskunft) {}

    public function eigene(Request $request): BinaryFileResponse|RedirectResponse
    {
        $user = $request->user();

        if ($this->datenauskunft->zuGross($user)) {
            return back()->with('error', __('Die Datenauskunft ist zu gross zum Herunterladen. Bitte bei einem Admin melden.'));
        }

        try {
            $pfad = $this->datenauskunft->erzeugen($user);
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', __('Die Datenauskunft liess sich nicht erstellen. Bitte später erneut versuchen.'));
        }

        Log::info('Datenauskunft erstellt', ['benutzer_id' => $user->benutzer_id]);
        Protokoll::schreiben(Protokoll::AUTH_DATENAUSKUNFT_EIGENE, $user);

        return response()->download($pfad, $this->datenauskunft->dateiname($user))->deleteFileAfterSend();
    }
}
