<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class ConnectionGuideController extends Controller
{
    /**
     * Show the public guide to connecting an AI client.
     *
     * The server URL comes from the configured application URL, not from the address of this request,
     * so it stays the public one however the page was reached.
     */
    public function __invoke(): View
    {
        return view('connect', [
            'serverUrl' => rtrim((string) config('app.url'), '/').route('mcp.server', absolute: false),
        ]);
    }
}
