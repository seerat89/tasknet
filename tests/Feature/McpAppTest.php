<?php

use App\Actions\CreateProject;
use App\Mcp\Resources\TaskNetApp;
use App\Mcp\Tools\CreateProjectTool;
use App\Mcp\Tools\CreateTaskTool;
use App\Mcp\Tools\ShowProjectFormTool;
use App\Mcp\Tools\ShowProjectsTool;
use App\Mcp\Tools\ShowTaskFormTool;
use App\Mcp\Tools\ShowTasksTool;
use App\Mcp\Tools\UpdateProjectTool;
use App\Mcp\Tools\UpdateTaskTool;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Laravel\Passport\Passport;

uses(LazilyRefreshDatabase::class);

/**
 * The address of the page that was built for this run.
 */
function appUri(): string
{
    return 'ui://tasknet/app-'.substr((string) hash_file('sha256', resource_path('dist/mcp-app/app.html')), 0, 12).'.html';
}

const VIEW_TOOLS = ['show_projects', 'show_tasks', 'show_project_form', 'show_task_form'];

beforeEach(function () {
    $this->user = User::factory()->demo()->create();
    $this->otherUser = User::factory()->demo()->create();

    $this->project = Project::factory()->for($this->user)->create(['name' => 'Launch plan']);
    $this->task = Task::factory()->for($this->project)->create(['title' => 'Write the announcement', 'due_date' => '2026-01-15']);

    $this->otherProject = Project::factory()->for($this->otherUser)->create(['name' => 'Secret roadmap']);
    $this->otherTask = Task::factory()->for($this->otherProject)->create(['title' => 'Secret task']);
});

/**
 * Every row a view tool could conceivably change.
 */
function workspaceSnapshot(): array
{
    return [
        Project::query()->orderBy('id')->get()->toArray(),
        Task::query()->orderBy('id')->get()->toArray(),
    ];
}

// --- Resource registration and metadata -------------------------------------

test('the server advertises the MCP Apps extension', function () {
    Passport::actingAs($this->user, ['mcp:use'], 'api');

    $capabilities = mcpRequest($this, 'initialize', ['protocolVersion' => '2025-11-25', 'capabilities' => (object) [], 'clientInfo' => ['name' => 'test', 'version' => '1']])
        ->assertOk()
        ->json('result.capabilities');

    expect($capabilities['extensions'])->toHaveKey('io.modelcontextprotocol/ui');
});

test('the app resource is listed with the MCP Apps MIME type', function () {
    Passport::actingAs($this->user, ['mcp:use'], 'api');

    $resources = mcpRequest($this, 'resources/list')->assertOk()->json('result.resources');

    expect($resources)->toHaveCount(1)
        ->and($resources[0]['uri'])->toBe(appUri())
        ->and($resources[0]['mimeType'])->toBe('text/html;profile=mcp-app');
});

test('the app resource requests no extra domains, permissions or host-specific origin', function () {
    Passport::actingAs($this->user, ['mcp:use'], 'api');

    $listed = mcpRequest($this, 'resources/list')->json('result.resources.0._meta.ui');
    $read = mcpRequest($this, 'resources/read', ['uri' => appUri()])->assertOk()->json('result.contents.0._meta.ui');

    foreach ([$listed, $read] as $ui) {
        expect($ui)->toBe(['prefersBorder' => true]);
    }
});

test('the app resource is one self-contained page', function () {
    Passport::actingAs($this->user, ['mcp:use'], 'api');

    $content = mcpRequest($this, 'resources/read', ['uri' => appUri()])->assertOk()->json('result.contents.0');

    expect($content['mimeType'])->toBe('text/html;profile=mcp-app')
        ->and($content['uri'])->toBe(appUri())
        ->and($content['text'])->toContain('<main id="app">', '<script type="module"', '<style')
        // Nothing may be fetched from anywhere: no external or sibling scripts, styles or images.
        ->not->toMatch('/<script[^>]+\ssrc=/i')
        ->not->toMatch('/<link[^>]+\shref=/i')
        ->not->toMatch('/@import\s+url\(/i')
        ->not->toMatch('/url\(\s*["\']?https?:/i');
});

test('the app page holds no workspace data or credentials', function () {
    Passport::actingAs($this->user, ['mcp:use'], 'api');

    $html = mcpRequest($this, 'resources/read', ['uri' => appUri()])->json('result.contents.0.text');

    expect($html)->not->toContain('Launch plan', 'Secret roadmap', $this->user->demo_id, $this->user->email, config('app.key'));
});

