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
class ShowProjectFormTool extends Tool
{
    use InteractsWithWorkspace;
    use OpensViews;

    protected string $name = 'show_project_form';

    protected string $title = 'Show project form';

    protected string $description = 'Open a form for the user to create a project, or to edit one when project_id is given. Opening it changes nothing. Use it only when the user wants to fill in a form: if they already said what to create or change, call create_project or update_project directly.';

    /**
     * Get the tool's input schema.
     *
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'project_id' => $schema->integer()->description('ID of the project to edit. Omit to create a new project.'),
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

        if (! isset($data['project_id'])) {
            Gate::forUser($user)->authorize('create', Project::class);

            return $this->openView('project_form', 'a form for a new project', 'create_project');
        }

        $project = $this->findProject($user, $data['project_id']);

        Gate::forUser($user)->authorize('update', $project);

        return $this->openView('project_form', "the edit form for project {$project->id}", 'update_project', ['project_id' => $project->id]);
    }
}
