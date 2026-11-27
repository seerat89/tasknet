<?php

namespace App\Actions;

use App\Models\McpClientAuthorization;

class RecordMcpAuthorization
{
    /**
     * Note that the user has just approved a client. Approving it again moves the date forward.
     */
    public function record(int|string $userId, string $clientId): void
    {
        McpClientAuthorization::query()->updateOrCreate(
            ['user_id' => $userId, 'client_id' => $clientId],
            ['authorized_at' => now()],
        );
    }
}
