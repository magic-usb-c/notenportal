<?php

declare(strict_types=1);

namespace App\Http\Controllers\Verwaltung;

use App\Models\Berufsbildner;
use App\Models\Lernender;
use App\Services\Auswertung\LernstandRechner;
use App\Services\Benutzer\LernendeErfassungService;
use App\Services\Benutzer\Startpasswort;
use App\Services\Uebersicht;
use App\Support\Protokoll;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Lernende: Liste, Anlegen, Detail, Bearbeiten. */
class LernendeController extends VerwaltungController
{
    private const array WARNUNGEN = ['tief_avg', 'keine_noten', 'ohne_betreuung', 'ohne_track'];

    private const array SORTIERUNGEN = ['name', 'avg', 'last_note', 'lehrjahr'];

    public function __construct(
        private readonly LernendeErfassungService $erfassung,
        private readonly Uebersicht $uebersicht,
        private readonly LernstandRechner $lernstaende,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $istAdmin = $user->hasRole('Admin');

        $filter = [
            'suche' => trim((string) $request->input('suche', '')),
            'lehrberuf_id' => $request->integer('lehrberuf_id') ?: null,
            'lehrjahr' => $request->integer('lehrjahr') ?: null,
            'bms' => in_array($request->input('bms'), ['ja', 'nein'], true) ? $request->input('bms') : '',
            'warnung' => in_array($request->input('warnung'), self::WARNUNGEN, true) ? $request->input('warnung') : '',
            'inaktive' => $request->boolean('inaktive'),
            'berufsbildner_id' => $istAdmin ? ($request->integer('berufsbildner_id') ?: null) : null,
            'sort' => in_array($request->input('sort'), self::SORTIERUNGEN, true) ? $request->input('sort') : 'name',
            'dir' => $request->input('dir') === 'desc' ? 'desc' : 'asc',
        ];

        $heute = now()->toDateString();
        $bmsAktiv = fn ($t) => $t->where('track_typ', 'BMS')
            ->where('start_datum', '<=', $heute)
            ->where(fn ($q) => $q->whereNull('end_datum')->orWhere('end_datum', '>=', $heute));

        $lernende = Lernender::sichtbarFuer($user)
            ->with([
                'benutzer',
                'lehrberuf',
                'tracks',
                'betreuungen' => fn ($q) => $q->aktiv()->with('berufsbildner.benutzer'),
            ])
            ->when(! $filter['inaktive'], fn ($q) => $q->whereHas('benutzer', fn ($b) => $b->where('aktiv', true)))
            ->when($filter['suche'] !== '', function ($q) use ($filter) {
                $like = '%'.addcslashes($filter['suche'], '%_\\').'%';
                $q->whereHas('benutzer', fn ($b) => $b->where(fn ($w) => $w
                    ->where('vorname', 'like', $like)
                    ->orWhere('nachname', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('benutzername', 'like', $like)));
            })
            ->when($filter['lehrberuf_id'], fn ($q, $id) => $q->where('lehrberuf_id', $id))
            ->when($filter['berufsbildner_id'], fn ($q, $id) => $q->whereHas('betreuungen', fn ($b) => $b->aktiv()->where('berufsbildner_id', $id)))
            ->when($filter['bms'] === 'ja', fn ($q) => $q->whereHas('tracks', $bmsAktiv))
            ->when($filter['bms'] === 'nein', fn ($q) => $q->whereDoesntHave('tracks', $bmsAktiv))
            ->get();

        $zeilen = $this->mitStatistik($lernende, (int) $user->benutzer_id);

        $cutoff = now()->subDays(30)->toDateString();
        $zeilen = $zeilen
            ->when($filter['lehrjahr'], fn ($z, $jahr) => $z->filter(fn ($r) => $r->lehrjahr === $jahr))
            ->when($filter['warnung'] === 'tief_avg', fn ($z) => $z->filter(fn ($r) => $r->avg !== null && $r->avg < 4.0))
            ->when($filter['warnung'] === 'keine_noten', fn ($z) => $z->filter(fn ($r) => ! $r->lastNote || $r->lastNote < $cutoff))
            ->when($filter['warnung'] === 'ohne_betreuung', fn ($z) => $z->filter(fn ($r) => ! $r->betreuer))
            ->when($filter['warnung'] === 'ohne_track', fn ($z) => $z->filter(fn ($r) => ! $r->trackAktiv));

        $desc = $filter['dir'] === 'desc';
        $zeilen = match ($filter['sort']) {
            'avg' => $zeilen->sortBy(fn ($r) => $r->avg ?? -1, SORT_REGULAR, $desc),
            'last_note' => $zeilen->sortBy(fn ($r) => $r->lastNote ?? '', SORT_STRING, $desc),
            'lehrjahr' => $zeilen->sortBy(fn ($r) => $r->lehrjahr ?? 0, SORT_REGULAR, $desc),
            default => $zeilen->sortBy(fn ($r) => mb_strtolower($r->nachname.' '.$r->vorname), SORT_STRING, $desc),
        };

        return view('verwaltung.lernende.index', [
            'zeilen' => $zeilen->values(),
            'filter' => $filter,
            'lehrberufe' => DB::table('lehrberufe')->orderBy('name')->get(['lehrberuf_id', 'name']),
            'berufsbildnerListe' => $istAdmin ? $this->berufsbildnerListe() : collect(),
        ]);
    }

    public function create(Request $request): View
    {
        $istAdmin = $request->user()->hasRole('Admin');
        abort_if(! $istAdmin && ! $request->user()->berufsbildner, 403);

        return view('verwaltung.lernende.create', [
            'lehrberufe' => DB::table('lehrberufe')->where('aktiv', 1)->orderBy('name')->get(),
            'semester' => DB::table('semester')->orderBy('sortierung')->get(),
            'berufsbildnerListe' => $istAdmin ? $this->berufsbildnerListe() : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $istAdmin = $user->hasRole('Admin');
        $eigenerBb = $user->berufsbildner?->berufsbildner_id;
        abort_if(! $istAdmin && ! $eigenerBb, 403);

        $regeln = [
            'vorname' => ['required', 'string', 'max:100'],
            'nachname' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:benutzer,email'],
            'benutzername' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', 'unique:benutzer,benutzername'],
            'lehrberuf_id' => ['required', 'integer', Rule::exists('lehrberufe', 'lehrberuf_id')->where('aktiv', 1)],
            'lehrbeginn' => ['required', 'date'],
            'lehrende' => ['nullable', 'date', 'after_or_equal:lehrbeginn'],
            'bemerkung' => ['nullable', 'string', 'max:5000'],
            'track_typ' => ['nullable', 'in:BMS,ABU'],
            'track_semester_id' => ['nullable', 'required_with:track_typ', 'integer', 'exists:semester,semester_id'],
        ];

        if ($istAdmin) {
            $regeln['berufsbildner_id'] = ['nullable', 'integer', Rule::exists('berufsbildner', 'berufsbildner_id')->whereNull('geloescht_am')];
        }

        $daten = $request->validate($regeln);

        // BB: Betreuung immer ihm selbst; Admin: gewählter BB (optional)
        $berufsbildnerId = $istAdmin ? ($daten['berufsbildner_id'] ?? null) : $eigenerBb;
        $passwort = Startpasswort::erzeugen();

        $lernenderId = $this->erfassung->erstellen(
            [...$daten, 'passwort' => $passwort],
            $berufsbildnerId ? (int) $berufsbildnerId : null,
        );

        Protokoll::schreiben(Protokoll::ADMIN_KONTO_ANGELEGT, Lernender::find($lernenderId), ['rolle' => 'Lernender']);

        return redirect()
            ->to($this->zuRoute($request, 'learners.show', $lernenderId))
            ->with('success', __('Lernender angelegt.'))
            ->with('startpasswort', $passwort);
    }

    public function show(Request $request, int $lernender_id): View
    {
        $lernender = $this->sichtbarerLernender($request, $lernender_id);
        $lernender->load([
            'lehrberuf',
            'tracks' => fn ($q) => $q->with(['startSemester', 'endSemester'])->orderByDesc('start_datum'),
            'betreuungen' => fn ($q) => $q->with('berufsbildner.benutzer')->orderByDesc('gueltig_von'),
        ]);

        return view('verwaltung.lernende.show', [
            'lernender' => $lernender,
            ...$this->uebersicht->lernendenDetail($lernender, $this->bereich($request), $request->user()),
            'semesterListe' => DB::table('semester')->orderBy('sortierung')->get(),
            'berufsbildnerListe' => $this->berufsbildnerListe(),
        ]);
    }

    public function edit(Request $request, int $lernender_id): View
    {
        $lernender = $this->sichtbarerLernender($request, $lernender_id);
        Gate::authorize('update', $lernender);

        return view('verwaltung.lernende.edit', [
            'lernender' => $lernender,
            'lehrberufe' => DB::table('lehrberufe')
                ->where(fn ($q) => $q->where('aktiv', 1)->orWhere('lehrberuf_id', $lernender->lehrberuf_id))
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(Request $request, int $lernender_id): RedirectResponse
    {
        $lernender = $this->sichtbarerLernender($request, $lernender_id);
        Gate::authorize('update', $lernender);

        $benutzerId = $lernender->benutzer_id;

        $daten = $request->validate([
            'vorname' => ['required', 'string', 'max:100'],
            'nachname' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', Rule::unique('benutzer', 'email')->ignore($benutzerId, 'benutzer_id')],
            'benutzername' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('benutzer', 'benutzername')->ignore($benutzerId, 'benutzer_id')],
            'lehrberuf_id' => ['required', 'integer', 'exists:lehrberufe,lehrberuf_id'],
            'lehrbeginn' => ['required', 'date'],
            'lehrende' => ['nullable', 'date', 'after_or_equal:lehrbeginn'],
            'bemerkung' => ['nullable', 'string', 'max:5000'],
        ]);

        DB::transaction(function () use ($lernender, $daten) {
            $lernender->benutzer->update([
                'vorname' => $daten['vorname'],
                'nachname' => $daten['nachname'],
                'email' => $daten['email'],
                'benutzername' => $daten['benutzername'],
            ]);

            $lernender->update([
                'lehrberuf_id' => (int) $daten['lehrberuf_id'],
                'lehrbeginn' => $daten['lehrbeginn'],
                'lehrende' => $daten['lehrende'] ?? null,
                'bemerkung' => $daten['bemerkung'] ?? null,
            ]);
        });

        return redirect()
            ->to($this->zuRoute($request, 'learners.show', $lernender_id))
            ->with('success', __('Lernender gespeichert.'));
    }

    /**
     * Listenzeilen mit Notenstatistik (Anzahl, letzte Note, Ø gesamt) und
     * ungelesenen Noten des angemeldeten Benutzers.
     *
     * @param  Collection<int, Lernender>  $lernende
     */
    private function mitStatistik(Collection $lernende, int $viewerId): Collection
    {
        $ids = $lernende->modelKeys();

        $noten = DB::table('noten')
            ->whereIn('lernender_id', $ids)
            ->whereNull('geloescht_am')
            ->groupBy('lernender_id')
            ->select([
                'lernender_id',
                DB::raw('COUNT(*) as anzahl'),
                DB::raw('MAX(pruefungsdatum) as letzte'),
            ])
            ->get()
            ->keyBy('lernender_id');
        $staende = $this->lernstaende->fuer(array_map('intval', $ids));

        $ungelesen = DB::table('noten as n')
            ->leftJoin('noten_gesehen as g', fn ($j) => $j->on('g.note_id', '=', 'n.note_id')->where('g.viewer_benutzer_id', '=', $viewerId))
            ->whereIn('n.lernender_id', $ids)
            ->whereNull('n.geloescht_am')
            ->where(fn ($q) => $q->whereNull('g.gesehen_am')
                ->orWhereColumn('n.erstellt_am', '>', 'g.gesehen_am')
                ->orWhereExists(fn ($k) => $k->select(DB::raw(1))
                    ->from('noten_kommentare as k')
                    ->whereColumn('k.note_id', 'n.note_id')
                    ->whereColumn('k.erstellt_am', '>', 'g.gesehen_am')))
            ->groupBy('n.lernender_id')
            ->select(['n.lernender_id', DB::raw('COUNT(*) as anzahl')])
            ->pluck('anzahl', 'n.lernender_id');

        $heute = now()->toDateString();

        return $lernende->map(function (Lernender $l) use ($noten, $ungelesen, $heute, $staende) {
            $n = $noten->get($l->lernender_id);
            $betreuer = $l->betreuungen->first()?->berufsbildner?->benutzer;

            return (object) [
                'lernender' => $l,
                'vorname' => $l->benutzer->vorname,
                'nachname' => $l->benutzer->nachname,
                'lehrjahr' => $l->lehrjahr(),
                'bms' => $l->tracks->contains(fn ($t) => $t->track_typ === 'BMS'
                    && $t->start_datum->toDateString() <= $heute
                    && (! $t->end_datum || $t->end_datum->toDateString() >= $heute)),
                'trackAktiv' => $l->tracks->contains(fn ($t) => $t->start_datum->toDateString() <= $heute
                    && (! $t->end_datum || $t->end_datum->toDateString() >= $heute)),
                'betreuer' => $betreuer,
                'anzahl' => (int) ($n->anzahl ?? 0),
                'lastNote' => $n->letzte ?? null,
                'avg' => $staende[$l->lernender_id]->auswertung->gesamtNote,
                'stand' => $staende[$l->lernender_id],
                'ungelesen' => (int) ($ungelesen[$l->lernender_id] ?? 0),
            ];
        });
    }

    /** Aktive Berufsbildner für Filter und Betreuungs-Auswahl. */
    private function berufsbildnerListe(): Collection
    {
        return Berufsbildner::query()
            ->whereHas('benutzer', fn ($q) => $q->where('aktiv', true))
            ->with('benutzer')
            ->get()
            ->sortBy(fn (Berufsbildner $bb) => mb_strtolower($bb->benutzer->nachname.' '.$bb->benutzer->vorname))
            ->values();
    }
}
