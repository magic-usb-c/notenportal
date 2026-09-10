<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PasswortWechselController extends Controller
{
    public function edit(Request $request): View|RedirectResponse
    {
        if (! $request->user()->passwort_wechsel_noetig) {
            return redirect()->route('dashboard');
        }

        return view('auth.passwort-wechsel');
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->passwort_wechsel_noetig) {
            return redirect()->route('dashboard');
        }

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        if (Hash::check($validated['password'], $user->passwort_hash)) {
            return back()->withErrors(['password' => 'Das neue Passwort muss sich vom bisherigen unterscheiden.']);
        }

        $user->update([
            'passwort_hash' => $validated['password'],
            'passwort_wechsel_noetig' => false,
        ]);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Passwort gespeichert.');
    }
}
