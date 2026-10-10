<?php

namespace App\Actions;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;

class UpdateTask
{
    /**
     * Update the editable fields of a task inside one of the user's projects.
     *
     * @param  array{title?: string, description?: string|null, status?: string, priority?: string, due_date?: string|null}  $data
     *
     * @throws AuthorizationException
     * @throws ModelNotFoundException
     */
    public function update(User $user, Project $project, Task $task, array $data): Task
    {
        if ($task->project_id !== $project->id) {
            throw (new ModelNotFoundException)->setModel(Task::class, [$task->id]);
        }

        Gate::forUser($user)->authorize('update', $task);

        $task->update(Arr::only($data, ['title', 'description', 'status', 'priority', 'due_date']));

        return $task;
    }
}
