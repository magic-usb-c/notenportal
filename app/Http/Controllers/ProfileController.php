<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();

        // Lernenden-Profil für zusätzliche Anzeige (read-only)
        $lernendeProfil = DB::table('lernende as l')
            ->leftJoin('lehrberufe as lb', 'lb.lehrberuf_id', '=', 'l.lehrberuf_id')
            ->where('l.benutzer_id', $user->benutzer_id)
            ->whereNull('l.geloescht_am')
            ->select(['l.lehrbeginn', 'l.lehrende', 'lb.name as lehrberuf_name', 'lb.kuerzel'])
            ->first();

        return view('profile.edit', compact('user', 'lernendeProfil'));
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $user->vorname  = $request->validated('vorname');
        $user->nachname = $request->validated('nachname');
        $user->email    = $request->validated('email');
        $user->save();

        return Redirect::route('profile.edit')->with('status', 'Profil aktualisiert.');
    }

}
