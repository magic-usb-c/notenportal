<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BenutzerController extends Controller
{
    public function index()
    {
        $benutzer = DB::table('benutzer as b')
            ->leftJoin('benutzer_rollen as br', 'br.benutzer_id', '=', 'b.benutzer_id')
            ->leftJoin('rollen as r', 'r.rolle_id', '=', 'br.rolle_id')
            ->whereNull('b.geloescht_am')
            ->select([
                'b.benutzer_id',
                'b.vorname',
                'b.nachname',
                'b.email',
                'b.benutzername',
                'b.aktiv',
                'b.erstellt_am',
                DB::raw('GROUP_CONCAT(r.name ORDER BY r.name SEPARATOR ", ") as rollen'),
            ])
            ->groupBy('b.benutzer_id', 'b.vorname', 'b.nachname', 'b.email', 'b.benutzername', 'b.aktiv', 'b.erstellt_am')
            ->orderBy('b.nachname')
            ->orderBy('b.vorname')
            ->get();

        return view('admin.benutzer.index', compact('benutzer'));
    }

    public function create()
    {
        $rollen     = DB::table('rollen')->orderBy('rolle_id')->get();
        $lehrberufe = DB::table('lehrberufe')->where('aktiv', 1)->orderBy('name')->get();
        $semester   = DB::table('semester')->orderBy('sortierung')->get();

        $berufsbildner = DB::table('berufsbildner as bb')
            ->join('benutzer as b', 'b.benutzer_id', '=', 'bb.benutzer_id')
            ->whereNull('bb.geloescht_am')
            ->whereNull('b.geloescht_am')
            ->where('b.aktiv', 1)
            ->select(['bb.berufsbildner_id', 'b.vorname', 'b.nachname', 'b.email'])
            ->orderBy('b.nachname')
            ->orderBy('b.vorname')
            ->get();

        return view('admin.benutzer.create', compact('rollen', 'lehrberufe', 'semester', 'berufsbildner'));
    }

    public function store(Request $request): RedirectResponse
    {
        $rolleId = (int) $request->input('rolle_id');

        $rules = [
            'vorname'      => ['required', 'string', 'max:100'],
            'nachname'     => ['required', 'string', 'max:100'],
            'email'        => ['required', 'email', 'max:255', 'unique:benutzer,email'],
            'benutzername' => ['required', 'string', 'max:50', 'unique:benutzer,benutzername', 'alpha_num'],
            'passwort'     => ['required', 'string', 'min:8', 'confirmed'],
            'rolle_id'     => ['required', 'integer', 'exists:rollen,rolle_id'],
        ];

        // Zusatzfelder für Lernende
        if ($rolleId === 3) {
            $rules['lehrberuf_id']    = ['required', 'integer', 'exists:lehrberufe,lehrberuf_id'];
            $rules['lehrbeginn']      = ['required', 'date'];
            $rules['berufsbildner_id'] = ['nullable', 'integer', 'exists:berufsbildner,berufsbildner_id'];
            $rules['track_typ']        = ['nullable', 'in:BMS,ABU'];
            $rules['track_semester_id'] = ['required_if:track_typ,BMS', 'required_if:track_typ,ABU',
                                           'nullable', 'integer', 'exists:semester,semester_id'];
        }

        $validated = $request->validate($rules);

        DB::transaction(function () use ($validated, $rolleId) {
            // 1) Benutzer anlegen
            $user = User::create([
                'vorname'       => $validated['vorname'],
                'nachname'      => $validated['nachname'],
                'email'         => $validated['email'],
                'benutzername'  => $validated['benutzername'],
                'passwort_hash' => $validated['passwort'],
                'aktiv'         => true,
            ]);

            $benutzerId = (int) $user->benutzer_id;

            // 2) Rolle zuweisen
            DB::table('benutzer_rollen')->insert([
                'benutzer_id' => $benutzerId,
                'rolle_id'    => $rolleId,
            ]);

            // 3) Typ-spezifische Profil-Einträge
            if ($rolleId === 3) {
                // Lernender-Profil
                $lernenderId = DB::table('lernende')->insertGetId([
                    'benutzer_id'     => $benutzerId,
                    'lehrberuf_id'    => $validated['lehrberuf_id'],
                    'lehrbeginn'      => $validated['lehrbeginn'],
                    'erstellt_am'     => now(),
                    'aktualisiert_am' => now(),
                ]);

                // Optional: Berufsbildner zuweisen
                if (!empty($validated['berufsbildner_id'])) {
                    DB::table('betreuungen')->insert([
                        'lernender_id'     => $lernenderId,
                        'berufsbildner_id' => $validated['berufsbildner_id'],
                        'gueltig_von'      => $validated['lehrbeginn'],
                        'gueltig_bis'      => null,
                    ]);
                }

                // Optional: BMS/ABU-Track anlegen
                if (!empty($validated['track_typ'])) {
                    DB::table('lernender_tracks')->insert([
                        'lernender_id'      => $lernenderId,
                        'track_typ'         => $validated['track_typ'],
                        'start_datum'       => $validated['lehrbeginn'],
                        'end_datum'         => null,
                        'start_semester_id' => $validated['track_semester_id'],
                        'end_semester_id'   => null,
                    ]);
                }

            } elseif ($rolleId === 2) {
                // Berufsbildner-Profil
                DB::table('berufsbildner')->insert([
                    'benutzer_id'     => $benutzerId,
                    'erstellt_am'     => now(),
                    'aktualisiert_am' => now(),
                ]);
            }
        });

        return redirect()->route('admin.benutzer.index')
            ->with('status', 'Benutzer angelegt.');
    }

    public function toggleAktiv(Request $request, int $benutzer_id): RedirectResponse
    {
        $user = User::whereNull('geloescht_am')->findOrFail($benutzer_id);

        if ($user->benutzer_id === (int) $request->user()->benutzer_id) {
            return back()->with('error', 'Eigener Account kann nicht deaktiviert werden.');
        }

        $user->aktiv = !$user->aktiv;
        $user->save();

        return back()->with('status', $user->aktiv ? 'Benutzer aktiviert.' : 'Benutzer deaktiviert.');
    }
}
