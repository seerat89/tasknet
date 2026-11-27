<?php

namespace App\Actions;

use App\Models\McpClientAuthorization;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Passport;

class RevokeMcpClientAccess
{
    /**
     * Remove every authorization one user has given one OAuth client.
     *
     * Only rows belonging to this user and this client are touched: all their access tokens, the
     * refresh tokens issued for those access tokens, any authorization codes that have not been
     * exchanged yet, and the record of when the client was approved and last used the workspace.
     * The client registration itself is never deleted, because dynamically
     * registered clients have no owner and may be authorized by other workspaces. The client can
     * be approved again through the normal OAuth flow.
     */
    public function revoke(User $user, string $clientId): void
    {
        DB::transaction(function () use ($user, $clientId): void {
            $accessTokens = Passport::token()->newQuery()
                ->where('user_id', $user->getKey())
                ->where('client_id', $clientId);
            $refreshTokens = fn (): Builder => Passport::refreshToken()->newQuery()
                ->whereIn('access_token_id', (clone $accessTokens)->select('id'));

            // The order matters. First take hold of everything a token request could be relying on:
            // codes by deleting them, refresh tokens by locking them. A request still being handled then
            // either waits for this transaction and finds its credential gone, or has already stored its
            // new tokens, which the deletes below see. See RevocationAwareRefreshTokenRepository.
            Passport::authCode()->newQuery()
                ->where('user_id', $user->getKey())
                ->where('client_id', $clientId)
                ->delete();

            $refreshTokens()->lockForUpdate()->get(['id']);

            $refreshTokens()->delete();
            $accessTokens->delete();

            McpClientAuthorization::query()
                ->where('user_id', $user->getKey())
                ->where('client_id', $clientId)
                ->delete();
        });
    }
}
