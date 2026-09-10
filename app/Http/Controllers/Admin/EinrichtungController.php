<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Berufsbildner;
use App\Models\User;
use App\Services\Auswertung\Konfiguration;
use App\Services\Benutzer\LernendeErfassungService;
use App\Services\Benutzer\Startpasswort;
use App\Support\Betrieb;
use App\Support\Einrichtung;
use App\Support\Einstellungen;
use App\Support\KategorieRegeln;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Geführte Ersteinrichtung. Jeder Schritt speichert für sich und bleibt später erreichbar;
 * vorhandene Daten werden nie überschrieben, nur ergänzt.
 */
class EinrichtungController extends Controller
{
    private const array ATTRIBUTE = [
        'kategorien.*.name' => 'Name', 'kategorien.*.gewicht_gesamt' => 'Gewicht',
        'kategorien.*.promotion_min_schnitt' => 'Promotion Ø', 'kategorien.*.promotion_max_ungenuegend' => 'max. ungenügend',
        'kategorien.*.promotion_max_minuspunkte' => 'max. Minuspunkte',
        'eigene.*.kuerzel' => 'Kürzel', 'eigene.*.name' => 'Lehrberuf',
        'personen.*.vorname' => 'Vorname', 'personen.*.nachname' => 'Nachname', 'personen.*.email' => 'E-Mail', 'personen.*.rolle' => 'Rolle',
        'lernende.*.vorname' => 'Vorname', 'lernende.*.nachname' => 'Nachname', 'lernende.*.email' => 'E-Mail',
        'lernende.*.lehrberuf_id' => 'Lehrberuf', 'lernende.*.lehrbeginn' => 'Lehrbeginn', 'lernende.*.lehrende' => 'Lehrende',
        'lernende.*.berufsbildner_id' => 'Berufsbildner', 'lernende.*.track' => 'Track',
    ];

    /** Startpasswörter bleiben bis zum Abschluss sichtbar (Session), damit keine Liste verloren geht. */
    private const string ZUGAENGE = 'einrichtung_zugaenge';

    public function __construct(private readonly LernendeErfassungService $erfassung) {}

    public function show(Request $request, string $schritt = 'betrieb'): View
    {
        abort_unless(array_key_exists($schritt, Einrichtung::SCHRITTE), 404);

        return view('admin.einrichtung.'.$schritt, [
            'stand' => Einrichtung::stand(),
            'zugaenge' => $request->session()->get(self::ZUGAENGE, []),
            ...$this->daten($schritt, $request),
        ]);
    }

    public function betrieb(Request $request): RedirectResponse
    {
        $user = $request->user();
        $daten = $request->validate([
            ...Betrieb::regeln(),
            'vorname' => ['required', 'string', 'max:100'],
            'nachname' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('benutzer', 'email')->ignore($user->benutzer_id, 'benutzer_id')],
        ]);

        Betrieb::speichern($daten);
        $user->update(['vorname' => $daten['vorname'], 'nachname' => $daten['nachname'], 'email' => $daten['email']]);

