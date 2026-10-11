<?php

namespace App\Mcp\Tools;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Resources\TaskResource;
use App\Mcp\Concerns\InteractsWithWorkspace;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListTasksTool extends Tool
{
    use InteractsWithWorkspace;

    protected string $name = 'list_tasks';

    protected string $title = 'List tasks';

    protected string $description = 'List the tasks in one of your projects, optionally filtered by status or priority.';

    /**
     * Get the tool's input schema.
     *
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->integer()->description('ID of the project.')->required(),
            'status' => $schema->string()->description('Only return tasks with this status.')->enum(TaskStatus::class),
            'priority' => $schema->string()->description('Only return tasks with this priority.')->enum(TaskPriority::class),
        ];
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory
    {
        $user = $this->workspaceUser($request);

        $data = $request->validate([
            'project_id' => ['required', 'integer'],
            'status' => ['sometimes', Rule::enum(TaskStatus::class)],
            'priority' => ['sometimes', Rule::enum(TaskPriority::class)],
        ]);

        $project = $this->findProject($user, $data['project_id']);

        $tasks = $project->tasks()
            ->when(isset($data['status']), fn ($query) => $query->where('status', $data['status']))
            ->when(isset($data['priority']), fn ($query) => $query->where('priority', $data['priority']))
            ->orderBy('id')
            ->get();

        return Response::structured([
            'project_id' => $project->id,
            'tasks' => TaskResource::collection($tasks)->resolve(),
        ]);
    }
}
