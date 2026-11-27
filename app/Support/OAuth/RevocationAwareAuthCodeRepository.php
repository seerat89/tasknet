<?php

namespace App\Support\OAuth;

use Laravel\Passport\Bridge\AuthCodeRepository;
use Laravel\Passport\Passport;

/**
 * Tells RevocationAwareRefreshTokenRepository which authorization code a token request relies on,
 * so a code exchange overtaken by revocation issues nothing.
 */
class RevocationAwareAuthCodeRepository extends AuthCodeRepository
{
    public function isAuthCodeRevoked(string $codeId): bool
    {
        RevocationAwareRefreshTokenRepository::relyOn(Passport::authCode()->newQuery()->whereKey($codeId));

        return parent::isAuthCodeRevoked($codeId);
    }
}
