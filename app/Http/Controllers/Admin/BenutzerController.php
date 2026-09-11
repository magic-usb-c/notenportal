<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Berufsbildner;
use App\Models\User;
use App\Services\Notifications\AccountMails;
use App\Support\Protokoll;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Konten von Admins und Berufsbildnern. Lernenden-Konten laufen über die
 * Lernenden-Verwaltung (Verwaltung\*), hier werden sie nur gelistet.
 */
class BenutzerController extends Controller
{
    private const array ROLLEN = ['Admin', 'Berufsbildner'];

    public function index(Request $request)
    {
        $suche = $request->input('suche', '');
        $rolleId = $request->input('rolle_id', '');
        $status = $request->input('status', '');

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
            $like = '%'.addcslashes($suche, '%_\\').'%';
            $q->where(fn ($w) => $w
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
        $rollen = DB::table('rollen')->orderBy('rolle_id')->get();

        return view('admin.benutzer.index', compact('benutzer', 'rollen', 'suche', 'rolleId', 'status'));
    }

    public function create()
    {
        $rollen = DB::table('rollen')->whereIn('name', self::ROLLEN)->orderBy('rolle_id')->get();

        return view('admin.benutzer.create', compact('rollen'));
    }

    public function store(Request $request): RedirectResponse
    {
        $rollen = DB::table('rollen')->whereIn('name', self::ROLLEN)->pluck('rolle_id', 'name');

        $validated = $request->validate([
            'vorname' => ['required', 'string', 'max:100'],
            'nachname' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:benutzer,email'],
            'benutzername' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', 'unique:benutzer,benutzername'],
            'passwort' => ['required', 'string', 'confirmed', Password::defaults()],
            'rolle_id' => ['required', 'integer', Rule::in($rollen->values()->all())],
        ]);

        $user = DB::transaction(function () use ($validated, $rollen) {
            $user = User::create([
                'vorname' => $validated['vorname'],
                'nachname' => $validated['nachname'],
                'email' => $validated['email'],
                'benutzername' => $validated['benutzername'],
                'passwort_hash' => $validated['passwort'],
                'passwort_wechsel_noetig' => true,
                'aktiv' => true,
            ]);

            $user->rollen()->attach((int) $validated['rolle_id']);

            if ((int) $validated['rolle_id'] === (int) $rollen['Berufsbildner']) {
                Berufsbildner::create(['benutzer_id' => $user->benutzer_id]);
            }

            return $user;
        });

        AccountMails::accountCreated($user);
        Protokoll::schreiben(Protokoll::ADMIN_KONTO_ANGELEGT, $user, ['rolle' => (string) $rollen->search((int) $validated['rolle_id'])]);

        return redirect()->route('admin.users.index')->with('success', __('Benutzer angelegt.'));
    }

    public function edit(int $benutzer_id)
    {
        $user = User::findOrFail($benutzer_id);

        if ($lernenderId = $user->lernender?->lernender_id) {
            return redirect()->route('admin.learners.show', $lernenderId);
        }

        return view('admin.benutzer.edit', ['user' => $user, 'rollen' => $user->rollen()->pluck('name')]);
    }

    public function update(Request $request, int $benutzer_id): RedirectResponse
    {
        $user = $this->kontoOhneLernende($benutzer_id);

        $validated = $request->validate([
            'vorname' => ['required', 'string', 'max:100'],
            'nachname' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('benutzer', 'email')->ignore($benutzer_id, 'benutzer_id')],
            'benutzername' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('benutzer', 'benutzername')->ignore($benutzer_id, 'benutzer_id')],
            'rollen' => ['required', 'array', 'min:1'],
            'rollen.*' => [Rule::in(self::ROLLEN)],
            'passwort' => ['nullable', 'string', 'confirmed', Password::defaults()],
        ]);

