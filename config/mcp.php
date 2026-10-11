<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Redirect Domains
    |--------------------------------------------------------------------------
    |
    | Origins (scheme and host) that dynamically registered OAuth clients may
    | use for redirect URIs. Laravel MCP also accepts any loopback callback
    | (any port) when http://localhost or http://127.0.0.1 is listed, which
    | covers MCP Inspector and local clients.
    |
    | Set MCP_REDIRECT_DOMAINS to a comma-separated list. A "*" wildcard is
    | deliberately ignored: a client must never be able to send authorization
    | codes to an arbitrary host.
    |
    */

    'redirect_domains' => array_values(array_filter(
        array_map('trim', explode(',', (string) env('MCP_REDIRECT_DOMAINS', 'http://localhost,http://127.0.0.1'))),
        fn (string $domain): bool => $domain !== '' && ! str_contains($domain, '*'),
    )),

    /*
    |--------------------------------------------------------------------------
    | Allowed Custom Schemes
    |--------------------------------------------------------------------------
    |
    | Private-use URI schemes (RFC 8252) accepted for native client callbacks.
    |
    */

    'custom_schemes' => [],

    /*
    |--------------------------------------------------------------------------
    | Authorization Server
    |--------------------------------------------------------------------------
    |
    | The OAuth authorization server issuer identifier. When null, this
    | defaults to `url('/')`.
    |
    */

    'authorization_server' => null,

    /*
    |--------------------------------------------------------------------------
    | Tool Search
    |--------------------------------------------------------------------------
    |
    | Limits enforced during tool search (see the package defaults).
    |
    */

    'tool_search' => [
        'max_tool_calls' => 10,
        'max_output_bytes' => 65_536,
    ],

];
