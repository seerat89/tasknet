<?php

namespace App\Support;

use Illuminate\Session\DatabaseSessionHandler;

/**
 * Laravel's database session handler, minus the raw IP address and User-Agent.
 *
 * TaskNet has no use for this data, so the ip_address and user_agent columns are
 * left in place (they are nullable framework columns) but never written.
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