        $rollen = collect($validated['rollen'])->unique()->values();
        if ((int) $user->benutzer_id === (int) $request->user()->benutzer_id && ! $rollen->contains('Admin')) {
            throw ValidationException::withMessages(['rollen' => __('Die eigene Admin-Rolle bleibt bestehen.')]);
        }
        $berufsbildner = $user->berufsbildner;
        if ($berufsbildner && ! $rollen->contains('Berufsbildner') && DB::table('betreuungen')
            ->where('berufsbildner_id', $berufsbildner->berufsbildner_id)
            ->where(fn ($q) => $q->whereNull('gueltig_bis')->orWhere('gueltig_bis', '>=', now()->toDateString()))
            ->exists()) {
            throw ValidationException::withMessages(['rollen' => __('Aktive Betreuungen zuerst übergeben.')]);
        }

        $passwortZurueckgesetzt = ! empty($validated['passwort']);
        $rollenAlt = $user->rollen()->pluck('name')->sort()->values();

        DB::transaction(function () use ($user, $validated, $rollen, $berufsbildner) {
            $user->fill([
                'vorname' => $validated['vorname'],
                'nachname' => $validated['nachname'],
                'email' => $validated['email'],
                'benutzername' => $validated['benutzername'],
            ]);
            $user->rollen()->sync(DB::table('rollen')->whereIn('name', $rollen)->pluck('rolle_id')->all());
            if ($rollen->contains('Berufsbildner') && ! $berufsbildner) {
                Berufsbildner::withTrashed()->where('benutzer_id', $user->benutzer_id)->first()?->restore()
                    ?? Berufsbildner::create(['benutzer_id' => $user->benutzer_id]);
            } elseif (! $rollen->contains('Berufsbildner') && $berufsbildner) {
                $berufsbildner->delete();
            }

            if (! empty($validated['passwort'])) {
                $user->passwort_hash = $validated['passwort'];
                $user->passwort_wechsel_noetig = true;
            }

            $user->save();
        });

        if ($passwortZurueckgesetzt) {
            AccountMails::passwordResetByAdmin($user);
        }

        $rollenNeu = $rollen->sort()->values();
        if ($rollenAlt->all() !== $rollenNeu->all()) {
            Protokoll::schreiben(Protokoll::ADMIN_ROLLEN_GEAENDERT, $user, ['alt' => $rollenAlt->all(), 'neu' => $rollenNeu->all()]);
        }

        return redirect()->route('admin.users.edit', $benutzer_id)->with('success', __('Benutzer gespeichert.'));
    }

    public function toggleAktiv(Request $request, int $benutzer_id): RedirectResponse
    {
        $user = $this->kontoOhneLernende($benutzer_id);

        if ($user->benutzer_id === (int) $request->user()->benutzer_id) {
            return back()->with('error', __('Eigener Account kann nicht deaktiviert werden.'));
        }

        $neuAktiv = ! $user->aktiv;

        DB::transaction(function () use ($user, $neuAktiv) {
            $berufsbildner = $user->berufsbildner;
            $user->aktiv = $neuAktiv;
            $user->save();

            if (! $neuAktiv) {
                $berufsbildner?->delete();
            } elseif ($user->rollen()->whereRaw('LOWER(name) = LOWER(?)', ['Berufsbildner'])->exists()) {
                Berufsbildner::withTrashed()->where('benutzer_id', $user->benutzer_id)->first()?->restore();
            }
        });

        Protokoll::schreiben($neuAktiv ? Protokoll::ADMIN_KONTO_REAKTIVIERT : Protokoll::ADMIN_KONTO_DEAKTIVIERT, $user);

        return back()->with('success', $neuAktiv ? __('Benutzer aktiviert.') : __('Benutzer deaktiviert.'));
    }

    /** Lernenden-Konten werden ausschliesslich über die Lernenden-Verwaltung geändert. */
    private function kontoOhneLernende(int $benutzerId): User
    {
        $user = User::findOrFail($benutzerId);
        abort_if($user->lernender()->exists(), 404);

        return $user;
    }
}
