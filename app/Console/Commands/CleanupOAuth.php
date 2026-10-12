<?php

namespace App\Console\Commands;

use App\Actions\CleanupStaleMcpClients;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tasknet:cleanup-oauth')]
#[Description('Purge old OAuth tokens and delete stale dynamically registered MCP clients')]
class CleanupOAuth extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(CleanupStaleMcpClients $staleClients): int
    {
        // Expired access-token rows are kept for the whole refresh-token lifetime. A refresh token
        // only points at its access token, and that row is how TaskNet knows which user and client
        // the refresh token belongs to, so it must outlive any refresh token that could still work.
        // Passport's --hours is how long an expired item is retained after it expired.
        $retentionHours = config('tasknet.mcp.oauth.refresh_token_days') * 24;

        if ($this->call('passport:purge', ['--hours' => $retentionHours]) !== self::SUCCESS) {
            return self::FAILURE;
        }

        // Clients go last: purging first releases the records that would otherwise keep them alive.
        $deleted = $staleClients->cleanup();

        $this->info("Deleted {$deleted} stale MCP client(s).");

        return self::SUCCESS;
    }
}
