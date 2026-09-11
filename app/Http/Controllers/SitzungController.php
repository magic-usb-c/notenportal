<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SitzungController extends Controller
{
    /**
     * Keep-Alive: tut inhaltlich nichts – `StartSession` speichert die Session nach jeder
     * Antwort ohnehin und setzt damit `last_activity` neu, wie ein normaler Seitenaufruf.
     */
    public function verlaengern(Request $request): Response
    {
        return response()->noContent();
    }
}
