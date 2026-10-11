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

/**
 * Run search_tools over HTTP and decode the catalog result.
 *
 * @return array{ok: bool, tools: array<int, array<string, mixed>>, hasMore: bool}
 */
function searchCatalog(mixed $test, string $query = ''): array
{
    $response = mcpRequest($test, 'tools/call', [
        'name' => 'search_tools',
        'arguments' => ['query' => $query],
    ])->assertOk();

    return json_decode($response->json('result.content.0.text'), true);
}

test('the read tools are advertised directly and the catalog tools are not', function () {
    Passport::actingAs($this->user, ['mcp:use'], 'api');

    $tools = mcpRequest($this, 'tools/list')->assertOk()->json('result.tools.*.name');

    expect($tools)->toBe(['list_projects', 'get_project', 'list_tasks', 'get_task', 'search_tools', 'execute_tools'])
        ->not->toContain('create_project', 'update_project', 'create_task', 'update_task');
});

test('catalog tools cannot be called directly', function () {
    Passport::actingAs($this->user, ['mcp:use'], 'api');

    $response = mcpRequest($this, 'tools/call', ['name' => 'create_project', 'arguments' => ['name' => 'Sneaky']])->assertStatus(400);

    expect($response->json('error.message'))->toContain('create_project')
        ->and($this->user->projects()->count())->toBe(1);
});

test('search_tools discovers every cataloged tool with its schema', function (string $tool, string $requiredField) {
    Passport::actingAs($this->user, ['mcp:use'], 'api');

    $result = searchCatalog($this, $tool);
    $found = collect($result['tools'])->firstWhere('name', $tool);

    expect($result['ok'])->toBeTrue()
        ->and($found)->not->toBeNull()
        ->and($found['inputSchema']['required'])->toContain($requiredField)
        ->and($found['description'])->not->toBeEmpty();
})->with([
    'create_project' => ['create_project', 'name'],
    'update_project' => ['update_project', 'project_id'],
    'create_task' => ['create_task', 'title'],
    'update_task' => ['update_task', 'task_id'],
]);

test('browsing the catalog lists exactly the four mutation tools', function () {
    Passport::actingAs($this->user, ['mcp:use'], 'api');

    $names = collect(searchCatalog($this)['tools'])->pluck('name')->all();

    expect($names)->toEqualCanonicalizing(['create_project', 'update_project', 'create_task', 'update_task']);
});

test('execute_tools runs cataloged tools as the OAuth user', function () {
    Passport::actingAs($this->user, ['mcp:use'], 'api');

    $created = executeCatalogTool($this, 'create_project', ['name' => 'Via the catalog'])->assertOk();

    expect(mcpResult($created)['isError'])->toBeFalse();

    $project = Project::where('name', 'Via the catalog')->sole();
    expect($project->user_id)->toBe($this->user->id);

    executeCatalogTool($this, 'create_task', ['project_id' => $project->id, 'title' => 'First task'])->assertOk();
    $task = Task::where('title', 'First task')->sole();

    executeCatalogTool($this, 'update_task', ['project_id' => $project->id, 'task_id' => $task->id, 'status' => 'completed'])->assertOk();
    executeCatalogTool($this, 'update_project', ['project_id' => $project->id, 'name' => 'Renamed'])->assertOk();

    expect($task->fresh()->status->value)->toBe('completed')
        ->and($project->fresh()->name)->toBe('Renamed');
});

test('execute_tools can run several calls in order', function () {
    Passport::actingAs($this->user, ['mcp:use'], 'api');

    mcpRequest($this, 'tools/call', [
        'name' => 'execute_tools',
        'arguments' => ['calls' => [
            ['name' => 'update_project', 'arguments' => ['project_id' => $this->project->id, 'name' => 'First']],
            ['name' => 'create_task', 'arguments' => ['project_id' => $this->project->id, 'title' => 'Second']],
        ]],
    ])->assertOk();

    expect($this->project->fresh()->name)->toBe('First')
        ->and($this->project->tasks()->where('title', 'Second')->exists())->toBeTrue();
});

