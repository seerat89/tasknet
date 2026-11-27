<?php

namespace App\Actions;

use App\Models\McpClientAuthorization;
use App\Models\User;

class RecordMcpActivity
{
    /**
     * Note that a client has just used a workspace successfully.
     *
     * The stored time is only moved once it is older than the configured interval, so a busy client
     * costs one read per operation and at most one write per interval.
     */
    public function record(User $user, string $clientId): void
    {
        $keys = ['user_id' => $user->getKey(), 'client_id' => $clientId];
        $lastUsed = McpClientAuthorization::query()->where($keys)->value('last_used_at');

        if ($lastUsed?->gt(now()->subMinutes(config('tasknet.mcp.activity_interval_minutes')))) {
            return;
        }

        // An upsert, so simultaneous first requests cannot collide. It leaves authorized_at alone: for a
        // client approved before that date was kept, the new row has none.
        McpClientAuthorization::query()->upsert(
            [[...$keys, 'last_used_at' => now()]],
            ['user_id', 'client_id'],
            ['last_used_at'],
        );
    }
}
