<?php

namespace App\Mcp\Tools;

use App\Actions\CreateProject;
use App\Http\Requests\StoreProjectRequest;
use App\Http\Resources\ProjectResource;
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
class CreateProjectTool extends Tool
{
    use InteractsWithWorkspace;

    protected string $name = 'create_project';

    protected string $title = 'Create project';

    protected string $description = 'Create a project in your workspace. A workspace can hold at most 10 projects.';

    /**
     * Get the tool's input schema.
     *
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('Project name, up to 120 characters.')->max(120)->required(),
            'description' => $schema->string()->description('Optional project description, up to 2000 characters.')->max(2000),
        ];
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request, CreateProject $createProject): ResponseFactory
    {
        $user = $this->workspaceUser($request);

        $data = $request->validate((new StoreProjectRequest)->rules());

        $project = $createProject->create($user, $data);

        return Response::structured(['project' => ProjectResource::make($project)->resolve()]);
    }
}
