<?php

namespace App\Http\Controllers;

use App\Models\McpDynamicClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Server\Http\Controllers\OAuthRegisterController;

class RegisterMcpClientController extends OAuthRegisterController
{
    /**
     * The only chatgpt.com callbacks that may be registered: the stable redirect and the
     * per-connector redirect, as documented at https://developers.openai.com/plugins/build/auth.
     */
    private const CHATGPT_CALLBACK_PATTERN = '#\Ahttps://chatgpt\.com/(?:connector_platform_oauth_redirect|connector/oauth/[A-Za-z0-9_-]+)\z#';

    /**
     * Register a client as the package does, and record that it came from dynamic registration.
     *
     * If recording fails the whole registration is rolled back, so a client is never left untracked.
     */
    public function __invoke(Request $request): JsonResponse
    {
        return DB::transaction(function () use ($request): JsonResponse {
            $response = parent::__invoke($request);

            if ($response->getStatusCode() === 201) {
                McpDynamicClient::query()->create([
                    'client_id' => $response->getData(true)['client_id'],
                    'created_at' => now(),
                ]);
            }

            return $response;
        });
    }

    /**
     * Hold every chatgpt.com URI to the exact callback formats, even if the whole origin is
     * listed in MCP_REDIRECT_DOMAINS.
     */
    protected function isValidRedirectUri(string $value): bool
    {
        if (! parent::isValidRedirectUri($value)) {
            return false;
        }

        return strtolower((string) parse_url($value, PHP_URL_HOST)) !== 'chatgpt.com'
            || preg_match(self::CHATGPT_CALLBACK_PATTERN, $value) === 1;
    }

    /**
     * Permit the ChatGPT callbacks alongside the configured domains. The package matches these
     * as prefixes, so isValidRedirectUri() above is what limits each URI to an exact format.
     *
     * @return array<int, string>
     */
    protected function allowedDomains(): array
    {
        return [
            ...parent::allowedDomains(),
            'https://chatgpt.com/connector_platform_oauth_redirect',
            'https://chatgpt.com/connector/oauth/',
        ];
    }
}
