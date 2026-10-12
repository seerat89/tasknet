<?php

namespace App\Support;

use Illuminate\Session\DatabaseSessionHandler;

/**
 * Laravel's database session handler, minus the raw IP address and User-Agent.
 *
 * TaskNet intentionally omits Laravel's default IP address and User-Agent session metadata;
 * the sessions table has no columns for them.
 */
class PrivacyDatabaseSessionHandler extends DatabaseSessionHandler
{
    /**
     * Skip the request information (IP address and User-Agent).
     *
     * @param  array  $payload
     * @return $this
     */
    protected function addRequestInformation(&$payload)
    {
        return $this;
    }
}
