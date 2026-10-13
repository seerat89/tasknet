<?php

namespace App\Http\Controllers;

use App\Actions\CreateDemoWorkspace;
use App\Http\Requests\StartDemoRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;

class DemoWorkspaceController extends Controller
{
    /**
     * Show the form that starts a demo workspace.
     */
    public function create(): View
    {
        return view('demo.create');
    }

    /**
     * Create a demo workspace, sign in as it and hand over its credentials once.
     */
    public function store(StartDemoRequest $request, CreateDemoWorkspace $createDemoWorkspace): RedirectResponse
    {
        ['user' => $user, 'password' => $password] = $createDemoWorkspace->create();

        Auth::login($user);
        $request->session()->regenerate();

        // Encrypted so the plaintext never reaches a database-backed session store.
        $request->session()->put('demo_credentials', [
            'demo_id' => $user->demo_id,
            'password' => Crypt::encryptString($password),
            'expires_at' => $user->expires_at->toIso8601String(),
        ]);

        return redirect()->route('demo.credentials');
    }
}
