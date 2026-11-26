<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\InteractsWithWorkspace;
use App\Mcp\Concerns\OpensViews;
use App\Mcp\Resources\TaskNetApp;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\RendersApp;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[RendersApp(resource: TaskNetApp::class)]
class ShowTasksTool extends Tool
{
    use InteractsWithWorkspace;
    use OpensViews;

    protected string $name = 'show_tasks';

    protected string $title = 'Show tasks';

    protected string $description = 'Open the interactive task explorer for one project, or one task when task_id is given. Use it when the user wants to see, filter or work through tasks. Opening it changes nothing. To look up data for your own answer, use list_tasks or get_task instead.';

    /**
     * Get the tool's input schema.
     *
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->integer()->description('ID of the project.')->required(),
            'task_id' => $schema->integer()->description('Optional ID of the task to open.'),
        ];
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory
    {
        $user = $this->workspaceUser($request);

        $this->blankAsNull($request, ['task_id']);

        $data = $request->validate([
            'project_id' => ['required', 'integer'],
            'task_id' => ['nullable', 'integer'],
        ]);

        $project = $this->findProject($user, $data['project_id']);

        if (! isset($data['task_id'])) {
            return $this->openView('tasks', "the tasks of project {$project->id}", 'list_tasks', ['project_id' => $project->id]);
        }

        $task = $this->findTask($user, $project, $data['task_id']);

        return $this->openView('task', "task {$task->id}", 'get_task', ['project_id' => $project->id, 'task_id' => $task->id]);
    }
}
