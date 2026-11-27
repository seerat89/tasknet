<?php

namespace App\Mcp\Servers;

use App\Mcp\Concerns\RecordsActivity;
use App\Mcp\Resources\TaskNetApp;
use App\Mcp\Resources\TaskNetAppAnyBuild;
use App\Mcp\Tools\CreateProjectTool;
use App\Mcp\Tools\CreateTaskTool;
use App\Mcp\Tools\GetProjectTool;
use App\Mcp\Tools\GetTaskTool;
use App\Mcp\Tools\ListProjectsTool;
use App\Mcp\Tools\ListTasksTool;
use App\Mcp\Tools\ShowProjectFormTool;
use App\Mcp\Tools\ShowProjectsTool;
use App\Mcp\Tools\ShowTaskFormTool;
use App\Mcp\Tools\ShowTasksTool;
use App\Mcp\Tools\UpdateProjectTool;
use App\Mcp\Tools\UpdateTaskTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('TaskNet')]
#[Version('0.1.0')]
#[Instructions('TaskNet is a small project and task manager. Use these tools to read and manage the connected user\'s own demo workspace: list_projects, get_project, create_project, update_project, list_tasks, get_task, create_task and update_task. A workspace holds at most 10 projects and 100 tasks. Tasks belong to a project, so find or create a project first. Task status is one of todo, in_progress or completed; priority is low, medium or high. Never put real or sensitive data in a demo workspace. Use those eight tools to answer questions and to make changes: when the user has already given the details, create or update directly. Use show_projects, show_tasks, show_project_form or show_task_form only when the user wants to browse visually or fill in a form; they open an interactive view and change nothing.')]
class TaskNetServer extends Server
{
    use RecordsActivity;

    /**
     * The tools registered with this server. There are deliberately no delete tools.
     *
     * @var array<int, class-string<Server\Tool>>
     */
    protected array $tools = [
        ListProjectsTool::class,
        GetProjectTool::class,
        CreateProjectTool::class,
        UpdateProjectTool::class,
        ListTasksTool::class,
        GetTaskTool::class,
        CreateTaskTool::class,
        UpdateTaskTool::class,
        ShowProjectsTool::class,
        ShowTasksTool::class,
        ShowProjectFormTool::class,
        ShowTaskFormTool::class,
    ];

    /**
     * The interactive view opened by the show_* tools, and its fallback for other builds.
     *
     * @var array<int, class-string<Server\Resource>>
     */
    protected array $resources = [
        TaskNetApp::class,
        TaskNetAppAnyBuild::class,
    ];
}
