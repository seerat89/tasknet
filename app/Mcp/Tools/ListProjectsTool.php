<?php

namespace App\Mcp\Tools;

use App\Http\Resources\ProjectResource;
use App\Mcp\Concerns\InteractsWithWorkspace;
use App\Models\Project;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
class ListProjectsTool extends Tool
{
    use InteractsWithWorkspace;

    protected string $name = 'list_projects';

    protected string $title = 'List projects';

    protected string $description = 'List all projects in your TaskNet workspace, each with its task count.';

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory
    {
        $user = $this->workspaceUser($request);

        Gate::forUser($user)->authorize('viewAny', Project::class);

        $projects = $user->projects()->withCount('tasks')->orderBy('id')->get();

        return Response::structured([
            'projects' => ProjectResource::collection($projects)->resolve(),
        ]);
    }
}
