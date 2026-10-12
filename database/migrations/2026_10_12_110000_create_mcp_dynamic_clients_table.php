<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Track the OAuth clients created by MCP dynamic client registration.
     *
     * Clients registered before this table existed are deliberately not recorded: they cannot be
     * told apart from other Passport clients with certainty, so they are never cleaned up automatically.
     */
    public function up(): void
    {
        Schema::create('mcp_dynamic_clients', function (Blueprint $table) {
            $table->foreignUuid('client_id')->primary()->constrained('oauth_clients')->cascadeOnDelete();
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_dynamic_clients');
    }
};
