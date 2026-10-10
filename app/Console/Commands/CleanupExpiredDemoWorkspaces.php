<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tasknet:cleanup-demos')]
#[Description('Delete expired demo workspaces')]
class CleanupExpiredDemoWorkspaces extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $deleted = 0;

        User::query()
            ->where('is_demo', true)
            ->where('expires_at', '<=', now())
            ->lazyById()
            ->each(function (User $user) use (&$deleted): void {
                $user->delete();
                $deleted++;
            });

        $this->info("Deleted {$deleted} expired demo workspace(s).");

        return self::SUCCESS;
    }
}
