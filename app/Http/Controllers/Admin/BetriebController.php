<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Betrieb;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BetriebController extends Controller
{
    public function edit(): View
    {
        return view('admin.betrieb.edit', ['werte' => Betrieb::werte()]);
    }

    public function update(Request $request): RedirectResponse
    {
        Betrieb::speichern($request->validate(Betrieb::regeln()));

        return redirect()->route('admin.betrieb.edit')->with('success', 'Betrieb gespeichert.');
    }
}
