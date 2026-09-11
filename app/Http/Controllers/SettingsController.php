<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Lernender;
use App\Services\Calendar\CalendarExport;
use App\Support\Darstellung;
use App\Support\DashboardKarten;
use App\Support\Theme;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Seite «Einstellungen» (/settings/…): je Tab eine eigene Route, siehe settings/_tabs.blade.php. */
class SettingsController extends Controller
{
    public function profile(Request $request): View
    {
        $user = $request->user();
        $lernender = $user->lernender;
        $dashboardRolle = DashboardKarten::rolleFuer($user);

        $lehrberuf = $lernender
            ? DB::table('lehrberufe')->where('lehrberuf_id', $lernender->lehrberuf_id)->first(['name', 'kuerzel'])
            : null;

        $praeferenzen = Darstellung::fuer($user);

        return view('settings.profile', [
            'user' => $user,
            'lernender' => $lernender,
            'lehrberuf' => $lehrberuf,
            'bmsAktiv' => $lernender && $this->hatAktivenBmsTrack($lernender),
            'kontrastOption' => Theme::kontrastOptionVerfuegbar(),
            'praeferenzenOption' => Darstellung::praeferenzenOptionVerfuegbar(),
            'betriebTheme' => Theme::betrieb(),
            'praeferenzen' => $praeferenzen,
            'dashboardRolle' => $dashboardRolle,
            'dashboardKarten' => DashboardKarten::fuerRolle($dashboardRolle),
            'tastenkuerzelAktiv' => $praeferenzen['tastenkuerzel'] === Darstellung::TASTENKUERZEL_AN,
        ]);
    }

    public function calendar(Request $request): View
    {
        $user = $request->user();
        $lernender = $user->lernender;

        return view('settings.calendar', [
            'user' => $user,
            'lernender' => $lernender,
            'exportToken' => CalendarExport::token($user),
            'feed' => $lernender?->calendarFeeds()->first(),
        ]);
    }

    public function calendarTokenReset(Request $request): RedirectResponse
    {
        CalendarExport::resetToken($request->user());

        return redirect()->route('settings.calendar')->with('success', __('Neuer Abo-Link erzeugt.'));
    }

    public function data(): View
    {
        return view('settings.data');
    }

    private function hatAktivenBmsTrack(Lernender $lernender): bool
    {
        $heute = now()->toDateString();

        return DB::table('lernender_tracks')
            ->where('lernender_id', $lernender->lernender_id)
            ->where('track_typ', 'BMS')
            ->where('start_datum', '<=', $heute)
            ->where(fn ($q) => $q->whereNull('end_datum')->orWhere('end_datum', '>=', $heute))
            ->exists();
    }
}
