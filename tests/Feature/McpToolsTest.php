<?php

use App\Mcp\Tools\CreateProjectTool;
use App\Mcp\Tools\CreateTaskTool;
use App\Mcp\Tools\GetProjectTool;
use App\Mcp\Tools\GetTaskTool;
use App\Mcp\Tools\ListProjectsTool;
use App\Mcp\Tools\ListTasksTool;
use App\Mcp\Tools\UpdateProjectTool;
use App\Mcp\Tools\UpdateTaskTool;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->demo()->create();
    $this->otherUser = User::factory()->demo()->create();

    $this->project = Project::factory()->for($this->user)->create(['name' => 'Launch plan']);
    $this->task = Task::factory()->for($this->project)->create(['title' => 'Write the announcement', 'status' => 'todo']);

    $this->otherProject = Project::factory()->for($this->otherUser)->create(['name' => 'Secret roadmap']);
    $this->otherTask = Task::factory()->for($this->otherProject)->create(['title' => 'Secret task']);
});

test('list_projects returns only the authenticated users projects', function () {
    callMcpTool($this->user, ListProjectsTool::class)
        ->assertOk()
        ->assertSee('Launch plan')
        ->assertDontSee('Secret roadmap')
        ->assertStructuredContent(fn ($json) => $json
            ->has('projects', 1)
            ->where('projects.0.id', $this->project->id)
            ->where('projects.0.tasks_count', 1)
            ->etc());
});

test('get_project returns an own project', function () {
    callMcpTool($this->user, GetProjectTool::class, ['project_id' => $this->project->id])
        ->assertOk()
        ->assertSee('Launch plan');
});

test('get_project does not reveal another workspaces project', function () {
    callMcpTool($this->user, GetProjectTool::class, ['project_id' => $this->otherProject->id])
        ->assertHasErrors(['Project not found.'])
        ->assertDontSee('Secret roadmap');
});

test('get_project reports a missing project the same way as a foreign one', function () {
    callMcpTool($this->user, GetProjectTool::class, ['project_id' => 999999])
        ->assertHasErrors(['Project not found.']);
});

test('list_tasks returns only tasks of an own project and supports filters', function () {
    Task::factory()->for($this->project)->create(['title' => 'Already shipped', 'status' => 'completed']);

    callMcpTool($this->user, ListTasksTool::class, ['project_id' => $this->project->id])
        ->assertOk()
        ->assertSee(['Write the announcement', 'Already shipped'])
        ->assertDontSee('Secret task');

    callMcpTool($this->user, ListTasksTool::class, ['project_id' => $this->project->id, 'status' => 'completed'])
        ->assertOk()
        ->assertSee('Already shipped')
        ->assertDontSee('Write the announcement');
});

test('list_tasks rejects a project from another workspace', function () {
    callMcpTool($this->user, ListTasksTool::class, ['project_id' => $this->otherProject->id])
        ->assertHasErrors(['Project not found.'])
        ->assertDontSee('Secret task');
});

test('list_tasks validates the status filter', function () {
    callMcpTool($this->user, ListTasksTool::class, ['project_id' => $this->project->id, 'status' => 'archived'])
        ->assertHasErrors();
});

test('get_task returns an own task', function () {
    callMcpTool($this->user, GetTaskTool::class, ['project_id' => $this->project->id, 'task_id' => $this->task->id])
        ->assertOk()
        ->assertSee('Write the announcement');
});

test('get_task does not reveal another workspaces task', function () {
    callMcpTool($this->user, GetTaskTool::class, ['project_id' => $this->otherProject->id, 'task_id' => $this->otherTask->id])
        ->assertHasErrors(['Project not found.'])
        ->assertDontSee('Secret task');

    callMcpTool($this->user, GetTaskTool::class, ['project_id' => $this->project->id, 'task_id' => $this->otherTask->id])
        ->assertHasErrors(['Task not found.'])
        ->assertDontSee('Secret task');
});

test('create_project creates a project owned by the authenticated user', function () {
    callMcpTool($this->user, CreateProjectTool::class, ['name' => 'From my agent', 'description' => 'Created over MCP'])
        ->assertOk()
        ->assertSee('From my agent');

    $project = Project::where('name', 'From my agent')->sole();

    expect($project->user_id)->toBe($this->user->id)
        ->and($project->description)->toBe('Created over MCP');
});

test('create_project ignores ownership identifiers in the input', function () {
    callMcpTool($this->user, CreateProjectTool::class, [
        'name' => 'Not stolen',
        'user_id' => $this->otherUser->id,
        'demo_id' => $this->otherUser->demo_id,
        'email' => $this->otherUser->email,
    ])->assertOk();

    expect(Project::where('name', 'Not stolen')->sole()->user_id)->toBe($this->user->id)
        ->and($this->otherUser->projects()->count())->toBe(1);
});

