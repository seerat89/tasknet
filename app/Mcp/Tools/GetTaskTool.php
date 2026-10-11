<?php

namespace App\Mcp\Tools;

use App\Http\Resources\TaskResource;
use App\Mcp\Concerns\InteractsWithWorkspace;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetTaskTool extends Tool
{
    use InteractsWithWorkspace;

    protected string $name = 'get_task';

    protected string $title = 'Get task';

    protected string $description = 'Get one task by ID from one of your projects.';

    /**
     * Get the tool's input schema.
     *
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->integer()->description('ID of the project the task belongs to.')->required(),
            'task_id' => $schema->integer()->description('ID of the task.')->required(),
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
            'task_id' => ['required', 'integer'],
        ]);

        $project = $this->findProject($user, $data['project_id']);
        $task = $this->findTask($user, $project, $data['task_id']);

        return Response::structured(['task' => TaskResource::make($task)->resolve()]);
    }
}
