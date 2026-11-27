<?php

namespace App\Actions;

use App\Enums\McpClientProvider;
use App\Models\McpClientAuthorization;
use App\Models\User;
use App\Support\AuthorizedMcpClient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;

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
        [$accessTokens, $refreshable] = $this->liveTokens($user);

        return $this->clients($accessTokens->merge($refreshable));
    }

    /**
     * Find a client this user may revoke: any client they hold a token row for.
     *
     * This is deliberately wider than get(). While a client is renewing its tokens there is a moment when
     * none of them is live, and revoking must still work then.
     */
    public function revocable(User $user, string $clientId): ?Client
    {
        // The value comes from the URL, and PostgreSQL rejects anything but a UUID in these columns.
        $held = Str::isUuid($clientId)
            && Passport::token()->newQuery()->where('user_id', $user->getKey())->where('client_id', $clientId)->exists();

        return $held ? Passport::client()->newQuery()->whereKey($clientId)->first() : null;
    }

    /**
     * The same clients, with what TaskNet knows about each one's access to this workspace.
     *
     * @return Collection<int, AuthorizedMcpClient>
     */
    public function details(User $user): Collection
    {
        [$accessTokens, $refreshable, $refreshTokens] = $this->liveTokens($user);

        $records = McpClientAuthorization::query()->where('user_id', $user->getKey())->get()->keyBy('client_id');

        return $this->clients($accessTokens->merge($refreshable))->map(function (Client $client) use ($accessTokens, $refreshable, $refreshTokens, $records): AuthorizedMcpClient {
            $access = $accessTokens->where('client_id', $client->getKey());
            $renewable = $refreshable->where('client_id', $client->getKey());
            $record = $records->get($client->getKey());

            return new AuthorizedMcpClient(
                id: (string) $client->getKey(),
                name: filled($client->name) ? $client->name : 'MCP client',
                provider: McpClientProvider::fromRedirectUris($client->redirect_uris),
                scopes: $access->merge($renewable)->pluck('scopes')->flatten()->unique()->values()->all(),
                hasAccessToken: $access->isNotEmpty(),
                accessUntil: $access->pluck('expires_at')
                    ->merge($refreshTokens->whereIn('access_token_id', $renewable->modelKeys())->pluck('expires_at'))
                    ->filter()
                    ->max(),
                authorizedAt: $record?->authorized_at,
                lastUsedAt: $record?->last_used_at,
            );
        });
    }

    /**
     * Read, in three queries, the user's tokens that can still lead to access: the live access tokens,
     * the access tokens (live or not) that have a live refresh token, and those refresh tokens.
     *
     * @return array{0: Collection<int, Token>, 1: Collection<int, Token>, 2: Collection<int, RefreshToken>}
     */
    private function liveTokens(User $user): array
    {
        $userTokens = fn (): Builder => Passport::token()->newQuery()->where('user_id', $user->getKey());

        $accessTokens = $userTokens()
            ->where('revoked', false)
            ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->get(['id', 'client_id', 'scopes', 'expires_at']);

        $refreshTokens = Passport::refreshToken()->newQuery()
            ->where('revoked', false)
            ->where(fn (Builder $query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->whereIn('access_token_id', $userTokens()->select('id'))
            ->get(['id', 'access_token_id', 'expires_at']);

        $refreshable = $userTokens()
            ->whereIn('id', $refreshTokens->pluck('access_token_id'))
            ->get(['id', 'client_id', 'scopes']);

        return [$accessTokens, $refreshable, $refreshTokens];
    }

    /**
     * @param  Collection<int, Token>  $tokens
     * @return Collection<int, Client>
     */
    private function clients(Collection $tokens): Collection
    {
        return Passport::client()->newQuery()
            ->whereKey($tokens->pluck('client_id')->unique()->values())
            ->where('revoked', false)
            ->orderBy('name')
            ->get();
    }
}