test('create_project validates its input', function () {
    callMcpTool($this->user, CreateProjectTool::class, [])->assertHasErrors(['name']);
    callMcpTool($this->user, CreateProjectTool::class, ['name' => str_repeat('a', 121)])->assertHasErrors();

    expect($this->user->projects()->count())->toBe(1);
});

test('create_project enforces the workspace project limit', function () {
    config()->set('tasknet.limits.projects', 2);

    callMcpTool($this->user, CreateProjectTool::class, ['name' => 'Second'])->assertOk();
    callMcpTool($this->user, CreateProjectTool::class, ['name' => 'Third'])
        ->assertHasErrors(['You can create up to 2 projects in a demo workspace.']);

    expect($this->user->projects()->count())->toBe(2);
});

test('update_project changes only the supplied fields', function () {
    $this->project->update(['description' => 'Original description']);

    callMcpTool($this->user, UpdateProjectTool::class, ['project_id' => $this->project->id, 'name' => 'Renamed'])
        ->assertOk();

    expect($this->project->fresh())
        ->name->toBe('Renamed')
        ->description->toBe('Original description');
});

test('update_project can clear the description', function () {
    $this->project->update(['description' => 'Soon gone']);

    callMcpTool($this->user, UpdateProjectTool::class, ['project_id' => $this->project->id, 'description' => null])
        ->assertOk();

    expect($this->project->fresh()->description)->toBeNull();
});

test('update_project rejects an empty name', function () {
    callMcpTool($this->user, UpdateProjectTool::class, ['project_id' => $this->project->id, 'name' => ''])
        ->assertHasErrors();

    expect($this->project->fresh()->name)->toBe('Launch plan');
});

test('update_project cannot modify another workspaces project', function () {
    callMcpTool($this->user, UpdateProjectTool::class, ['project_id' => $this->otherProject->id, 'name' => 'Hijacked'])
        ->assertHasErrors(['Project not found.']);

    expect($this->otherProject->fresh()->name)->toBe('Secret roadmap');
});

test('update_project cannot change the owner', function () {
    callMcpTool($this->user, UpdateProjectTool::class, [
        'project_id' => $this->project->id,
        'name' => 'Still mine',
        'user_id' => $this->otherUser->id,
    ])->assertOk();

    expect($this->project->fresh()->user_id)->toBe($this->user->id);
});

test('create_task creates a task with defaults in an own project', function () {
    callMcpTool($this->user, CreateTaskTool::class, ['project_id' => $this->project->id, 'title' => 'Draft the post'])
        ->assertOk()
        ->assertSee('Draft the post');

    $task = Task::where('title', 'Draft the post')->sole();

    expect($task->status->value)->toBe('todo')
        ->and($task->priority->value)->toBe('medium')
        ->and($task->project_id)->toBe($this->project->id);
});

test('create_task accepts every task field', function () {
    callMcpTool($this->user, CreateTaskTool::class, [
        'project_id' => $this->project->id,
        'title' => 'Ship it',
        'description' => 'Final step',
        'status' => 'in_progress',
        'priority' => 'high',
        'due_date' => '2026-12-24',
    ])->assertOk();

    $task = Task::where('title', 'Ship it')->sole();

    expect($task->status->value)->toBe('in_progress')
        ->and($task->priority->value)->toBe('high')
        ->and($task->due_date->toDateString())->toBe('2026-12-24');
});

test('create_task cannot add a task to another workspaces project', function () {
    callMcpTool($this->user, CreateTaskTool::class, ['project_id' => $this->otherProject->id, 'title' => 'Sneaky'])
        ->assertHasErrors(['Project not found.']);

    expect(Task::where('title', 'Sneaky')->exists())->toBeFalse();
});

test('create_task validates its input', function () {
    $arguments = ['project_id' => $this->project->id];

    callMcpTool($this->user, CreateTaskTool::class, $arguments)->assertHasErrors(['title']);
    callMcpTool($this->user, CreateTaskTool::class, [...$arguments, 'title' => 'x', 'status' => 'archived'])->assertHasErrors();
    callMcpTool($this->user, CreateTaskTool::class, [...$arguments, 'title' => 'x', 'priority' => 'urgent'])->assertHasErrors();
    callMcpTool($this->user, CreateTaskTool::class, [...$arguments, 'title' => 'x', 'due_date' => 'tomorrow-ish'])->assertHasErrors();

    expect($this->project->tasks()->count())->toBe(1);
});

