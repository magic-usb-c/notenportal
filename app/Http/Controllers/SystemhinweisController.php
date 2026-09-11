<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\NotificationMark;
use App\Support\Einstellungen;
use App\Support\Systemhinweis;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SystemhinweisController extends Controller
{
    /** Wegklicken (pro Benutzer, dauerhaft) – nimmt nur die aktuell gültige Version an. */
    public function schliessen(Request $request): Response|JsonResponse
    {
        $version = (string) $request->input('version');
        $aktuelleVersion = (string) Einstellungen::get(Einstellungen::HINWEIS_VERSION, '');

        if ($version === '' || $aktuelleVersion === '' || $version !== $aktuelleVersion) {
            return response()->json(['message' => __('Ungültige Version.')], 422);
        }

        NotificationMark::query()->insertOrIgnore([
            'user_id' => (int) $request->user()->benutzer_id,
            'type' => Systemhinweis::TYP,
            'subject_key' => $version,
            'created_at' => now(),
        ]);

        return response()->noContent();
    }
}