test('catalog execution cannot touch another workspace', function () {
    Passport::actingAs($this->user, ['mcp:use'], 'api');
    $otherTask = Task::factory()->for($this->otherProject)->create(['title' => 'Secret task']);

    $project = executeCatalogTool($this, 'update_project', ['project_id' => $this->otherProject->id, 'name' => 'Hijacked'])->assertOk();
    $task = executeCatalogTool($this, 'update_task', ['project_id' => $this->otherProject->id, 'task_id' => $otherTask->id, 'title' => 'Hijacked'])->assertOk();
    $create = executeCatalogTool($this, 'create_task', ['project_id' => $this->otherProject->id, 'title' => 'Sneaky'])->assertOk();

    expect(mcpResult($project)['isError'])->toBeTrue()
        ->and(mcpResult($task)['isError'])->toBeTrue()
        ->and(mcpResult($create)['isError'])->toBeTrue()
        ->and($this->otherProject->fresh()->name)->toBe('Secret roadmap')
        ->and($otherTask->fresh()->title)->toBe('Secret task')
        ->and($this->otherProject->tasks()->count())->toBe(1);
});

test('catalog execution ignores ownership identifiers', function () {
    Passport::actingAs($this->user, ['mcp:use'], 'api');

    executeCatalogTool($this, 'create_project', [
        'name' => 'Mine',
        'user_id' => $this->otherUser->id,
        'email' => $this->otherUser->email,
    ])->assertOk();

    expect(Project::where('name', 'Mine')->sole()->user_id)->toBe($this->user->id);
});

test('the project limit applies to catalog execution', function () {
    config()->set('tasknet.limits.projects', 1);
    Passport::actingAs($this->user, ['mcp:use'], 'api');

    $response = executeCatalogTool($this, 'create_project', ['name' => 'Over the limit'])->assertOk();

    expect(mcpResult($response)['isError'])->toBeTrue()
        ->and(json_encode(mcpResult($response)))->toContain('You can create up to 1 projects')
        ->and($this->user->projects()->count())->toBe(1);
});

test('the task limit applies to catalog execution', function () {
    config()->set('tasknet.limits.tasks', 1);
    Passport::actingAs($this->user, ['mcp:use'], 'api');

    executeCatalogTool($this, 'create_task', ['project_id' => $this->project->id, 'title' => 'Fits'])->assertOk();
    $response = executeCatalogTool($this, 'create_task', ['project_id' => $this->project->id, 'title' => 'Too many'])->assertOk();

    expect(mcpResult($response)['isError'])->toBeTrue()
        ->and(json_encode(mcpResult($response)))->toContain('You can create up to 1 tasks')
        ->and($this->user->tasks()->count())->toBe(1);
});

test('validation errors surface through the catalog', function () {
    Passport::actingAs($this->user, ['mcp:use'], 'api');

    $response = executeCatalogTool($this, 'create_task', ['project_id' => $this->project->id, 'title' => 'x', 'status' => 'archived'])->assertOk();

    expect(mcpResult($response)['isError'])->toBeTrue()
        ->and($this->project->tasks()->count())->toBe(0);
});

test('expired demo users cannot search or execute catalog tools', function () {
    $expired = User::factory()->demo()->expired()->create();
    Passport::actingAs($expired, ['mcp:use'], 'api');

    mcpRequest($this, 'tools/call', ['name' => 'search_tools', 'arguments' => (object) []])->assertForbidden();
    executeCatalogTool($this, 'create_project', ['name' => 'Too late'])->assertForbidden();

    expect($expired->projects()->count())->toBe(0);
});

test('expired users are also denied when the catalog runs in-process', function () {
    $expired = User::factory()->demo()->expired()->create();
    $project = Project::factory()->for($expired)->create(['name' => 'Old name']);

    callMcpTool($expired, UpdateProjectTool::class, ['project_id' => $project->id, 'name' => 'New name'])
        ->assertHasErrors();

    expect($project->fresh()->name)->toBe('Old name');
});

test('unauthenticated clients cannot search the catalog', function () {
    mcpRequest($this, 'tools/call', ['name' => 'search_tools', 'arguments' => (object) []])->assertUnauthorized();
});
