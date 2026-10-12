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
    | MCP Rate Limit
    |--------------------------------------------------------------------------
    |
    | Maximum authenticated /mcp requests per minute for each workspace.
    |
    */

    'mcp' => [
        'requests_per_minute' => (int) env('MCP_RATE_LIMIT', 120),
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
