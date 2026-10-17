<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Runs the daily cleanup when Vercel Cron calls it, because production has no Laravel scheduler process.
 *
 * Vercel sends `Authorization: Bearer <CRON_SECRET>`; without that secret configured, nothing runs.
 * It reuses the same Artisan commands as the local schedule, in the same order.
 */
class ScheduledCleanupController extends Controller
{
    private const COMMANDS = ['tasknet:cleanup-demos', 'tasknet:cleanup-oauth'];

    public function __invoke(Request $request): JsonResponse
    {
        $secret = (string) config('tasknet.cron.secret');

        if ($secret === '' || ! hash_equals("Bearer {$secret}", (string) $request->header('Authorization'))) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $results = [];
        $failed = false;

        // Each command runs even if the one before it failed; both are safe to rerun.
        foreach (self::COMMANDS as $command) {
            $output = new BufferedOutput;
            $status = Artisan::call($command, [], $output);

            $results[$command] = ['status' => $status === 0 ? 'ok' : 'failed', 'output' => trim($output->fetch())];
            $failed = $failed || $status !== 0;
        }

        return response()->json($results, $failed ? 500 : 200);
    }
}
