<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Marks an OAuth client as created through MCP dynamic client registration.
 *
 * Only these clients are ever cleaned up automatically. The OAuth client itself is shared by
 * every workspace that authorizes it, so it has no owner here.
 */
class McpDynamicClient extends Model
{
    protected $primaryKey = 'client_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
