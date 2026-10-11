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
use Laravel\Mcp\Server\Tools\ToolSearch;

#[Name('TaskNet')]
#[Version('0.1.0')]
#[Instructions('TaskNet is a small project and task manager. Use these tools to read the connected user\'s own demo workspace: list_projects, get_project, list_tasks and get_task. Creating and updating projects and tasks (create_project, update_project, create_task, update_task) is available through the tool catalog: call search_tools to get the exact tool names and input schemas, then call execute_tools. A workspace holds at most 10 projects and 100 tasks. Tasks belong to a project, so find or create a project first. Task status is one of todo, in_progress or completed; priority is low, medium or high. Never put real or sensitive data in a demo workspace.')]
class TaskNetServer extends Server
{
    /**
     * The tools registered with this server.
     *
     * Frequently used read tools are advertised directly. The mutation tools live in a
     * searchable catalog (progressive tool disclosure), reached through the search_tools
     * and execute_tools tools that ToolSearch adds.
     *
     * @var array<int|string, class-string<Server\Tool>|array<int, class-string<Server\Tool>>>
     */
    protected array $tools = [
        ListProjectsTool::class,
        GetProjectTool::class,
        ListTasksTool::class,
        GetTaskTool::class,
        ToolSearch::class => [
            CreateProjectTool::class,
            UpdateProjectTool::class,
            CreateTaskTool::class,
            UpdateTaskTool::class,
        ],
    ];
}
