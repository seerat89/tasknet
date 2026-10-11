<?php

namespace App\Actions;

use App\Enums\ChangeType;
use App\Events\ProjectChanged;
use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

class UpdateProject
{
    /**
     * Update the editable fields of one of the user's projects.
     *
     * @param  array{name?: string, description?: string|null}  $data
     *
     * @throws AuthorizationException
     */
    public function update(User $user, Project $project, array $data): Project
    {
        Gate::forUser($user)->authorize('update', $project);

        $project->update(Arr::only($data, ['name', 'description']));

        ProjectChanged::announce($user, ChangeType::Updated);

        return $project;
    }
}
