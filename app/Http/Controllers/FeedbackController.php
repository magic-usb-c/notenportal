<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Feedback;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeedbackController extends Controller
{
    /**
     * «Meine Meldungen»: nur die eigenen Feedback-Einträge, neueste zuerst.
     */
    public function index(Request $request): View
    {
        $meldungen = Feedback::query()
            ->where('benutzer_id', (int) $request->user()->benutzer_id)
            ->orderByDesc('erstellt_am')
            ->paginate(25);

        return view('feedback.index', compact('meldungen'));
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'kategorie' => ['required', 'in:'.implode(',', array_keys(Feedback::KATEGORIEN))],
            'text' => ['required', 'string', 'min:3', 'max:5000'],
            'route_name' => ['nullable', 'string', 'max:150'],
            'url' => ['nullable', 'string', 'max:500'],
            'viewport' => ['nullable', 'regex:/^\d{2,5}x\d{2,5}$/'],
        ]);

        Feedback::create([
            'benutzer_id' => (int) $request->user()->benutzer_id,
            'kategorie' => $validated['kategorie'],
            'text' => $validated['text'],
            'route_name' => $validated['route_name'] ?? null,
            'url' => $validated['url'] ?? null,
            'user_agent' => $request->userAgent() ? substr($request->userAgent(), 0, 255) : null,
            'viewport' => $validated['viewport'] ?? null,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['ok' => true], 201);
        }

        return redirect()->back()->with('success', 'Danke, deine Meldung ist eingegangen.');
    }
}