test('the app resource requires authentication', function () {
    mcpRequest($this, 'resources/read', ['uri' => appUri()])->assertUnauthorized();
    mcpRequest($this, 'resources/list')->assertUnauthorized();
});

test('an expired workspace cannot read the app resource', function () {
    Passport::actingAs(User::factory()->demo()->expired()->create(), ['mcp:use'], 'api');

    mcpRequest($this, 'resources/read', ['uri' => appUri()])->assertForbidden();
});

// --- Build output and versioning ---------------------------------------------

test('the page is built outside the web root', function () {
    expect(resource_path('dist/mcp-app/app.html'))->toBeFile()
        ->and(public_path('build/mcp/app.html'))->not->toBeFile();
});

test('the resource URI is derived from the built page', function () {
    expect(app(TaskNetApp::class)->uri())->toBe(appUri())->toMatch('#^ui://tasknet/app-[0-9a-f]{12}\.html$#');
});

test('a different build gets a different URI', function () {
    $other = tempnam(sys_get_temp_dir(), 'app');
    file_put_contents($other, '<main id="app">changed</main>');

    $app = new class($other) extends TaskNetApp
    {
        public function __construct(private readonly string $page) {}

        protected function path(): string
        {
            return $this->page;
        }
    };

    try {
        $first = $app->uri();
        file_put_contents($other, '<main id="app">changed again</main>');

        expect($first)->not->toBe(appUri())
            ->and($app->uri())->not->toBe($first);

        unlink($other);

        expect($app->uri())->toBe('ui://tasknet/app-unbuilt.html');
    } finally {
        if (is_file($other)) {
            unlink($other);
        }
    }
});

test('the address of another build serves the current page with the same restrictions', function (string $uri) {
    Passport::actingAs($this->user, ['mcp:use'], 'api');

    $current = mcpRequest($this, 'resources/read', ['uri' => appUri()])->assertOk()->json('result.contents.0');
    $other = mcpRequest($this, 'resources/read', ['uri' => $uri])->assertOk()->json('result.contents.0');

    expect($other['uri'])->toBe($uri)
        ->and($other['text'])->toBe($current['text'])
        ->and($other['mimeType'])->toBe('text/html;profile=mcp-app')
        ->and($other['_meta'])->toBe($current['_meta']);
})->with([
    'the address connected clients may still hold' => 'ui://tasknet/app-v1.html',
    'an earlier build' => 'ui://tasknet/app-0123456789ab.html',
]);

test('only app page addresses are answered', function (string $uri) {
    Passport::actingAs($this->user, ['mcp:use'], 'api');

    $response = mcpRequest($this, 'resources/read', ['uri' => $uri]);

    expect($response->json('error.message'))->toContain('not found')
        ->and($response->json('result'))->toBeNull();
})->with([
    'ui://tasknet/other.html',
    'ui://tasknet/app-1/../../.env',
    'ui://tasknet/app-1/2.html',
    'ui://other/app-v1.html',
    'file://resources/dist/mcp-app/app.html',
]);

test('the fallback is a template that no tool points to', function () {
    Passport::actingAs($this->user, ['mcp:use'], 'api');

    $templates = mcpRequest($this, 'resources/templates/list')->assertOk()->json('result.resourceTemplates');
    $linked = collect(mcpRequest($this, 'tools/list')->json('result.tools'))->pluck('_meta.ui.resourceUri')->filter()->unique()->values();

    expect($templates)->toHaveCount(1)
        ->and($templates[0]['uriTemplate'])->toBe('ui://tasknet/app-{build}.html')
        ->and($templates[0]['_meta']['ui'])->toBe(['prefersBorder' => true])
        ->and($linked->all())->toBe([appUri()]);
});

test('the fallback requires authentication too', function () {
    mcpRequest($this, 'resources/read', ['uri' => 'ui://tasknet/app-v1.html'])->assertUnauthorized();
});

// --- Tool metadata and backward compatibility -------------------------------

test('only the view tools are linked to the app and they are read only', function () {
    Passport::actingAs($this->user, ['mcp:use'], 'api');

    $tools = collect(mcpRequest($this, 'tools/list')->assertOk()->json('result.tools'))->keyBy('name');

    foreach (VIEW_TOOLS as $name) {
        expect($tools[$name]['_meta']['ui'])->toBe(['resourceUri' => appUri(), 'visibility' => ['model', 'app']])
            ->and($tools[$name]['annotations']['readOnlyHint'])->toBeTrue()
            ->and($tools[$name]['title'])->not->toBeEmpty();
    }

    // The data tools are unchanged for existing clients, and stay callable from the view by default.
    foreach ($tools->except(VIEW_TOOLS) as $tool) {
        expect($tool)->not->toHaveKey('_meta');
    }

    expect($tools->except(VIEW_TOOLS))->toHaveCount(8);
});

