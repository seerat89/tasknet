<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('demo_id')->nullable()->unique()->after('id');
            $table->boolean('is_demo')->default(false)->after('demo_id');
            $table->timestamp('expires_at')->nullable()->index()->after('remember_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['demo_id']);
            $table->dropIndex(['expires_at']);
            $table->dropColumn(['demo_id', 'is_demo', 'expires_at']);
        });
    }
};
