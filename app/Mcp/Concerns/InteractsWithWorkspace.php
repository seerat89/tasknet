<?php

namespace App\Mcp\Concerns;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Support\ValidationMessages;

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
     * Run a write operation and mark the failures that are known to have changed nothing.
     *
     * The error text is what Laravel MCP would send anyway. The marks go in `_meta`, which is not shown to
     * the model: validation failures name their fields, so the interactive form can mark the right inputs,
     * and authorization failures are flagged. Any other error is left unmarked because it may have happened
     * after the record was saved, so the view must not treat it as safe to repeat.
     *
     * @param  Closure(): ResponseFactory  $operation
     */
    protected function reportingRefusals(Closure $operation): ResponseFactory
    {
        try {
            return $operation();
        } catch (ValidationException $exception) {
            return Response::make(Response::error(ValidationMessages::from($exception)))
                ->withMeta('tasknet/validationErrors', $exception->errors());
        } catch (AuthorizationException $exception) {
            return Response::make(Response::error($exception->getMessage()))->withMeta('tasknet/refused', true);
        }
    }

    /**
     * Treat a blank value for an optional field as "no value", as the web UI does. Without this a blank
     * due date would pass validation and reach the database as an invalid date.
     *
     * @param  array<int, string>  $fields
     */
    protected function blankAsNull(Request $request, array $fields): void
    {
        foreach ($fields as $field) {
            $value = $request->get($field);

            if (is_string($value) && trim($value) === '') {
                $request->merge([$field => null]);
            }
        }
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
