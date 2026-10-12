<?php

namespace App\Http\Controllers;

use App\Actions\ListAuthorizedMcpClients;
use App\Actions\RevokeMcpClientAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AuthorizedMcpClientController extends Controller
{
    /**
     * Revoke the signed-in workspace's access for one authorized MCP client.
     *
     * The client in the URL only selects among the clients this workspace has authorized.
     * Anything else is a 404, and nothing outside the signed-in user's own rows is ever touched.
     */
    public function destroy(Request $request, string $client, ListAuthorizedMcpClients $authorized, RevokeMcpClientAccess $revoke): RedirectResponse
    {
        $user = $request->user();

        $revokedClient = $authorized->get($user)->firstWhere('id', $client);

        abort_if($revokedClient === null, 404);

        $revoke->revoke($user, $revokedClient->getKey());

        $name = filled($revokedClient->name) ? $revokedClient->name : 'MCP client';

        return redirect()->route('settings')->with('success', "Access revoked for {$name}.");
    }
}
