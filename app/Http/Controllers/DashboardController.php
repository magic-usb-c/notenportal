<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Noten\NoteService;
use App\Services\Uebersicht;
use App\Support\Einrichtung;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly Uebersicht $uebersicht,
        private readonly NoteService $noteService,
    ) {}

    public function lernender(Request $request): View
    {
        $lernender = $request->user()->lernender ?? abort(403);

        $daten = $this->uebersicht->lernender($lernender->load('lehrberuf'), $request->user());
        $daten['drawerFehler'] = $this->noteService->drawerNachFehler($request, $lernender);

        return view('dashboards.lernender', $daten);
    }

    public function berufsbildner(Request $request): View
    {
        abort_unless($request->user()->berufsbildner, 403);

        $filter = [
            'sort' => in_array($request->input('sort'), Uebersicht::BB_SORTIERUNGEN, true) ? $request->input('sort') : null,
            'dir' => $request->input('dir') === 'desc' ? 'desc' : 'asc',
        ];

        return view('dashboards.berufsbildner', [
            ...$this->uebersicht->berufsbildner($request->user(), $filter['sort'], $filter['dir']),
            'filter' => $filter,
        ]);
    }

    public function admin(): View|RedirectResponse
    {
        if (Einrichtung::offen()) {
            return redirect()->route('admin.setup');
        }

        return view('dashboards.admin', $this->uebersicht->admin());
    }
}