test('the data tool input schemas are unchanged', function (string $name, array $properties, array $required) {
    Passport::actingAs($this->user, ['mcp:use'], 'api');

    $schema = collect(mcpRequest($this, 'tools/list')->json('result.tools'))->firstWhere('name', $name)['inputSchema'];

    expect(array_keys($schema['properties']))->toBe($properties)
        ->and($schema['required'] ?? [])->toBe($required);
})->with([
    ['list_projects', [], []],
    ['get_project', ['project_id'], ['project_id']],
    ['create_project', ['name', 'description'], ['name']],
    ['update_project', ['project_id', 'name', 'description'], ['project_id']],
    ['list_tasks', ['project_id', 'status', 'priority'], ['project_id']],
    ['get_task', ['project_id', 'task_id'], ['project_id', 'task_id']],
    ['create_task', ['project_id', 'title', 'description', 'status', 'priority', 'due_date'], ['project_id', 'title']],
    ['update_task', ['project_id', 'task_id', 'title', 'description', 'status', 'priority', 'due_date'], ['project_id', 'task_id']],
]);

test('data tools still return JSON text for text-only clients, matching the structured content', function () {
    Passport::actingAs($this->user, ['mcp:use'], 'api');

    $result = mcpResult(callMcpToolOverHttp($this, 'list_tasks', ['project_id' => $this->project->id])->assertOk());

    expect(json_decode($result['content'][0]['text'], true))->toBe($result['structuredContent'])
        ->and($result['structuredContent']['project_id'])->toBe($this->project->id);
});

// The widget parses exactly these fields (resources/mcp-app/types.ts).
test('project and task responses have the shape the view reads', function () {
    Passport::actingAs($this->user, ['mcp:use'], 'api');

    $project = mcpResult(callMcpToolOverHttp($this, 'get_project', ['project_id' => $this->project->id]))['structuredContent']['project'];
    $task = mcpResult(callMcpToolOverHttp($this, 'get_task', ['project_id' => $this->project->id, 'task_id' => $this->task->id]))['structuredContent']['task'];

    expect($project)->toHaveKeys(['id', 'name', 'description', 'tasks_count', 'created_at'])
        ->and($task)->toHaveKeys(['id', 'project_id', 'title', 'description', 'status', 'priority', 'due_date', 'created_at'])
        ->and($task['due_date'])->toBe('2026-01-15');
});

// --- View tools --------------------------------------------------------------

dataset('view openings', [
    'project list' => ['show_projects', [], ['view' => 'projects']],
    'one project' => ['show_projects', ['project_id' => 'own'], ['view' => 'project', 'project_id' => 'own']],
    'task list' => ['show_tasks', ['project_id' => 'own'], ['view' => 'tasks', 'project_id' => 'own']],
    'one task' => ['show_tasks', ['project_id' => 'own', 'task_id' => 'task'], ['view' => 'task', 'project_id' => 'own', 'task_id' => 'task']],
    'new project form' => ['show_project_form', [], ['view' => 'project_form']],
    'edit project form' => ['show_project_form', ['project_id' => 'own'], ['view' => 'project_form', 'project_id' => 'own']],
    'new task form' => ['show_task_form', [], ['view' => 'task_form']],
    'new task form in a project' => ['show_task_form', ['project_id' => 'own'], ['view' => 'task_form', 'project_id' => 'own']],
    'edit task form' => ['show_task_form', ['project_id' => 'own', 'task_id' => 'task'], ['view' => 'task_form', 'project_id' => 'own', 'task_id' => 'task']],
]);

test('opening a view returns only a starting point and changes nothing', function (string $tool, array $arguments, array $expected) {
    Passport::actingAs($this->user, ['mcp:use'], 'api');
    $ids = ['own' => $this->project->id, 'task' => $this->task->id];
    $resolve = fn (array $values) => collect($values)->map(fn (string $value, string $key) => $key === 'view' ? $value : $ids[$value])->all();
    $before = workspaceSnapshot();

    $result = mcpResult(callMcpToolOverHttp($this, $tool, $resolve($arguments))->assertOk());

    expect($result['isError'])->toBeFalse()
        ->and($result['structuredContent'])->toBe($resolve($expected))
        ->and($result['content'][0]['text'])->toContain('Nothing was changed', 'cannot show interactive views')
        ->and(json_encode($result))->not->toContain('Launch plan')
        ->and(workspaceSnapshot())->toBe($before);
})->with('view openings');