        return $this->weiter('betrieb', 'Betrieb gespeichert.');
    }

    public function kategorien(Request $request): RedirectResponse
    {
        $daten = $request->validate([
            'kategorien' => ['required', 'array'],
            'kategorien.*.name' => ['required', 'string', 'max:50', 'distinct'],
            'kategorien.*.aktiv' => ['boolean'],
            ...KategorieRegeln::regeln('kategorien.*.'),
        ], [], self::ATTRIBUTE);

        $ids = DB::table('kategorien')->pluck('kategorie_id')->map(fn ($id) => (int) $id)->all();
        DB::transaction(function () use ($daten, $ids) {
            foreach ($daten['kategorien'] as $id => $k) {
                if (in_array((int) $id, $ids, true)) {
                    DB::table('kategorien')->where('kategorie_id', (int) $id)->update([
                        'name' => $k['name'],
                        'aktiv' => (bool) ($k['aktiv'] ?? false),
                        ...KategorieRegeln::werte($k),
                    ]);
                }
            }
        });
        Einstellungen::set(Einrichtung::KATEGORIEN_GEPRUEFT, '1');
        Konfiguration::vergessen();

        return $this->weiter('kategorien', 'Kategorien gespeichert.');
    }

    public function semester(Request $request): RedirectResponse
    {
        $daten = $request->validate([
            'herbst' => ['required', 'date'],
            'fruehling' => ['required', 'date', 'after:herbst'],
            'bis_jahr' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);
        $herbst = Carbon::parse($daten['herbst']);
        $fruehling = Carbon::parse($daten['fruehling']);
        if ($fruehling->year !== $herbst->year + 1) {
            throw ValidationException::withMessages(['fruehling' => 'Das Frühlingssemester beginnt im Jahr nach dem Herbstsemester.']);
        }

        $bis = max($herbst->year, min((int) $daten['bis_jahr'], $herbst->year + 15));
        $plan = Einrichtung::semesterPlan($herbst->year, $bis, $herbst->format('m-d'), $fruehling->format('m-d'));

        [$neu, $vorhanden] = DB::transaction(function () use ($plan) {
            $neu = 0;
            $vorhanden = 0;
            $sortierung = (int) DB::table('semester')->max('sortierung');
            foreach ($plan as $s) {
                $konflikt = DB::table('semester')
                    ->where('bezeichnung', $s['bezeichnung'])
                    ->orWhere(fn ($q) => $q->where('start_datum', '<=', $s['end_datum'])->where('end_datum', '>=', $s['start_datum']))
                    ->exists();
                if ($konflikt) {
                    $vorhanden++;

                    continue;
                }
                DB::table('semester')->insert([...$s, 'sortierung' => $sortierung += 10]);
                $neu++;
            }
            $this->semesterSortieren();

            return [$neu, $vorhanden];
        });
        Konfiguration::vergessen();

        $text = ($neu === 1 ? '1 Semester' : $neu.' Semester').' angelegt'.($vorhanden ? ', '.$vorhanden.' bereits vorhanden' : '').'.';

        return $this->weiter('semester', $text);
    }

    public function lehrberufe(Request $request): RedirectResponse
    {
        $daten = $request->validate([
            'berufe' => ['array'],
            'berufe.*' => ['string', Rule::in(array_keys(Einrichtung::LEHRBERUFE))],
            'eigene' => ['array'],
            'eigene.*.kuerzel' => ['nullable', 'string', 'max:10', 'alpha_num', 'distinct', 'required_with:eigene.*.name'],
            'eigene.*.name' => ['nullable', 'string', 'max:200', 'distinct', 'required_with:eigene.*.kuerzel'],
            'faecher' => ['array'],
            'faecher.*' => ['string', 'regex:/^(BMS|ABU):[A-Z]+$/'],
        ], [], self::ATTRIBUTE);

        $berufe = collect($daten['berufe'] ?? [])->mapWithKeys(fn ($k) => [$k => Einrichtung::LEHRBERUFE[$k]]);
        foreach ($daten['eigene'] ?? [] as $e) {
            if (filled($e['kuerzel'] ?? null)) {
                $berufe[strtoupper(trim($e['kuerzel']))] = trim($e['name']);
            }
        }

        $kategorien = DB::table('kategorien')->pluck('kategorie_id', 'code');
        [$neuBerufe, $neuFaecher] = DB::transaction(function () use ($berufe, $daten, $kategorien) {
            $neuBerufe = 0;
            foreach ($berufe as $kuerzel => $name) {
                if (! DB::table('lehrberufe')->where('kuerzel', $kuerzel)->orWhere('name', $name)->exists()) {
                    DB::table('lehrberufe')->insert(['kuerzel' => $kuerzel, 'name' => $name, 'aktiv' => 1]);
                    $neuBerufe++;
                }
            }

            $neuFaecher = 0;
            foreach ($daten['faecher'] ?? [] as $schluessel) {
                [$track, $kurz] = explode(':', $schluessel);
                $name = Einrichtung::FAECHER[$track][$kurz] ?? null;
                if ($name && isset($kategorien[$track]) && ! DB::table('faecher')->where('name', $name)->where('track_typ', $track)->exists()) {
                    DB::table('faecher')->insert(['kategorie_id' => $kategorien[$track], 'track_typ' => $track, 'name' => $name, 'kurzname' => $kurz]);
                    $neuFaecher++;
                }
            }

            return [$neuBerufe, $neuFaecher];
        });
        Konfiguration::vergessen();

        return $this->weiter('lehrberufe', ($neuBerufe === 1 ? '1 Lehrberuf' : $neuBerufe.' Lehrberufe').' und '.($neuFaecher === 1 ? '1 Fach' : $neuFaecher.' Fächer').' angelegt.');
    }

    public function module(Request $request): RedirectResponse
    {
        $daten = $request->validate([
            'lehrberuf_id' => ['required', 'integer', 'exists:lehrberufe,lehrberuf_id'],
            'schule' => ['nullable', 'string', 'max:20000'],
            'uek' => ['nullable', 'string', 'max:20000'],
            'ziel' => ['required', 'numeric', 'min:1', 'max:9999'],
        ]);

        $kategorien = DB::table('kategorien')->whereIn('code', ['FACH', 'UEK'])->pluck('kategorie_id', 'code');
        $zeilen = [];
        $fehler = [];
        foreach (['schule' => 'FACH', 'uek' => 'UEK'] as $feld => $code) {
            $ergebnis = Einrichtung::moduleAusText($daten[$feld] ?? '');
            if ($ergebnis['fehler'] !== []) {
                $fehler[$feld] = 'Nicht erkannt: '.implode(' · ', array_slice($ergebnis['fehler'], 0, 3));
            }
            foreach ($ergebnis['module'] as $m) {
                $zeilen[] = [...$m, 'kategorie_id' => $kategorien[$code] ?? null];
            }
        }
        if ($fehler !== []) {
            throw ValidationException::withMessages($fehler);
        }
        if ($zeilen === []) {
            throw ValidationException::withMessages(['schule' => 'Keine Module erfasst.']);
        }

        $lehrberufId = (int) $daten['lehrberuf_id'];
        $neu = DB::transaction(function () use ($zeilen, $daten, $lehrberufId) {
            $neu = 0;
            foreach ($zeilen as $z) {
                $modulId = DB::table('module')->where('modul_nummer', $z['nummer'])->value('modul_id')
                    ?? DB::table('module')->insertGetId(['modul_nummer' => $z['nummer'], 'titel' => $z['titel'], 'ziel_gewicht_summe_default' => $daten['ziel']]);
                $zugeordnet = DB::table('lehrberuf_module')->where('lehrberuf_id', $lehrberufId)->where('modul_id', $modulId)->exists();
                if (! $zugeordnet && $z['kategorie_id'] !== null) {
                    DB::table('lehrberuf_module')->insert(['lehrberuf_id' => $lehrberufId, 'modul_id' => $modulId, 'kategorie_id' => $z['kategorie_id'], 'pflicht' => 1]);
                    $neu++;
                }
            }

            return $neu;
        });
        Konfiguration::vergessen();

        return redirect()
            ->route('admin.einrichtung', ['schritt' => 'module', 'lehrberuf_id' => $lehrberufId])
            ->with('success', ($neu === 1 ? '1 Modul' : $neu.' Module').' zugeordnet.');
    }

    public function personen(Request $request): RedirectResponse
    {
        $daten = $request->validate([
            'personen' => ['required', 'array', 'min:1', 'max:50'],
            'personen.*.vorname' => ['required', 'string', 'max:100'],
            'personen.*.nachname' => ['required', 'string', 'max:100'],
            'personen.*.email' => ['required', 'email', 'max:255', 'distinct', 'unique:benutzer,email'],
            'personen.*.rolle' => ['required', 'in:Berufsbildner,Admin'],
        ], [], self::ATTRIBUTE);

        $rollen = DB::table('rollen')->pluck('rolle_id', 'name');
        $zugaenge = DB::transaction(function () use ($daten, $rollen) {
            $zugaenge = [];
            foreach ($daten['personen'] as $p) {
                $passwort = Startpasswort::erzeugen();
                $user = User::create([
                    'vorname' => $p['vorname'],
                    'nachname' => $p['nachname'],
                    'email' => $p['email'],
                    'benutzername' => Einrichtung::benutzername($p['vorname'], $p['nachname']),
                    'passwort_hash' => $passwort,
                    'passwort_wechsel_noetig' => true,
                    'aktiv' => true,
                ]);
                $user->rollen()->attach((int) $rollen[$p['rolle']]);
                if ($p['rolle'] === 'Berufsbildner') {
                    Berufsbildner::create(['benutzer_id' => $user->benutzer_id]);
                }
                $zugaenge[] = ['name' => $p['vorname'].' '.$p['nachname'], 'rolle' => $p['rolle'], 'email' => $p['email'], 'passwort' => $passwort];
            }

            return $zugaenge;
        });

        $this->merken($request, $zugaenge);

        return redirect()->route('admin.einrichtung', 'personen')
            ->with('success', (count($zugaenge) === 1 ? '1 Konto' : count($zugaenge).' Konten').' angelegt.');
    }

    public function lernende(Request $request): RedirectResponse
    {
        $daten = $request->validate([
            'lernende' => ['required', 'array', 'min:1', 'max:100'],
            'lernende.*.vorname' => ['required', 'string', 'max:100'],
            'lernende.*.nachname' => ['required', 'string', 'max:100'],
            'lernende.*.email' => ['required', 'email', 'max:255', 'distinct', 'unique:benutzer,email'],
            'lernende.*.lehrberuf_id' => ['required', 'integer', 'exists:lehrberufe,lehrberuf_id'],
            'lernende.*.lehrbeginn' => ['required', 'date'],
            'lernende.*.lehrende' => ['nullable', 'date', 'after:lernende.*.lehrbeginn'],
            'lernende.*.berufsbildner_id' => ['nullable', 'integer', 'exists:berufsbildner,berufsbildner_id'],
            'lernende.*.track' => ['nullable', 'in:BMS,ABU'],
        ], [], self::ATTRIBUTE);

        $konfig = Konfiguration::ausDb();
        $fehler = [];
        foreach ($daten['lernende'] as $i => $l) {
            $beginn = Carbon::parse($l['lehrbeginn'])->toDateString();
            if (! empty($l['track']) && $konfig->semesterFuerDatum($beginn) === null) {
                $fehler["lernende.$i.lehrbeginn"] = 'Kein Semester am '.Carbon::parse($beginn)->format('d.m.Y').' – zuerst Semester anlegen.';
            }
        }
        if ($fehler !== []) {
            throw ValidationException::withMessages($fehler);
        }

        $zugaenge = DB::transaction(function () use ($daten, $konfig) {
            $zugaenge = [];
            foreach ($daten['lernende'] as $l) {
                $passwort = Startpasswort::erzeugen();
                $beginn = Carbon::parse($l['lehrbeginn'])->toDateString();
                $this->erfassung->erstellen([
                    'vorname' => $l['vorname'],
                    'nachname' => $l['nachname'],
                    'email' => $l['email'],
                    'benutzername' => Einrichtung::benutzername($l['vorname'], $l['nachname']),
                    'passwort' => $passwort,
                    'lehrberuf_id' => (int) $l['lehrberuf_id'],
                    'lehrbeginn' => $beginn,
                    'lehrende' => $l['lehrende'] ?? null,
                    'track_typ' => $l['track'] ?? null,
                    'track_semester_id' => ! empty($l['track']) ? $konfig->semesterFuerDatum($beginn) : null,
                ], ! empty($l['berufsbildner_id']) ? (int) $l['berufsbildner_id'] : null);
                $zugaenge[] = ['name' => $l['vorname'].' '.$l['nachname'], 'rolle' => 'Lernende/r', 'email' => $l['email'], 'passwort' => $passwort];
            }

            return $zugaenge;
        });

        $this->merken($request, $zugaenge);

        return redirect()->route('admin.einrichtung', 'personen')
            ->with('success', (count($zugaenge) === 1 ? '1 Lernende/r' : count($zugaenge).' Lernende').' angelegt.');
    }

    public function abschliessen(Request $request): RedirectResponse
    {
        Einrichtung::abschliessen();
        $request->session()->forget(self::ZUGAENGE);

        return redirect()->route('admin.dashboard')->with('success', 'Einrichtung abgeschlossen.');
    }

    /** @param  list<array{name: string, rolle: string, email: string, passwort: string}>  $neu */
    private function merken(Request $request, array $neu): void
    {
        $request->session()->put(self::ZUGAENGE, [...$request->session()->get(self::ZUGAENGE, []), ...$neu]);
    }

    private function weiter(string $schritt, string $meldung): RedirectResponse
    {
        return redirect()->route('admin.einrichtung', Einrichtung::naechster($schritt))->with('success', $meldung);
    }

    /** Sortierung chronologisch neu vergeben (zweistufig, damit eindeutige Werte nie kollidieren). */
    private function semesterSortieren(): void
    {
        $ids = DB::table('semester')->orderBy('start_datum')->pluck('semester_id')->values();
        $offset = (int) DB::table('semester')->max('sortierung') + 10;
        foreach ($ids as $i => $id) {
            DB::table('semester')->where('semester_id', $id)->update(['sortierung' => $offset + ($i + 1) * 10]);
        }
        foreach ($ids as $i => $id) {
            DB::table('semester')->where('semester_id', $id)->update(['sortierung' => ($i + 1) * 10]);
        }
    }

    /** @return array<string, mixed> */
    private function daten(string $schritt, Request $request): array
    {
        $schuljahr = now()->month >= 8 ? now()->year : now()->year - 1;

        return match ($schritt) {
            'betrieb' => ['werte' => Betrieb::werte(), 'konto' => $request->user()],
            'kategorien' => ['kategorien' => DB::table('kategorien')->orderBy('sortierung')->get()],
            'semester' => [
                'semester' => DB::table('semester')->orderBy('sortierung')->get(['bezeichnung', 'start_datum', 'end_datum']),
                'vorschlag' => ['herbst' => ($schuljahr - 3).'-08-01', 'fruehling' => ($schuljahr - 2).'-02-01', 'bis' => $schuljahr + 4],
            ],
            'lehrberufe' => [
                'lehrberufe' => DB::table('lehrberufe')->orderBy('name')->get(['kuerzel', 'name']),
                'faecher' => DB::table('faecher')->get(['name', 'track_typ']),
            ],
            'module' => $this->moduleDaten($request),
            'personen' => [
                'lehrberufe' => DB::table('lehrberufe')->where('aktiv', 1)->orderBy('name')->get(['lehrberuf_id', 'kuerzel', 'name']),
                'berufsbildner' => DB::table('berufsbildner as bb')
                    ->join('benutzer as b', 'b.benutzer_id', '=', 'bb.benutzer_id')
                    ->whereNull('bb.geloescht_am')
                    ->whereNull('b.geloescht_am')
                    ->orderBy('b.nachname')
                    ->get(['bb.berufsbildner_id', 'b.vorname', 'b.nachname']),
                'lehrbeginn' => $schuljahr.'-08-01',
                'semesterVorhanden' => DB::table('semester')->exists(),
            ],
            default => [],
        };
    }

    /** @return array<string, mixed> */
    private function moduleDaten(Request $request): array
    {
        $lehrberufe = DB::table('lehrberufe as lb')
            ->leftJoin('lehrberuf_module as lbm', 'lbm.lehrberuf_id', '=', 'lb.lehrberuf_id')
            ->groupBy('lb.lehrberuf_id', 'lb.kuerzel', 'lb.name')
            ->orderBy('lb.name')
            ->selectRaw('lb.lehrberuf_id, lb.kuerzel, lb.name, COUNT(lbm.modul_id) as anzahl')
            ->get();
        $aktiv = $lehrberufe->firstWhere('lehrberuf_id', $request->integer('lehrberuf_id')) ?? $lehrberufe->first();

        $zugeordnet = $aktiv
            ? DB::table('lehrberuf_module as lbm')
                ->join('module as m', 'm.modul_id', '=', 'lbm.modul_id')
                ->leftJoin('kategorien as k', 'k.kategorie_id', '=', 'lbm.kategorie_id')
                ->where('lbm.lehrberuf_id', $aktiv->lehrberuf_id)
                ->orderBy('m.modul_nummer')
                ->get(['m.modul_nummer', 'm.titel', 'k.code', 'k.name as lernort'])
            : collect();

        return ['lehrberufe' => $lehrberufe, 'aktiv' => $aktiv, 'zugeordnet' => $zugeordnet];
    }
}
