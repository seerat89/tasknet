<?php

namespace App\Actions;

use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CreateProject
{
    /**
     * Create a project owned by the given user, within the workspace project limit.
     *
     * @param  array{name: string, description?: string|null}  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function create(User $user, array $data): Project
    {
        Gate::forUser($user)->authorize('create', Project::class);

        return DB::transaction(function () use ($user, $data): Project {
            // Serialize concurrent creations for this workspace so the limit cannot be exceeded.
            User::query()->whereKey($user->getKey())->lockForUpdate()->first();

            $limit = config('tasknet.limits.projects');

            if ($user->projects()->count() >= $limit) {
                throw ValidationException::withMessages([
                    'name' => "You can create up to {$limit} projects in a demo workspace.",
                ]);
            }

            return $user->projects()->create($data);
        });
    }
}
