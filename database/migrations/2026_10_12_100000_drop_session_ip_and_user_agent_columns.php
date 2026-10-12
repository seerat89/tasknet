<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * TaskNet does not store the IP address or User-Agent with sessions.
     */
    public function up(): void
    {
        Schema::table(config('session.table', 'sessions'), function (Blueprint $table) {
            $table->dropColumn(['ip_address', 'user_agent']);
        });
    }

    /**
     * Restore the empty columns; the discarded values cannot be recovered.
     */
    public function down(): void
    {
        Schema::table(config('session.table', 'sessions'), function (Blueprint $table) {
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
        });
    }
};
