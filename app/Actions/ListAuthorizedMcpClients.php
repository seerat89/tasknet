<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;

class ListAuthorizedMcpClients
{
    /**
     * Get the OAuth clients a user can currently use to reach their workspace.
     *
     * A client counts as authorized when the user still holds one of:
     *  - an access token for it that is not revoked and not expired, or
     *  - a refresh token for it that is not revoked and not expired. Passport's refresh grant only
     *    checks the refresh token itself, so such a client can obtain new access without asking again.
     *
     * Revoked or expired token rows that merely linger until the next purge do not count, and an
     * unrevoked client registration is required. Several tokens for one client give one entry.
     *
     * @return Collection<int, Client>
     */
    public function get(User $user): Collection
    {
        $userTokens = fn (): Builder => Passport::token()->newQuery()->where('user_id', $user->getKey());

        $viaAccessToken = $userTokens()
            ->where('revoked', false)
            ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->pluck('client_id');

        $liveRefreshTokens = Passport::refreshToken()->newQuery()
            ->where('revoked', false)
            ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->select('access_token_id');

        $viaRefreshToken = $userTokens()->whereIn('id', $liveRefreshTokens)->pluck('client_id');

        $clientIds = $viaAccessToken->merge($viaRefreshToken)->unique()->values();

        return Passport::client()->newQuery()
            ->whereKey($clientIds)
            ->where('revoked', false)
            ->orderBy('name')
            ->get();
    }
}
