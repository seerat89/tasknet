<?php

namespace App\Http\Controllers;

use App\Actions\DeleteDemoWorkspace;
use App\Http\Requests\DeleteWorkspaceRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WorkspaceController extends Controller
{
    /**
     * Show the workspace settings.
     */
    public function show(Request $request): View
    {
        return view('workspace.settings', ['user' => $request->user()]);
    }

    /**
     * Permanently delete the signed-in user's own workspace.
     */
    public function destroy(DeleteWorkspaceRequest $request, DeleteDemoWorkspace $deleteWorkspace): RedirectResponse
    {
        $deleteWorkspace->delete($request->user());

        Auth::guard()->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('success', 'Workspace deleted.');
    }
}
