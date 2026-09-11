<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Uebersicht;
use App\Support\Einrichtung;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly Uebersicht $uebersicht) {}

    public function lernender(Request $request): View
    {
        $lernender = $request->user()->lernender ?? abort(403);

        return view('dashboards.lernender', $this->uebersicht->lernender($lernender->load('lehrberuf'), $request->user()));
    }

    public function berufsbildner(Request $request): View
    {
        abort_unless($request->user()->berufsbildner, 403);

        return view('dashboards.berufsbildner', $this->uebersicht->berufsbildner($request->user()));
    }

    public function admin(): View|RedirectResponse
    {
        if (Einrichtung::offen()) {
            return redirect()->route('admin.setup');
        }

        return view('dashboards.admin', $this->uebersicht->admin());
    }
}
