<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Datenauskunft;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

        $pfad = $this->datenauskunft->erzeugen($user);

        Log::info('Datenauskunft erstellt', ['benutzer_id' => $user->benutzer_id]);

        return response()->download($pfad, $this->datenauskunft->dateiname($user))->deleteFileAfterSend();
    }
}
