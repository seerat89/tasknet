<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Laravel\Passport\Passport;

class DeleteDemoWorkspace
{
    /**
     * Permanently delete a demo workspace and the TaskNet data that belongs to it.
     *
     * This is the single deletion path, used both when a user deletes their workspace and by the
     * scheduled cleanup of expired workspaces. Projects and tasks are removed by the database
     * cascade from the user. Rows that no foreign key covers (sessions and OAuth records) are
     * removed explicitly. Registered OAuth clients are not touched: dynamically registered clients
     * have no owner, so they cannot be attributed to a single workspace.
     *
     * It is safe to run again for a workspace that has already been deleted.
     *
     * @throws InvalidArgumentException
     */
    public function delete(User $user): void
    {
        if (! $user->is_demo) {
            throw new InvalidArgumentException('Only demo workspaces can be deleted.');
        }

        $userId = $user->getKey();

        DB::transaction(function () use ($userId): void {
            $accessTokenIds = Passport::token()->newQuery()->where('user_id', $userId)->select('id');

            Passport::refreshToken()->newQuery()->whereIn('access_token_id', $accessTokenIds)->delete();
            Passport::token()->newQuery()->where('user_id', $userId)->delete();
            Passport::authCode()->newQuery()->where('user_id', $userId)->delete();

            DB::table(config('session.table', 'sessions'))->where('user_id', $userId)->delete();

            User::query()->whereKey($userId)->delete();
        });
    }
}
