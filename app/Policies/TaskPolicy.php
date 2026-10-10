<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    /**
     * Determine whether the user can view the task.
     */
    public function view(User $user, Task $task): bool
    {
        return $this->ownsTask($user, $task);
    }

    /**
     * Determine whether the user can create a task inside the given project.
     *
     * Checked as `can('create', [Task::class, $project])`.
     */
    public function create(User $user, Project $project): bool
    {
        return $user->isActiveDemo() && $project->user_id === $user->id;
    }

    /**
     * Determine whether the user can update the task.
     */
    public function update(User $user, Task $task): bool
    {
        return $this->ownsTask($user, $task);
    }

    /**
     * Determine whether the user can delete the task.
     */
    public function delete(User $user, Task $task): bool
    {
        return $this->ownsTask($user, $task);
    }

    /**
     * Task ownership is derived through its project.
     */
    private function ownsTask(User $user, Task $task): bool
    {
        return $task->project->user_id === $user->id;
    }
}