test('create_task enforces the workspace task limit across projects', function () {
    config()->set('tasknet.limits.tasks', 2);
    $secondProject = Project::factory()->for($this->user)->create();

    callMcpTool($this->user, CreateTaskTool::class, ['project_id' => $secondProject->id, 'title' => 'Second'])->assertOk();
    callMcpTool($this->user, CreateTaskTool::class, ['project_id' => $this->project->id, 'title' => 'Third'])
        ->assertHasErrors(['You can create up to 2 tasks in a demo workspace.']);

    expect($this->user->tasks()->count())->toBe(2);
});

test('update_task changes only the supplied fields', function () {
    callMcpTool($this->user, UpdateTaskTool::class, [
        'project_id' => $this->project->id,
        'task_id' => $this->task->id,
        'status' => 'completed',
    ])->assertOk();

    expect($this->task->fresh())
        ->status->value->toBe('completed')
        ->title->toBe('Write the announcement');
});

test('update_task rejects an invalid status and an empty title', function () {
    $arguments = ['project_id' => $this->project->id, 'task_id' => $this->task->id];

    callMcpTool($this->user, UpdateTaskTool::class, [...$arguments, 'status' => 'archived'])->assertHasErrors();
    callMcpTool($this->user, UpdateTaskTool::class, [...$arguments, 'title' => ''])->assertHasErrors();

    expect($this->task->fresh()->title)->toBe('Write the announcement');
});

test('update_task cannot modify another workspaces task', function () {
    callMcpTool($this->user, UpdateTaskTool::class, [
        'project_id' => $this->otherProject->id,
        'task_id' => $this->otherTask->id,
        'title' => 'Hijacked',
    ])->assertHasErrors(['Project not found.']);

    callMcpTool($this->user, UpdateTaskTool::class, [
        'project_id' => $this->project->id,
        'task_id' => $this->otherTask->id,
        'title' => 'Hijacked',
    ])->assertHasErrors(['Task not found.']);

    expect($this->otherTask->fresh()->title)->toBe('Secret task');
});

test('update_task cannot move a task to another project', function () {
    callMcpTool($this->user, UpdateTaskTool::class, [
        'project_id' => $this->project->id,
        'task_id' => $this->task->id,
        'title' => 'Stays put',
        'move_to_project_id' => $this->otherProject->id,
    ])->assertOk();

    expect($this->task->fresh()->project_id)->toBe($this->project->id);
});

test('expired demo workspaces are denied by every tool', function (string $tool, array $arguments) {
    $expired = User::factory()->demo()->expired()->create();
    $project = Project::factory()->for($expired)->create();
    $task = Task::factory()->for($project)->create();

    $arguments = array_map(fn ($value) => match ($value) {
        'PROJECT' => $project->id,
        'TASK' => $task->id,
        default => $value,
    }, $arguments);

    callMcpTool($expired, $tool, $arguments)->assertHasErrors();

    expect($expired->projects()->count())->toBe(1)
        ->and($expired->tasks()->count())->toBe(1)
        ->and($project->fresh()->name)->toBe($project->name)
        ->and($task->fresh()->title)->toBe($task->title);
})->with([
    'list_projects' => [ListProjectsTool::class, []],
    'get_project' => [GetProjectTool::class, ['project_id' => 'PROJECT']],
    'create_project' => [CreateProjectTool::class, ['name' => 'Too late']],
    'update_project' => [UpdateProjectTool::class, ['project_id' => 'PROJECT', 'name' => 'Too late']],
    'list_tasks' => [ListTasksTool::class, ['project_id' => 'PROJECT']],
    'get_task' => [GetTaskTool::class, ['project_id' => 'PROJECT', 'task_id' => 'TASK']],
    'create_task' => [CreateTaskTool::class, ['project_id' => 'PROJECT', 'title' => 'Too late']],
    'update_task' => [UpdateTaskTool::class, ['project_id' => 'PROJECT', 'task_id' => 'TASK', 'title' => 'Too late']],
]);

test('read tools are marked read-only and write tools are not', function () {
    foreach ([ListProjectsTool::class, GetProjectTool::class, ListTasksTool::class, GetTaskTool::class] as $tool) {
        expect((new $tool)->toArray()['annotations'])->toMatchArray(['readOnlyHint' => true]);
    }

    foreach ([CreateProjectTool::class, UpdateProjectTool::class, CreateTaskTool::class, UpdateTaskTool::class] as $tool) {
        expect((new $tool)->toArray()['annotations'])->not->toHaveKey('readOnlyHint');
    }
});

test('tool input schemas never accept ownership identifiers', function () {
    foreach ([ListProjectsTool::class, GetProjectTool::class, CreateProjectTool::class, UpdateProjectTool::class, ListTasksTool::class, GetTaskTool::class, CreateTaskTool::class, UpdateTaskTool::class] as $tool) {
        expect(array_keys((array) (new $tool)->toArray()['inputSchema']['properties']))
            ->not->toContain('user_id')
            ->not->toContain('demo_id')
            ->not->toContain('email');
    }
});
