<?php

namespace App\Actions;

use App\Enums\ChangeType;
use App\Events\TaskChanged;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CreateTask
{
    /**
     * Create a task in one of the user's projects, within the workspace-wide task limit.
     *
     * @param  array{title: string, description?: string|null, status?: string, priority?: string, due_date?: string|null}  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function create(User $user, Project $project, array $data): Task
    {
        Gate::forUser($user)->authorize('create', [Task::class, $project]);

        $task = DB::transaction(function () use ($user, $project, $data): Task {
            // Serialize concurrent creations for this workspace so the limit cannot be exceeded.
            User::query()->whereKey($user->getKey())->lockForUpdate()->first();

            $limit = config('tasknet.limits.tasks');

            if ($user->tasks()->count() >= $limit) {
                throw ValidationException::withMessages([
                    'title' => "You can create up to {$limit} tasks in a demo workspace.",
                ]);
            }

            return $project->tasks()->create($data);
        });

        TaskChanged::announce($user, ChangeType::Created);

        return $task;
    }
}