test('view tools do not open another workspace', function (string $tool, string $case, string $message) {
    $arguments = match ($case) {
        'foreign project' => ['project_id' => $this->otherProject->id],
        'foreign task in a foreign project' => ['project_id' => $this->otherProject->id, 'task_id' => $this->otherTask->id],
        'foreign task in an own project' => ['project_id' => $this->project->id, 'task_id' => $this->otherTask->id],
    };
    $class = [
        'show_projects' => ShowProjectsTool::class,
        'show_tasks' => ShowTasksTool::class,
        'show_project_form' => ShowProjectFormTool::class,
        'show_task_form' => ShowTaskFormTool::class,
    ][$tool];

    callMcpTool($this->user, $class, $arguments)
        ->assertHasErrors([$message])
        ->assertDontSee(['Secret roadmap', 'Secret task']);
})->with([
    ['show_projects', 'foreign project', 'Project not found.'],
    ['show_project_form', 'foreign project', 'Project not found.'],
    ['show_tasks', 'foreign project', 'Project not found.'],
    ['show_tasks', 'foreign task in a foreign project', 'Project not found.'],
    ['show_tasks', 'foreign task in an own project', 'Task not found.'],
    ['show_task_form', 'foreign project', 'Project not found.'],
    ['show_task_form', 'foreign task in a foreign project', 'Project not found.'],
    ['show_task_form', 'foreign task in an own project', 'Task not found.'],
]);

test('a task cannot be opened without its project', function () {
    callMcpTool($this->user, ShowTaskFormTool::class, ['task_id' => $this->task->id])->assertHasErrors();
    callMcpTool($this->user, ShowTasksTool::class, ['task_id' => $this->task->id])->assertHasErrors();
});

test('view tools are denied to expired workspaces', function (string $class) {
    callMcpTool(User::factory()->demo()->expired()->create(), $class)->assertHasErrors();
})->with([ShowProjectsTool::class, ShowProjectFormTool::class, ShowTaskFormTool::class]);

test('view tools require authentication', function (string $tool) {
    callMcpToolOverHttp($this, $tool)->assertUnauthorized();
})->with(VIEW_TOOLS);

test('a blank optional ID is treated as omitted', function (string $tool, array $arguments, string $view) {
    Passport::actingAs($this->user, ['mcp:use'], 'api');
    $arguments = array_map(fn (string $value) => $value === 'own' ? $this->project->id : $value, $arguments);

    $result = mcpResult(callMcpToolOverHttp($this, $tool, $arguments)->assertOk());

    expect($result['isError'])->toBeFalse()
        ->and($result['structuredContent']['view'])->toBe($view);
})->with([
    ['show_projects', ['project_id' => ''], 'projects'],
    ['show_tasks', ['project_id' => 'own', 'task_id' => ''], 'tasks'],
    ['show_project_form', ['project_id' => ''], 'project_form'],
    ['show_task_form', ['project_id' => '', 'task_id' => ''], 'task_form'],
]);

// --- Validation feedback and blank values -----------------------------------

test('refused writes are marked as having changed nothing', function (string $tool, array $arguments) {
    Passport::actingAs($this->user, ['mcp:use'], 'api');
    $arguments = array_map(fn ($value) => $value === 'foreign' ? $this->otherProject->id : $value, $arguments);

    $result = mcpResult(callMcpToolOverHttp($this, $tool, $arguments)->assertOk());

    expect($result['isError'])->toBeTrue()
        ->and($result['content'][0]['text'])->toBe('Project not found.')
        ->and($result['_meta']['tasknet/refused'])->toBeTrue();
})->with([
    ['update_project', ['project_id' => 'foreign', 'name' => 'Hijacked']],
    ['create_task', ['project_id' => 'foreign', 'title' => 'Sneaky']],
    ['update_task', ['project_id' => 'foreign', 'task_id' => 1, 'title' => 'Hijacked']],
]);

