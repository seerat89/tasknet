<?php

namespace App\Mcp\Tools;

use App\Actions\UpdateProject;
use App\Http\Requests\UpdateProjectRequest;
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
#[IsIdempotent]
class UpdateProjectTool extends Tool
{
    use InteractsWithWorkspace;

    protected string $name = 'update_project';

    protected string $title = 'Update project';

    protected string $description = 'Update the name or description of one of your projects. Omitted fields are left unchanged.';

    /**
     * Get the tool's input schema.
     *
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->integer()->description('ID of the project to update.')->required(),
            'name' => $schema->string()->description('New project name, up to 120 characters.')->max(120),
            'description' => $schema->string()->description('New project description, up to 2000 characters.')->max(2000),
        ];
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request, UpdateProject $updateProject): ResponseFactory
    {
        $user = $this->workspaceUser($request);

        $data = $request->validate([
            'project_id' => ['required', 'integer'],
            ...$this->optionalRules((new UpdateProjectRequest)->rules()),
        ]);

        $project = $this->findProject($user, $data['project_id']);

        $project = $updateProject->update($user, $project, $data)->loadCount('tasks');

        return Response::structured(['project' => ProjectResource::make($project)->resolve()]);
    }
}
