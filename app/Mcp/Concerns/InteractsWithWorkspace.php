<?php

namespace App\Mcp\Concerns;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;

/**
 * Shared helpers that keep MCP tools scoped to the authenticated user's workspace.
 */
trait InteractsWithWorkspace
{
    /**
     * Get the user authenticated through OAuth. Ownership is never read from tool input.
     *
     * @throws AuthenticationException
     */
    protected function workspaceUser(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
    }

    /**
     * Find one of the user's projects. Missing and foreign projects look identical.
     *
     * @throws AuthorizationException
     */
    protected function findProject(User $user, int $projectId): Project
    {
        $project = $user->projects()->find($projectId);

        if ($project === null) {
            throw new AuthorizationException('Project not found.');
        }

        Gate::forUser($user)->authorize('view', $project);

        return $project;
    }

    /**
     * Find a task inside one of the user's projects. Missing and foreign tasks look identical.
     *
     * @throws AuthorizationException
     */
    protected function findTask(User $user, Project $project, int $taskId): Task
    {
        $task = $project->tasks()->find($taskId);

        if ($task === null) {
            throw new AuthorizationException('Task not found.');
        }

        Gate::forUser($user)->authorize('view', $task);

        return $task;
    }

    /**
     * Turn validation rules into rules for fields that may be omitted but, when sent, still need a value.
     *
     * @param  array<string, array<int, mixed>>  $rules
     * @param  array<int, string>|null  $fields  Limit the change to these fields; null means every field.
     * @return array<string, array<int, mixed>>
     */
    protected function optionalRules(array $rules, ?array $fields = null): array
    {
        foreach ($rules as $field => $fieldRules) {
            if ($fields !== null && ! in_array($field, $fields, true)) {
                continue;
            }

            $rules[$field] = ['sometimes', ...array_map(fn (mixed $rule): mixed => $rule === 'required' ? 'filled' : $rule, $fieldRules)];
        }

        return $rules;
    }
}
