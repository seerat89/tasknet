<?php

namespace App\Mcp\Tools;

use App\Actions\UpdateTask;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Requests\UpdateTaskRequest;
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
#[IsIdempotent]
class UpdateTaskTool extends Tool
{
    use InteractsWithWorkspace;

    protected string $name = 'update_task';

    protected string $title = 'Update task';

    protected string $description = 'Update a task in one of your projects. Omitted fields are left unchanged; tasks cannot be moved to another project.';

    /**
     * Get the tool's input schema.
     *
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->integer()->description('ID of the project the task belongs to.')->required(),
            'task_id' => $schema->integer()->description('ID of the task to update.')->required(),
            'title' => $schema->string()->description('New task title, up to 160 characters.')->max(160),
            'description' => $schema->string()->description('New task description, up to 5000 characters.')->max(5000),
            'status' => $schema->string()->description('New task status.')->enum(TaskStatus::class),
            'priority' => $schema->string()->description('New task priority.')->enum(TaskPriority::class),
            'due_date' => $schema->string()->description('New due date in YYYY-MM-DD format.')->format('date'),
        ];
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request, UpdateTask $updateTask): ResponseFactory
    {
        $user = $this->workspaceUser($request);

        $data = $request->validate([
            'project_id' => ['required', 'integer'],
            'task_id' => ['required', 'integer'],
            ...$this->optionalRules((new UpdateTaskRequest)->rules()),
        ]);

        $project = $this->findProject($user, $data['project_id']);
        $task = $this->findTask($user, $project, $data['task_id']);

        $task = $updateTask->update($user, $project, $task, $data);

        return Response::structured(['task' => TaskResource::make($task)->resolve()]);
    }
}
