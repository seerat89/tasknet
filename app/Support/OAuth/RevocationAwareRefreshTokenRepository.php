<?php

namespace App\Support\OAuth;

use Illuminate\Database\Eloquent\Builder;
use Laravel\Passport\Bridge\RefreshTokenRepository;
use Laravel\Passport\Passport;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;

/**
 * Stops access that is revoked while a token request is being handled from coming back.
 *
 * Passport first checks the refresh token or authorization code a client presents, and then stores the new
 * tokens in separate statements. A revocation in between deletes what existed and misses what is stored
 * afterwards. So once the new tokens are stored, and before the client receives them, the presented
 * credential is looked up again: only revocation deletes it, and if it is gone the new tokens go too.
 *
 * The lookup locks the row, so a revocation that has deleted it but not yet committed is waited for.
 * RevokeMcpClientAccess locks these rows before it deletes any token; the two rely on each other.
 *
 * It overrides Passport's repository; tests/Feature/McpRevocationRaceTest.php fails if an upgrade
 * changes how tokens are checked or stored.
 */
class RevocationAwareRefreshTokenRepository extends RefreshTokenRepository
{
    /** Where the lookup of the presented credential is kept, for the current request only. */
    private const PRESENTED = 'tasknet.oauth.presented_credential';

    /**
     * Note the refresh token or authorization code this token request relies on.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $credential  A query for its row.
     */
    public static function relyOn(Builder $credential): void
    {
        request()->attributes->set(self::PRESENTED, $credential);
    }

    public function isRefreshTokenRevoked(string $tokenId): bool
    {
        self::relyOn(Passport::refreshToken()->newQuery()->whereKey($tokenId));

        return parent::isRefreshTokenRevoked($tokenId);
    }

    public function persistNewRefreshToken(RefreshTokenEntityInterface $refreshTokenEntity): void
    {
        parent::persistNewRefreshToken($refreshTokenEntity);

        $presented = request()->attributes->get(self::PRESENTED);

        // A used refresh token or code is only marked revoked, so its row is still there.
        if ($presented instanceof Builder && $presented->lockForUpdate()->first() === null) {
            Passport::refreshToken()->newQuery()->whereKey($refreshTokenEntity->getIdentifier())->delete();
            Passport::token()->newQuery()->whereKey($refreshTokenEntity->getAccessToken()->getIdentifier())->delete();

            throw OAuthServerException::invalidGrant('Access was revoked.');
        }
    }
}
