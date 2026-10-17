<?php

use App\Actions\CreateProject;
use App\Actions\CreateTask;
use App\Actions\DeleteProject;
use App\Actions\DeleteTask;
use App\Actions\UpdateProject;
use App\Actions\UpdateTask;
use App\Enums\ChangeType;
use App\Events\ProjectChanged;
use App\Events\TaskChanged;
use App\Http\Middleware\EnsureDemoWorkspaceIsActive;
use App\Mcp\Tools\CreateProjectTool;
use App\Mcp\Tools\UpdateTaskTool;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->demo()->create();
    $this->otherUser = User::factory()->demo()->create();
    $this->project = Project::factory()->for($this->user)->create();
    $this->task = Task::factory()->for($this->project)->create();
});

/**
 * Register the workspace channel on a real (Reverb) broadcaster so its authorization runs.
 */
function useReverbBroadcaster(): void
{
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => 'test-app',
    ]);

    Broadcast::forgetDrivers();

    require base_path('routes/channels.php');
}

function authorizeChannel(mixed $test, User $user, int $workspaceId): TestResponse
{
    return $test->actingAs($user)->postJson('/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => 'private-workspace.'.$workspaceId,
    ]);
}

test('a workspace owner can authorize their own private channel', function () {
    useReverbBroadcaster();

    authorizeChannel($this, $this->user, $this->user->id)
        ->assertOk()
        ->assertJsonStructure(['auth']);
});

test('another workspace cannot authorize the channel', function () {
    useReverbBroadcaster();

    authorizeChannel($this, $this->otherUser, $this->user->id)->assertForbidden();
});

test('guests cannot authorize any channel', function () {
    useReverbBroadcaster();

    $this->postJson('/broadcasting/auth', [
        'socket_id' => '1234.5678',
        'channel_name' => 'private-workspace.'.$this->user->id,
    ])->assertUnauthorized();
});

test('an expired demo workspace cannot authorize its channel', function () {
    useReverbBroadcaster();
    $expired = User::factory()->demo()->expired()->create();

    // Even when the web expiry middleware is bypassed, the channel itself refuses.
    $this->withoutMiddleware(EnsureDemoWorkspaceIsActive::class);

    authorizeChannel($this, $expired, $expired->id)->assertForbidden();
});

test('events broadcast only a change type on the owners private channel', function () {
    $event = new ProjectChanged($this->user->id, ChangeType::Updated);

    expect($event->broadcastOn())->toHaveCount(1)
        ->and($event->broadcastOn()[0]->name)->toBe('private-workspace.'.$this->user->id)
        ->and($event->broadcastAs())->toBe('project.changed')
        ->and($event->broadcastWith())->toBe(['change' => 'updated'])
        ->and((new TaskChanged($this->user->id, ChangeType::Created))->broadcastAs())->toBe('task.changed');
});

test('creating a project emits a project event for that workspace', function () {
    Event::fake([ProjectChanged::class]);

    app(CreateProject::class)->create($this->user, ['name' => 'Realtime']);

    Event::assertDispatched(ProjectChanged::class, fn (ProjectChanged $event) => $event->userId === $this->user->id
        && $event->change === ChangeType::Created);
});

test('updating a project emits a project event for that workspace', function () {
    Event::fake([ProjectChanged::class]);

    app(UpdateProject::class)->update($this->user, $this->project, ['name' => 'Renamed']);

    Event::assertDispatched(ProjectChanged::class, fn (ProjectChanged $event) => $event->userId === $this->user->id
        && $event->change === ChangeType::Updated);
});

test('deleting a project emits a project event for that workspace', function () {
    Event::fake([ProjectChanged::class]);

    app(DeleteProject::class)->delete($this->user, $this->project);

    Event::assertDispatched(ProjectChanged::class, fn (ProjectChanged $event) => $event->change === ChangeType::Deleted);
});

test('creating a task emits a task event for that workspace', function () {
    Event::fake([TaskChanged::class]);

    app(CreateTask::class)->create($this->user, $this->project, ['title' => 'Realtime']);

    Event::assertDispatched(TaskChanged::class, fn (TaskChanged $event) => $event->userId === $this->user->id
        && $event->change === ChangeType::Created);
});

test('updating a task emits a task event for that workspace', function () {
    Event::fake([TaskChanged::class]);

    app(UpdateTask::class)->update($this->user, $this->project, $this->task, ['title' => 'Renamed']);

    Event::assertDispatched(TaskChanged::class, fn (TaskChanged $event) => $event->userId === $this->user->id
        && $event->change === ChangeType::Updated);
});

