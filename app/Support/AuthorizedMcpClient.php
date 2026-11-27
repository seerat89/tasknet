<?php

namespace App\Support;

use App\Enums\McpClientProvider;
use Carbon\CarbonInterface;

/**
 * One client with access to a workspace, as shown in the workspace settings.
 */
readonly class AuthorizedMcpClient
{
    /**
     * @param  array<int, string>  $scopes  The scopes held by the tokens that still grant access.
     * @param  bool  $hasAccessToken  False when only a refresh token is left, so the client has to renew first.
     * @param  CarbonInterface|null  $accessUntil  When the last of those tokens expires.
     * @param  CarbonInterface|null  $authorizedAt  Null when the approval predates this record.
     * @param  CarbonInterface|null  $lastUsedAt  Null when no successful operation has been recorded.
     */
    public function __construct(
        public string $id,
        public string $name,
        public ?McpClientProvider $provider,
        public array $scopes,
        public bool $hasAccessToken,
        public ?CarbonInterface $accessUntil,
        public ?CarbonInterface $authorizedAt,
        public ?CarbonInterface $lastUsedAt,
    ) {}
}
