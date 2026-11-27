<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Keep when a workspace approved an MCP client and when that client last used the workspace.
     *
     * Nothing is backfilled: tokens only record when they were last issued, which a refresh resets,
     * so neither date can be known for access granted before this table existed. A missing row or an
     * empty column means "not recorded".
     */
    public function up(): void
    {
        Schema::create('mcp_client_authorizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('client_id')->index()->constrained('oauth_clients')->cascadeOnDelete();
            $table->timestamp('authorized_at')->nullable();
            $table->timestamp('last_used_at')->nullable();

            $table->unique(['user_id', 'client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_client_authorizations');
    }
};
