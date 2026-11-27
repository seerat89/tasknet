<?php

namespace App\Enums;

/**
 * The AI services TaskNet can recognise behind an OAuth client.
 *
 * A client chooses its own name when it registers, so the name proves nothing. Where TaskNet sends
 * authorization codes does: a client whose every callback is on one of these hosts can only be
 * completed there.
 */
enum McpClientProvider: string
{
    case ChatGpt = 'ChatGPT';
    case Claude = 'Claude';

    /**
     * @param  array<int, string>  $redirectUris
     */
    public static function fromRedirectUris(array $redirectUris): ?self
    {
        $providers = array_map(self::fromRedirectUri(...), array_values($redirectUris));
        $first = $providers[0] ?? null;

        // Every callback must belong to the same service.
        return array_filter($providers, fn (?self $provider): bool => $provider !== $first) === [] ? $first : null;
    }

    /**
     * Matched on how the address literally begins, so that nothing depends on a URL parser agreeing
     * with the browser about where the host ends.
     */
    private static function fromRedirectUri(string $uri): ?self
    {
        return match (true) {
            str_starts_with($uri, 'https://chatgpt.com/') => self::ChatGpt,
            str_starts_with($uri, 'https://claude.ai/') => self::Claude,
            default => null,
        };
    }

    /**
     * The host users see named on the consent screen.
     */
    public function host(): string
    {
        return match ($this) {
            self::ChatGpt => 'chatgpt.com',
            self::Claude => 'claude.ai',
        };
    }
}
