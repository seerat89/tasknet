<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * What one workspace's relationship with one MCP client looked like: when the user last approved it,
 * and when it last used the workspace successfully.
 *
 * This never grants access. Passport's tokens alone decide what a client may do.
 */
class McpClientAuthorization extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'authorized_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }
}
