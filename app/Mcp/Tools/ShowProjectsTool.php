<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\InteractsWithWorkspace;
use App\Mcp\Concerns\OpensViews;
use App\Mcp\Resources\TaskNetApp;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\RendersApp;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[RendersApp(resource: TaskNetApp::class)]
class ShowProjectsTool extends Tool
{
    use InteractsWithWorkspace;
    use OpensViews;

    protected string $name = 'show_projects';

    protected string $title = 'Show projects';

    protected string $description = 'Open the interactive project explorer, or one project with its progress when project_id is given. Use it when the user wants to see or browse projects. It changes nothing. To look up data for your own answer, use list_projects or get_project instead.';

    /**
     * Get the tool's input schema.
     *
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->integer()->description('Optional ID of the project to open.'),
        ];
    }

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory
    {
        $user = $this->workspaceUser($request);

        $this->blankAsNull($request, ['project_id']);

        $data = $request->validate(['project_id' => ['nullable', 'integer']]);

        Gate::forUser($user)->authorize('viewAny', Project::class);

        if (! isset($data['project_id'])) {
            return $this->openView('projects', 'the project explorer', 'list_projects');
        }

        $project = $this->findProject($user, $data['project_id']);

        return $this->openView('project', "project {$project->id}", 'get_project', ['project_id' => $project->id]);
    }
}
