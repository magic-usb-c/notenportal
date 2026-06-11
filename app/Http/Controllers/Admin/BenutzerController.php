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
    public function __construct(
        private readonly \App\Services\Benutzer\LernendeErfassungService $lernendeErfassung
    ) {}

    public function index(Request $request)
    {
        $suche   = $request->input('suche', '');
        $rolleId = $request->input('rolle_id', '');
        $status  = $request->input('status', '');

        $q = DB::table('benutzer as b')
            ->leftJoin('benutzer_rollen as br', 'br.benutzer_id', '=', 'b.benutzer_id')
            ->leftJoin('rollen as r', 'r.rolle_id', '=', 'br.rolle_id')
            ->leftJoin('lernende as l', function ($j) {
                $j->on('l.benutzer_id', '=', 'b.benutzer_id')->whereNull('l.geloescht_am');
            })
            ->whereNull('b.geloescht_am')
            ->select([
                'b.benutzer_id',
                'b.vorname',
                'b.nachname',
                'b.email',
                'b.benutzername',
                'b.aktiv',
                'b.erstellt_am',
                'l.lernender_id',
                DB::raw('GROUP_CONCAT(r.name ORDER BY r.name SEPARATOR ", ") as rollen'),
            ])
            ->groupBy('b.benutzer_id', 'b.vorname', 'b.nachname', 'b.email', 'b.benutzername', 'b.aktiv', 'b.erstellt_am', 'l.lernender_id');

        if ($suche !== '') {
            $like = '%' . $suche . '%';
            $q->where(fn($w) => $w
                ->where('b.vorname', 'like', $like)
                ->orWhere('b.nachname', 'like', $like)
                ->orWhere('b.email', 'like', $like)
                ->orWhere('b.benutzername', 'like', $like)
            );
        }

        if ($rolleId !== '') {
            $q->where('br.rolle_id', (int) $rolleId);
        }

        if ($status === 'aktiv') {
            $q->where('b.aktiv', 1);
        } elseif ($status === 'inaktiv') {
            $q->where('b.aktiv', 0);
        }

        $benutzer = $q->orderBy('b.nachname')->orderBy('b.vorname')->get();
        $rollen   = DB::table('rollen')->orderBy('rolle_id')->get();

        return view('admin.benutzer.index', compact('benutzer', 'rollen', 'suche', 'rolleId', 'status'));
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
        $rolleId          = (int) $request->input('rolle_id');
        $lernenderRolleId = (int) DB::table('rollen')->where('name', 'Lernender')->value('rolle_id');
        $bbRolleId = (int) DB::table('rollen')->where('name', 'Berufsbildner')->value('rolle_id');

        $rules = [
            'vorname'      => ['required', 'string', 'max:100'],
            'nachname'     => ['required', 'string', 'max:100'],
            'email'        => ['required', 'email', 'max:255', 'unique:benutzer,email'],
            'benutzername' => ['required', 'string', 'max:50', 'unique:benutzer,benutzername', 'alpha_num'],
            'passwort'     => ['required', 'string', 'min:8', 'confirmed'],
            'rolle_id'     => ['required', 'integer', 'exists:rollen,rolle_id'],
        ];

        // Zusatzfelder für Lernende
        if ($rolleId === $lernenderRolleId) {
            $rules['lehrberuf_id']    = ['required', 'integer', 'exists:lehrberufe,lehrberuf_id'];
            $rules['lehrbeginn']      = ['required', 'date'];
            $rules['berufsbildner_id'] = ['nullable', 'integer', 'exists:berufsbildner,berufsbildner_id'];
            $rules['track_typ']        = ['nullable', 'in:BMS,ABU'];
            $rules['track_semester_id'] = ['required_if:track_typ,BMS', 'required_if:track_typ,ABU',
                                           'nullable', 'integer', 'exists:semester,semester_id'];
        }

        $validated = $request->validate($rules);

        if ($rolleId === $lernenderRolleId) {
            // Lernende: geteilte Erfassungslogik (auch vom BB-Flow genutzt)
            $this->lernendeErfassung->erstellen(
                $validated,
                !empty($validated['berufsbildner_id']) ? (int) $validated['berufsbildner_id'] : null
            );
        } else {
            DB::transaction(function () use ($validated, $rolleId, $bbRolleId) {
                $user = User::create([
                    'vorname'       => $validated['vorname'],
                    'nachname'      => $validated['nachname'],
                    'email'         => $validated['email'],
                    'benutzername'  => $validated['benutzername'],
                    'passwort_hash' => $validated['passwort'],
                    'aktiv'         => true,
                ]);

                DB::table('benutzer_rollen')->insert([
                    'benutzer_id' => (int) $user->benutzer_id,
                    'rolle_id'    => $rolleId,
                ]);

                // Berufsbildner-Profil
                if ($rolleId === $bbRolleId) {
                    DB::table('berufsbildner')->insert([
                        'benutzer_id'     => (int) $user->benutzer_id,
                        'erstellt_am'     => now(),
                        'aktualisiert_am' => now(),
                    ]);
                }
            });
        }

        return redirect()->route('admin.benutzer.index')
            ->with('status', 'Benutzer angelegt.');
    }

    public function edit(int $benutzer_id)
    {
        $user   = User::whereNull('geloescht_am')->findOrFail($benutzer_id);
        $rollen = DB::table('rollen')->orderBy('rolle_id')->get();

        // Lernenden-Profil laden (falls vorhanden)
        $lernendeProfil = DB::table('lernende')
            ->where('benutzer_id', $benutzer_id)
            ->whereNull('geloescht_am')
            ->first();

        $lehrberufe = $lernendeProfil
            ? DB::table('lehrberufe')->where('aktiv', 1)->orderBy('name')->get()
            : collect();

        return view('admin.benutzer.edit', compact('user', 'rollen', 'lernendeProfil', 'lehrberufe'));
    }

    public function update(Request $request, int $benutzer_id): RedirectResponse
    {
        $user = User::whereNull('geloescht_am')->findOrFail($benutzer_id);

        $rules = [
            'vorname'  => ['required', 'string', 'max:100'],
            'nachname' => ['required', 'string', 'max:100'],
            'email'    => ['required', 'email', 'max:255',
                           \Illuminate\Validation\Rule::unique('benutzer', 'email')->ignore($benutzer_id, 'benutzer_id')],
        ];

        // Optional: neues Passwort nur wenn ausgefüllt
        if ($request->filled('passwort')) {
            $rules['passwort'] = ['string', 'min:8', 'confirmed'];
        }

        $validated = $request->validate($rules);

        $user->vorname  = $validated['vorname'];
        $user->nachname = $validated['nachname'];
        $user->email    = $validated['email'];

        if ($request->filled('passwort')) {
            $user->passwort_hash = $validated['passwort'];
        }

        $user->save();

        // Lernenden-Profil aktualisieren (falls vorhanden)
        $lernende = DB::table('lernende')
            ->where('benutzer_id', $benutzer_id)
            ->whereNull('geloescht_am')
            ->first();

        if ($lernende && $request->filled('lehrberuf_id')) {
            $lernendeRules = [
                'lehrberuf_id' => ['required', 'integer', 'exists:lehrberufe,lehrberuf_id'],
                'lehrbeginn'   => ['required', 'date'],
                'lehrende'     => ['nullable', 'date', 'after_or_equal:lehrbeginn'],
            ];
            $lernendeData = $request->validate($lernendeRules);

            DB::table('lernende')
                ->where('lernender_id', $lernende->lernender_id)
                ->update([
                    'lehrberuf_id'    => (int) $lernendeData['lehrberuf_id'],
                    'lehrbeginn'      => $lernendeData['lehrbeginn'],
                    'lehrende'        => $lernendeData['lehrende'] ?? null,
                    'aktualisiert_am' => now(),
                ]);
        }

        return redirect()->route('admin.benutzer.edit', $benutzer_id)
            ->with('status', 'Benutzer aktualisiert.');
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
