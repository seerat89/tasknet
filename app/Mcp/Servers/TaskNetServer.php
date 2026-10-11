<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\CreateProjectTool;
use App\Mcp\Tools\CreateTaskTool;
use App\Mcp\Tools\GetProjectTool;
use App\Mcp\Tools\GetTaskTool;
use App\Mcp\Tools\ListProjectsTool;
use App\Mcp\Tools\ListTasksTool;
use App\Mcp\Tools\UpdateProjectTool;
use App\Mcp\Tools\UpdateTaskTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('TaskNet')]
#[Version('0.1.0')]
#[Instructions('TaskNet is a small project and task manager. Use these tools to list, read, create and update projects and tasks in the connected user\'s own demo workspace. A workspace holds at most 10 projects and 100 tasks. Tasks belong to a project, so find or create a project first. Task status is one of todo, in_progress or completed; priority is low, medium or high. Never put real or sensitive data in a demo workspace.')]
class TaskNetServer extends Server
{
    /**
     * The tools registered with this server.
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
    ];
}
