<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Aktivitaet;
use App\Support\Protokoll;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Aktivitätsprotokoll (Audit-Log): Liste, neueste zuerst, mit Filtern. */
class AktivitaetController extends Controller
{
    public function index(Request $request): View
    {
        // Filter sind reine GET-Parameter (keine echte Formulareingabe mit Fehlermeldung) – ungültige
        // Werte werden hier über valid() still ignoriert statt einen 422/Redirect-Loop zu erzeugen.
        $validiert = validator($request->query(), [
            'aktion' => ['nullable', 'string', Rule::in(array_keys(Protokoll::LABELS))],
            'person' => ['nullable', 'string', 'max:100'],
            'von' => ['nullable', 'date_format:Y-m-d'],
            'bis' => ['nullable', 'date_format:Y-m-d'],
        ])->valid();

        $aktion = (string) ($validiert['aktion'] ?? '');
        $person = trim((string) ($validiert['person'] ?? ''));
        $von = (string) ($validiert['von'] ?? '');
        $bis = (string) ($validiert['bis'] ?? '');
        $personEscaped = addcslashes($person, '%_\\');

        $eintraege = Protokoll::verfuegbar()
            ? Aktivitaet::query()
                ->with('benutzer')
                ->when($aktion !== '', fn ($q) => $q->where('aktion', $aktion))
                ->when($person !== '', fn ($q) => $q->whereHas('benutzer', fn ($qq) => $qq
                    ->where('vorname', 'like', '%'.$personEscaped.'%')
                    ->orWhere('nachname', 'like', '%'.$personEscaped.'%')
                    ->orWhere('email', 'like', '%'.$personEscaped.'%')))
                ->when($von !== '', fn ($q) => $q->where('erstellt_am', '>=', Carbon::parse($von)->startOfDay()))
                ->when($bis !== '', fn ($q) => $q->where('erstellt_am', '<=', Carbon::parse($bis)->endOfDay()))
                ->orderByDesc('erstellt_am')
                ->paginate(50)
                ->withQueryString()
            : new LengthAwarePaginator([], 0, 50);

        return view('admin.aktivitaeten.index', [
            'eintraege' => $eintraege,
            'aktion' => $aktion,
            'person' => $person,
            'von' => $von,
            'bis' => $bis,
            'aktionen' => Protokoll::LABELS,
        ]);
    }
}
