<?php

namespace App\Mcp\Tools;

use App\Actions\CreateTask;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Resources\TaskResource;
use App\Mcp\Concerns\InteractsWithWorkspace;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsDestructive(false)]
#[IsIdempotent(false)]
class CreateTaskTool extends Tool
{
    use InteractsWithWorkspace;

    protected string $name = 'create_task';

    protected string $title = 'Create task';

    protected string $description = 'Create a task in one of your projects. A workspace can hold at most 100 tasks in total. Set status, priority and due_date here when you know them, instead of updating the task afterwards. Status defaults to todo and priority to medium.';

    /**
     * Get the tool's input schema.
     *
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->integer()->description('ID of the project to add the task to.')->required(),
            'title' => $schema->string()->description('Task title, up to 160 characters.')->max(160)->required(),
            'description' => $schema->string()->description('Optional task description, up to 5000 characters.')->max(5000),
            'status' => $schema->string()->description('Task status. Defaults to todo.')->enum(TaskStatus::class),
            'priority' => $schema->string()->description('Task priority. Defaults to medium.')->enum(TaskPriority::class),
            'due_date' => $schema->string()->description('Optional due date in YYYY-MM-DD format.')->format('date'),
        ];
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request, CreateTask $createTask): ResponseFactory
    {
        $user = $this->workspaceUser($request);

        $data = $request->validate([
            'project_id' => ['required', 'integer'],
            ...$this->optionalRules((new StoreTaskRequest)->rules(), ['status', 'priority']),
        ]);

        $project = $this->findProject($user, $data['project_id']);

        $task = $createTask->create($user, $project, $data)->refresh();

        return Response::structured(['task' => TaskResource::make($task)->resolve()]);
    }
}