test('an unexpected failure is not marked, because the change may have been saved', function () {
    config()->set('app.debug', false);
    Exceptions::fake();
    $this->mock(CreateProject::class)->shouldReceive('create')->andReturnUsing(function (User $user, array $data): never {
        $user->projects()->create($data);

        throw new RuntimeException('Lost the database connection after saving');
    });
    Passport::actingAs($this->user, ['mcp:use'], 'api');

    $result = mcpResult(callMcpToolOverHttp($this, 'create_project', ['name' => 'Saved anyway'])->assertOk());

    expect($result['isError'])->toBeTrue()
        ->and($result['content'][0]['text'])->toBe('An internal server error occurred.')
        ->and($result['_meta'] ?? [])->not->toHaveKeys(['tasknet/refused', 'tasknet/validationErrors'])
        ->and($this->user->projects()->where('name', 'Saved anyway')->exists())->toBeTrue();
});

test('validation failures name the fields in _meta and keep the same error text', function (string $class, array $arguments, string $field, string $message) {
    $arguments = array_map(fn ($value) => match ($value) {
        'own' => $this->project->id,
        'task' => $this->task->id,
        default => $value,
    }, $arguments);

    callMcpTool($this->user, $class, $arguments)->assertHasErrors([$message]);

    Passport::actingAs($this->user, ['mcp:use'], 'api');
    $tool = app($class)->name();
    $result = mcpResult(callMcpToolOverHttp($this, $tool, $arguments)->assertOk());

    expect($result['isError'])->toBeTrue()
        ->and($result['content'][0]['text'])->toContain($message)
        ->and($result['_meta']['tasknet/validationErrors'][$field][0])->toContain($message)
        ->and($result)->not->toHaveKey('structuredContent');
})->with([
    'project name missing' => [CreateProjectTool::class, [], 'name', 'The name field is required.'],
    'project name too long' => [UpdateProjectTool::class, ['project_id' => 'own', 'name' => str_repeat('a', 121)], 'name', 'must not be greater than 120 characters'],
    'task title missing' => [CreateTaskTool::class, ['project_id' => 'own'], 'title', 'The title field is required.'],
    'task due date format' => [UpdateTaskTool::class, ['project_id' => 'own', 'task_id' => 'task', 'due_date' => '15/01/2026'], 'due_date', 'must match the format Y-m-d'],
]);

test('workspace limits are reported against a field', function () {
    config()->set('tasknet.limits.projects', 1);
    Passport::actingAs($this->user, ['mcp:use'], 'api');

    $result = mcpResult(callMcpToolOverHttp($this, 'create_project', ['name' => 'Over'])->assertOk());

    expect($result['_meta']['tasknet/validationErrors']['name'][0])->toContain('You can create up to 1 projects');
});

test('a blank optional value clears the field instead of storing an empty string', function (string $blank) {
    Passport::actingAs($this->user, ['mcp:use'], 'api');
    $this->task->update(['description' => 'Draft first']);
    $this->project->update(['description' => 'Before']);

    $task = mcpResult(callMcpToolOverHttp($this, 'update_task', [
        'project_id' => $this->project->id, 'task_id' => $this->task->id, 'description' => $blank, 'due_date' => $blank,
    ])->assertOk());
    callMcpToolOverHttp($this, 'update_project', ['project_id' => $this->project->id, 'description' => $blank])->assertOk();

    expect($task['isError'])->toBeFalse()
        ->and($task['structuredContent']['task']['due_date'])->toBeNull()
        ->and($this->task->fresh()->getRawOriginal('due_date'))->toBeNull()
        ->and($this->task->fresh()->description)->toBeNull()
        ->and($this->project->fresh()->description)->toBeNull();
})->with(['empty' => '', 'spaces' => '   ']);

test('omitted fields are still left unchanged', function () {
    Passport::actingAs($this->user, ['mcp:use'], 'api');
    $this->task->update(['description' => 'Keep me']);

    callMcpToolOverHttp($this, 'update_task', ['project_id' => $this->project->id, 'task_id' => $this->task->id, 'status' => 'completed'])->assertOk();

    expect($this->task->fresh()->description)->toBe('Keep me')
        ->and($this->task->fresh()->due_date->toDateString())->toBe('2026-01-15');
});

test('markup in task data is returned as plain data', function () {
    Passport::actingAs($this->user, ['mcp:use'], 'api');
    $payload = '<img src=x onerror=alert(1)></script><script>alert(2)</script>';

    $created = mcpResult(callMcpToolOverHttp($this, 'create_task', ['project_id' => $this->project->id, 'title' => $payload])->assertOk());

    // Stored and returned verbatim; the view is responsible for rendering it as text.
    expect($created['structuredContent']['task']['title'])->toBe($payload)
        ->and(mcpRequest($this, 'resources/read', ['uri' => appUri()])->json('result.contents.0.text'))->not->toContain($payload);
});
