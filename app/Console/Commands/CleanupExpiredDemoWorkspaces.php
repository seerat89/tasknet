<?php

namespace App\Console\Commands;

use App\Actions\DeleteDemoWorkspace;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('tasknet:cleanup-demos')]
#[Description('Delete expired demo workspaces')]
class CleanupExpiredDemoWorkspaces extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(DeleteDemoWorkspace $deleteWorkspace): int
    {
        $deleted = 0;
        $failed = 0;

        User::query()
            ->where('is_demo', true)
            ->where('expires_at', '<=', now())
            ->lazyById()
            ->each(function (User $user) use ($deleteWorkspace, &$deleted, &$failed): void {
                try {
                    $deleteWorkspace->delete($user);
                    $deleted++;
                } catch (Throwable $exception) {
                    report($exception);
                    $failed++;
                }
            });

        $this->info("Deleted {$deleted} expired demo workspace(s).");

        if ($failed > 0) {
            $this->error("Failed to delete {$failed} expired demo workspace(s). See the log for details.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
