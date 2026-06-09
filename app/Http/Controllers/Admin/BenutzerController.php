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
        // Alle Benutzer (inkl. inaktive) mit ihren Rollen
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

        return view('admin.benutzer.create', compact('rollen', 'lehrberufe'));
    }

    public function store(Request $request): RedirectResponse
    {
        $rolleId = (int) $request->input('rolle_id');

        $rules = [
            'vorname'     => ['required', 'string', 'max:100'],
            'nachname'    => ['required', 'string', 'max:100'],
            'email'       => ['required', 'email', 'max:255', 'unique:benutzer,email'],
            'benutzername' => ['required', 'string', 'max:50', 'unique:benutzer,benutzername', 'alpha_num'],
            'passwort'    => ['required', 'string', 'min:8', 'confirmed'],
            'rolle_id'    => ['required', 'integer', 'exists:rollen,rolle_id'],
        ];

        // Lernender braucht Lehrberuf und Lehrbeginn
        if ($rolleId === 3) {
            $rules['lehrberuf_id'] = ['required', 'integer', 'exists:lehrberufe,lehrberuf_id'];
            $rules['lehrbeginn']   = ['required', 'date'];
        }

        $validated = $request->validate($rules);

        DB::transaction(function () use ($validated, $rolleId) {
            // 1) Benutzer anlegen
            $user = User::create([
                'vorname'      => $validated['vorname'],
                'nachname'     => $validated['nachname'],
                'email'        => $validated['email'],
                'benutzername' => $validated['benutzername'],
                'passwort_hash' => $validated['passwort'],  // hashed-Cast erledigt das Hashen
                'aktiv'        => true,
            ]);

            // 2) Rolle zuweisen
            DB::table('benutzer_rollen')->insert([
                'benutzer_id' => $user->benutzer_id,
                'rolle_id'    => $rolleId,
            ]);

            // 3) Typ-spezifisches Profil anlegen
            if ($rolleId === 3) {
                DB::table('lernende')->insert([
                    'benutzer_id'  => $user->benutzer_id,
                    'lehrberuf_id' => $validated['lehrberuf_id'],
                    'lehrbeginn'   => $validated['lehrbeginn'],
                    'erstellt_am'  => now(),
                    'aktualisiert_am' => now(),
                ]);
            } elseif ($rolleId === 2) {
                DB::table('berufsbildner')->insert([
                    'benutzer_id'     => $user->benutzer_id,
                    'erstellt_am'     => now(),
                    'aktualisiert_am' => now(),
                ]);
            }
        });

        return redirect()->route('admin.benutzer.index')
            ->with('status', 'Benutzer erfolgreich angelegt.');
    }

    public function toggleAktiv(Request $request, int $benutzer_id): RedirectResponse
    {
        $user = User::whereNull('geloescht_am')->findOrFail($benutzer_id);

        // Admin kann sich nicht selbst sperren
        if ($user->benutzer_id === (int) $request->user()->benutzer_id) {
            return back()->with('error', 'Sie können Ihren eigenen Account nicht deaktivieren.');
        }

        $user->aktiv = !$user->aktiv;
        $user->save();

        return back()->with('status', $user->aktiv ? 'Benutzer aktiviert.' : 'Benutzer deaktiviert.');
    }
}
