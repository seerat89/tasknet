<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Demo Workspaces
    |--------------------------------------------------------------------------
    |
    | TaskNet is a public, temporary MCP testing environment. Every visitor
    | gets an isolated demo workspace that is deleted once it has expired.
    |
    */

    'demo' => [
        'lifetime_days' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Workspace Limits
    |--------------------------------------------------------------------------
    |
    | Maximum number of records a single demo workspace may own. The web UI
    | and MCP must enforce the same limits.
    |
    */

    'limits' => [
        'projects' => 10,
        'tasks' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | MCP
    |--------------------------------------------------------------------------
    |
    | requests_per_minute: maximum authenticated /mcp requests per minute for
    | each workspace.
    |
    | oauth: lifetimes for the OAuth state external MCP clients rely on.
    |  - access_token_days / refresh_token_days: how long issued tokens work.
    |  - unused_client_days: how long a dynamically registered client must have
    |    existed before tasknet:cleanup-oauth may remove it, and then only if
    |    Passport no longer references it.
    |
    | Expired access-token rows are kept for the whole refresh-token lifetime
    | (see tasknet:cleanup-oauth), so a refresh token can always be traced to
    | its user and client.
    |
    */

    'mcp' => [
        'requests_per_minute' => (int) env('MCP_RATE_LIMIT', 120),

        'oauth' => [
            'access_token_days' => 1,
            'refresh_token_days' => 30,
            'unused_client_days' => 30,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Privacy Contact
    |--------------------------------------------------------------------------
    |
    | Public contact address shown on the privacy page. Leave empty until a
    | suitable address exists; the page then says one will be listed.
    |
    */

    'privacy_contact' => env('TASKNET_PRIVACY_CONTACT'),

    /*
    |--------------------------------------------------------------------------
    | Privacy Page Identity
    |--------------------------------------------------------------------------
    |
    | Display names shown on the privacy page. They are optional; the page
    | omits what is not configured. Supply them at deployment.
    |
    */

    'operator_name' => env('TASKNET_OPERATOR_NAME'),

    'hosting_provider' => env('TASKNET_HOSTING_PROVIDER'),

    'database_provider' => env('TASKNET_DATABASE_PROVIDER'),

];
