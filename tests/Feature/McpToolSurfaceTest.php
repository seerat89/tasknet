<?php

use App\Mcp\Tools\UpdateProjectTool;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Passport\Passport;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->demo()->create();
    $this->otherUser = User::factory()->demo()->create();

    $this->project = Project::factory()->for($this->user)->create(['name' => 'Launch plan']);
    $this->otherProject = Project::factory()->for($this->otherUser)->create(['name' => 'Secret roadmap']);
});

test('all eight tools are advertised directly and nothing else', function () {
    Passport::actingAs($this->user, ['mcp:use'], 'api');

    $tools = mcpRequest($this, 'tools/list')->assertOk()->json('result.tools.*.name');

    expect($tools)->toBe(['list_projects', 'get_project', 'create_project', 'update_project', 'list_tasks', 'get_task', 'create_task', 'update_task'])
        ->not->toContain('search_tools', 'execute_tools');
});

test('no delete tools are exposed', function () {
    Passport::actingAs($this->user, ['mcp:use'], 'api');

    $tools = mcpRequest($this, 'tools/list')->assertOk()->json('result.tools.*.name');

    expect(collect($tools)->filter(fn (string $name) => str_contains($name, 'delete') || str_contains($name, 'remove')))->toBeEmpty();
});

test('the removed discovery tools cannot be called', function (string $tool) {
    Passport::actingAs($this->user, ['mcp:use'], 'api');

    $response = callMcpToolOverHttp($this, $tool, [])->assertStatus(400);

    expect($response->json('error.message'))->toContain($tool);
})->with(['search_tools', 'execute_tools']);

test('the write tools run directly as the OAuth user', function () {
    Passport::actingAs($this->user, ['mcp:use'], 'api');

    $created = callMcpToolOverHttp($this, 'create_project', ['name' => 'Direct project'])->assertOk();
    expect(mcpResult($created)['isError'])->toBeFalse();

    $project = Project::where('name', 'Direct project')->sole();
    expect($project->user_id)->toBe($this->user->id);

    callMcpToolOverHttp($this, 'create_task', ['project_id' => $project->id, 'title' => 'First task'])->assertOk();
    $task = Task::where('title', 'First task')->sole();

    callMcpToolOverHttp($this, 'update_task', ['project_id' => $project->id, 'task_id' => $task->id, 'status' => 'completed'])->assertOk();
    callMcpToolOverHttp($this, 'update_project', ['project_id' => $project->id, 'name' => 'Renamed'])->assertOk();

    expect($task->fresh()->status->value)->toBe('completed')
        ->and($project->fresh()->name)->toBe('Renamed');
});

test('the write tools cannot touch another workspace', function () {
    Passport::actingAs($this->user, ['mcp:use'], 'api');
    $otherTask = Task::factory()->for($this->otherProject)->create(['title' => 'Secret task']);

    $project = callMcpToolOverHttp($this, 'update_project', ['project_id' => $this->otherProject->id, 'name' => 'Hijacked'])->assertOk();
    $task = callMcpToolOverHttp($this, 'update_task', ['project_id' => $this->otherProject->id, 'task_id' => $otherTask->id, 'title' => 'Hijacked'])->assertOk();
    $create = callMcpToolOverHttp($this, 'create_task', ['project_id' => $this->otherProject->id, 'title' => 'Sneaky'])->assertOk();

    expect(mcpResult($project)['isError'])->toBeTrue()
        ->and(mcpResult($task)['isError'])->toBeTrue()
        ->and(mcpResult($create)['isError'])->toBeTrue()
        ->and($this->otherProject->fresh()->name)->toBe('Secret roadmap')
        ->and($otherTask->fresh()->title)->toBe('Secret task')
        ->and($this->otherProject->tasks()->count())->toBe(1);
});

test('ownership identifiers are ignored', function () {
    Passport::actingAs($this->user, ['mcp:use'], 'api');

    callMcpToolOverHttp($this, 'create_project', [
        'name' => 'Mine',
        'user_id' => $this->otherUser->id,
        'email' => $this->otherUser->email,
    ])->assertOk();

    expect(Project::where('name', 'Mine')->sole()->user_id)->toBe($this->user->id);
});

test('the project limit applies', function () {
    config()->set('tasknet.limits.projects', 1);
    Passport::actingAs($this->user, ['mcp:use'], 'api');

    $response = callMcpToolOverHttp($this, 'create_project', ['name' => 'Over the limit'])->assertOk();

    expect(mcpResult($response)['isError'])->toBeTrue()
        ->and(json_encode(mcpResult($response)))->toContain('You can create up to 1 projects')
        ->and($this->user->projects()->count())->toBe(1);
});

test('the task limit applies', function () {
    config()->set('tasknet.limits.tasks', 1);
    Passport::actingAs($this->user, ['mcp:use'], 'api');

    callMcpToolOverHttp($this, 'create_task', ['project_id' => $this->project->id, 'title' => 'Fits'])->assertOk();
    $response = callMcpToolOverHttp($this, 'create_task', ['project_id' => $this->project->id, 'title' => 'Too many'])->assertOk();

    expect(mcpResult($response)['isError'])->toBeTrue()
        ->and(json_encode(mcpResult($response)))->toContain('You can create up to 1 tasks')
        ->and($this->user->tasks()->count())->toBe(1);
});

test('validation errors are returned to the client', function () {
    Passport::actingAs($this->user, ['mcp:use'], 'api');

    $response = callMcpToolOverHttp($this, 'create_task', ['project_id' => $this->project->id, 'title' => 'x', 'status' => 'archived'])->assertOk();

    expect(mcpResult($response)['isError'])->toBeTrue()
        ->and($this->project->tasks()->count())->toBe(0);
});

test('expired demo users cannot list or call tools over HTTP', function () {
    $expired = User::factory()->demo()->expired()->create();
    Passport::actingAs($expired, ['mcp:use'], 'api');

    mcpRequest($this, 'tools/list')->assertForbidden();
    callMcpToolOverHttp($this, 'create_project', ['name' => 'Too late'])->assertForbidden();

    expect($expired->projects()->count())->toBe(0);
});

test('expired users are also denied when a tool runs in-process', function () {
    $expired = User::factory()->demo()->expired()->create();
    $project = Project::factory()->for($expired)->create(['name' => 'Old name']);

    callMcpTool($expired, UpdateProjectTool::class, ['project_id' => $project->id, 'name' => 'New name'])
        ->assertHasErrors();

    expect($project->fresh()->name)->toBe('Old name');
});

test('unauthenticated clients cannot call tools', function () {
    callMcpToolOverHttp($this, 'create_project', ['name' => 'Anonymous'])->assertUnauthorized();
});
