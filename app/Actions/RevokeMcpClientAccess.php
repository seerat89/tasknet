<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;

class RevokeMcpClientAccess
{
    /**
     * Remove every authorization one user has given one OAuth client.
     *
     * Only rows belonging to this user and this client are touched: all their access tokens, the
     * refresh tokens issued for those access tokens, and any authorization codes that have not
     * been exchanged yet. The client registration itself is never deleted, because dynamically
     * registered clients have no owner and may be authorized by other workspaces. The client can
     * be approved again through the normal OAuth flow.
     */
    public function revoke(User $user, string $clientId): void
    {
        DB::transaction(function () use ($user, $clientId): void {
            $accessTokens = Passport::token()->newQuery()
                ->where('user_id', $user->getKey())
                ->where('client_id', $clientId);

            Passport::refreshToken()->newQuery()
                ->whereIn('access_token_id', (clone $accessTokens)->select('id'))
                ->delete();

            $accessTokens->delete();

            Passport::authCode()->newQuery()
                ->where('user_id', $user->getKey())
                ->where('client_id', $clientId)
                ->delete();
        });
    }
}