test('deleting a task emits a task event for that workspace', function () {
    Event::fake([TaskChanged::class]);

    app(DeleteTask::class)->delete($this->user, $this->project, $this->task);

    Event::assertDispatched(TaskChanged::class, fn (TaskChanged $event) => $event->change === ChangeType::Deleted);
});

test('nothing is announced when a mutation is rejected', function () {
    Event::fake([ProjectChanged::class, TaskChanged::class]);
    config()->set('tasknet.limits.projects', 1);
    config()->set('tasknet.limits.tasks', 1);

    expect(fn () => app(CreateProject::class)->create($this->user, ['name' => 'Over the limit']))->toThrow(ValidationException::class);
    expect(fn () => app(CreateTask::class)->create($this->user, $this->project, ['title' => 'Over the limit']))->toThrow(ValidationException::class);
    expect(fn () => app(UpdateProject::class)->update($this->otherUser, $this->project, ['name' => 'Hijack']))->toThrow(AuthorizationException::class);

    Event::assertNothingDispatched();
});

test('web requests announce changes through the shared actions', function () {
    Event::fake([ProjectChanged::class, TaskChanged::class]);

    $this->actingAs($this->user)->post(route('projects.store'), ['name' => 'From the web'])->assertRedirect();
    $this->actingAs($this->user)->post(route('projects.tasks.store', $this->project), [
        'title' => 'From the web',
        'status' => 'todo',
        'priority' => 'low',
    ])->assertRedirect();

    Event::assertDispatched(ProjectChanged::class, fn (ProjectChanged $event) => $event->userId === $this->user->id);
    Event::assertDispatched(TaskChanged::class, fn (TaskChanged $event) => $event->userId === $this->user->id);
});

test('MCP tools announce changes through the shared actions', function () {
    Event::fake([ProjectChanged::class, TaskChanged::class]);

    callMcpTool($this->user, CreateProjectTool::class, ['name' => 'From MCP'])->assertOk();
    callMcpTool($this->user, UpdateTaskTool::class, [
        'project_id' => $this->project->id,
        'task_id' => $this->task->id,
        'title' => 'From MCP',
    ])->assertOk();

    Event::assertDispatched(ProjectChanged::class, fn (ProjectChanged $event) => $event->userId === $this->user->id
        && $event->change === ChangeType::Created);
    Event::assertDispatched(TaskChanged::class, fn (TaskChanged $event) => $event->userId === $this->user->id
        && $event->change === ChangeType::Updated);
});

test('a realtime outage never fails a saved change', function () {
    Event::listen(ProjectChanged::class, fn () => throw new RuntimeException('Reverb is down'));

    $project = app(CreateProject::class)->create($this->user, ['name' => 'Still saved']);

    expect($project->exists)->toBeTrue()
        ->and(Project::where('name', 'Still saved')->exists())->toBeTrue();
});

test('only the dashboard and project pages subscribe to the workspace channel', function () {
    $channel = '<meta name="workspace-channel" content="workspace.'.$this->user->id.'">';

    $this->actingAs($this->user)->get(route('dashboard'))->assertOk()->assertSee($channel, false);
    $this->actingAs($this->user)->get(route('projects.show', $this->project))->assertOk()->assertSee($channel, false);
    $this->actingAs($this->user)->get(route('projects.edit', $this->project))->assertOk()->assertDontSee('workspace-channel');
});

test('realtime pages do not expose the internal email', function () {
    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee($this->user->email)
        ->assertDontSee('tasknet.local');
});

test('pages describe the Reverb connection at render time only when broadcasting is on', function () {
    $this->actingAs($this->user)->get(route('dashboard'))->assertOk()->assertDontSee('realtime-connection');

    config([
        'broadcasting.default' => 'reverb',
        'reverb.apps.apps.0.key' => 'public-key',
        'tasknet.realtime' => ['host' => null, 'port' => null, 'scheme' => null],
    ]);

    // Unset values follow the page's own address, as they do behind the production proxy.
    $this->actingAs($this->user)->get('https://tasknet.test/dashboard')->assertOk()
        ->assertSee(e(json_encode(['key' => 'public-key', 'host' => 'tasknet.test', 'port' => 443, 'scheme' => 'https'])), false);

    config(['tasknet.realtime' => ['host' => 'localhost', 'port' => '8080', 'scheme' => 'http']]);

    $this->actingAs($this->user)->get(route('dashboard'))->assertOk()
        ->assertSee(e(json_encode(['key' => 'public-key', 'host' => 'localhost', 'port' => 8080, 'scheme' => 'http'])), false)
        ->assertDontSee('secret');
});
