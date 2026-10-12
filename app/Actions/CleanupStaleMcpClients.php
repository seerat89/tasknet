<?php

namespace App\Actions;

use App\Models\McpDynamicClient;
use Laravel\Passport\Passport;

class CleanupStaleMcpClients
{
    /**
     * Delete dynamically registered OAuth clients that nothing depends on any more.
     *
     * A client is deleted only when all of these hold:
     *  - it was recorded by MCP dynamic client registration (other Passport clients are never touched);
     *  - it was registered longer ago than the unused-client grace period;
     *  - no access token, authorization code or device code still references it. Refresh tokens
     *    reach a client only through their access token, so a retained access token also protects a
     *    refresh token that may still be valid.
     *
     * Nothing else is deleted to make a client eligible. Any workspace that still holds
     * authorization state keeps the shared client alive.
     *
     * @return int The number of clients deleted.
     */
    public function cleanup(): int
    {
        $cutoff = now()->subDays(config('tasknet.mcp.oauth.unused_client_days'));

        $query = Passport::client()->newQuery()
            ->whereIn('id', McpDynamicClient::query()->where('created_at', '<', $cutoff)->select('client_id'))
            ->whereNotIn('id', Passport::token()->newQuery()->select('client_id'))
            ->whereNotIn('id', Passport::authCode()->newQuery()->select('client_id'));

        if (Passport::$deviceCodeGrantEnabled) {
            $query->whereNotIn('id', Passport::deviceCode()->newQuery()->select('client_id'));
        }

        // The marker row is removed by its foreign key.
        return $query->delete();
    }
}
