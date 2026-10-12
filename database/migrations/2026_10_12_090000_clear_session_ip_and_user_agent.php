<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * TaskNet no longer stores the IP address or User-Agent with sessions; discard what was stored earlier.
     */
    public function up(): void
    {
        DB::table(config('session.table', 'sessions'))->update(['ip_address' => null, 'user_agent' => null]);
    }

    /**
     * The discarded values are intentionally not restored.
     */
    public function down(): void
    {
        //
    }
};
