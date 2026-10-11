<?php

namespace App\Actions;

use App\Enums\ChangeType;
use App\Events\ProjectChanged;
use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

class DeleteProject
{
    /**
     * Delete one of the user's projects. Its tasks are removed by the database cascade.
     *
     * @throws AuthorizationException
     */
    public function delete(User $user, Project $project): void
    {
        Gate::forUser($user)->authorize('delete', $project);

        $project->delete();

        ProjectChanged::announce($user, ChangeType::Deleted);
    }
}
