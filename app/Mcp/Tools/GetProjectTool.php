<?php

namespace App\Mcp\Tools;

use App\Http\Resources\ProjectResource;
use App\Mcp\Concerns\InteractsWithWorkspace;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class GetProjectTool extends Tool
{
    use InteractsWithWorkspace;

    protected string $name = 'get_project';

    protected string $title = 'Get project';

    protected string $description = 'Get one of your projects by ID, including its task count.';

    /**
     * Get the tool's input schema.
     *
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->integer()->description('ID of the project.')->required(),
        ];
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory
    {
        $user = $this->workspaceUser($request);

        $data = $request->validate(['project_id' => ['required', 'integer']]);

        $project = $this->findProject($user, $data['project_id'])->loadCount('tasks');

        return Response::structured(['project' => ProjectResource::make($project)->resolve()]);
    }
}
