<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\InteractsWithWorkspace;
use App\Mcp\Concerns\OpensViews;
use App\Mcp\Resources\TaskNetApp;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\RendersApp;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[RendersApp(resource: TaskNetApp::class)]
class ShowTaskFormTool extends Tool
{
    use InteractsWithWorkspace;
    use OpensViews;

    protected string $name = 'show_task_form';

    protected string $title = 'Show task form';

    protected string $description = 'Open a form for the user to create a task, or to edit one when project_id and task_id are given. Opening it changes nothing. Use it only when the user wants to fill in a form: if they already said what to create or change, call create_task or update_task directly.';

    /**
     * Get the tool's input schema.
     *
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->integer()->description('For a new task, the project to preselect. Required when editing a task.'),
            'task_id' => $schema->integer()->description('ID of the task to edit. Omit to create a new task.'),
        ];
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory
    {
        $user = $this->workspaceUser($request);

        $this->blankAsNull($request, ['project_id', 'task_id']);

        $data = $request->validate([
            'project_id' => ['nullable', 'required_with:task_id', 'integer'],
            'task_id' => ['nullable', 'integer'],
        ]);

        Gate::forUser($user)->authorize('viewAny', Project::class);

        if (! isset($data['project_id'])) {
            return $this->openView('task_form', 'a form for a new task', 'create_task');
        }

        $project = $this->findProject($user, $data['project_id']);

        if (! isset($data['task_id'])) {
            Gate::forUser($user)->authorize('create', [Task::class, $project]);

            return $this->openView('task_form', "a form for a new task in project {$project->id}", 'create_task', ['project_id' => $project->id]);
        }

        $task = $this->findTask($user, $project, $data['task_id']);

        Gate::forUser($user)->authorize('update', $task);

        return $this->openView('task_form', "the edit form for task {$task->id}", 'update_task', ['project_id' => $project->id, 'task_id' => $task->id]);
    }
}
