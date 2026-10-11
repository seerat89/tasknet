<?php

namespace App\Actions;

use App\Enums\ChangeType;
use App\Events\TaskChanged;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;

class DeleteTask
{
    /**
     * Delete a task inside one of the user's projects.
     *
     * @throws AuthorizationException
     * @throws ModelNotFoundException
     */
    public function delete(User $user, Project $project, Task $task): void
    {
        if ($task->project_id !== $project->id) {
            throw (new ModelNotFoundException)->setModel(Task::class, [$task->id]);
        }

        Gate::forUser($user)->authorize('delete', $task);

        $task->delete();

        TaskChanged::announce($user, ChangeType::Deleted);
    }
}
