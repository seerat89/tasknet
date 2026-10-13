<?php

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->user = User::factory()->demo()->create();
    $this->otherUser = User::factory()->demo()->create();
    $this->project = Project::factory()->for($this->user)->create(['name' => 'Original', 'description' => 'Original notes']);
    $this->otherProject = Project::factory()->for($this->otherUser)->create(['name' => 'Not Yours']);
});

test('guests are sent to login for every project route', function (string $method, string $route) {
    $this->{$method}(route($route, $this->project))->assertRedirect(route('login'));
})->with([
    ['get', 'projects.create'],
    ['post', 'projects.store'],
    ['get', 'projects.show'],
    ['get', 'projects.edit'],
    ['put', 'projects.update'],
    ['delete', 'projects.destroy'],
]);

test('the create form can be opened', function () {
    $this->actingAs($this->user)->get(route('projects.create'))
        ->assertOk()
        ->assertSeeText(['New project', 'Name', 'Description'])
        ->assertSee('action="'.route('projects.store').'"', false);
});

test('a project can be created through the web flow', function () {
    $response = $this->actingAs($this->user)->post(route('projects.store'), [
        'name' => 'Website Redesign',
        'description' => 'Refresh the marketing site',
    ]);

    $project = Project::where('name', 'Website Redesign')->sole();
    $response->assertRedirect(route('projects.show', $project))->assertSessionHas('success', 'Project created.');
    expect($project->user_id)->toBe($this->user->id)
        ->and($project->description)->toBe('Refresh the marketing site');
    $this->get(route('projects.show', $project))->assertSeeText('Project created.');
});

test('project input is validated', function (array $input, string $field) {
    $this->actingAs($this->user)->from(route('projects.create'))->post(route('projects.store'), $input)
        ->assertRedirect(route('projects.create'))
        ->assertSessionHasErrors($field);
    expect(Project::count())->toBe(2);
})->with([
    'missing name' => [['name' => ''], 'name'],
    'name too long' => [['name' => str_repeat('a', 121)], 'name'],
    'description too long' => [['name' => 'Fine', 'description' => str_repeat('a', 2001)], 'description'],
]);

test('the project limit error is shown on the form', function () {
    config()->set('tasknet.limits.projects', 1);

    $this->actingAs($this->user)->from(route('projects.create'))->post(route('projects.store'), ['name' => 'Second'])
        ->assertRedirect(route('projects.create'))
        ->assertSessionHasErrors(['name' => 'You can create up to 1 projects in a demo workspace.']);

    $this->actingAs($this->user)->followingRedirects()->from(route('projects.create'))->post(route('projects.store'), ['name' => 'Second'])
        ->assertSeeText('You can create up to 1 projects in a demo workspace.')
        ->assertSee('aria-describedby="name-error"', false);
    expect($this->user->projects()->count())->toBe(1);
});

test('an own project can be viewed with its details', function () {
    Task::factory()->for($this->project)->create(['title' => 'Visible Task']);

    $this->actingAs($this->user)->get(route('projects.show', $this->project))
        ->assertOk()
        ->assertSeeText(['Original', 'Original notes', 'Visible Task', 'Edit project', 'Delete project', 'New task']);
});

test('another users project cannot be viewed', function () {
    $this->actingAs($this->user)->get(route('projects.show', $this->otherProject))->assertForbidden();
});

test('an own project can be edited and updated', function () {
    $this->actingAs($this->user)->get(route('projects.edit', $this->project))
        ->assertOk()
        ->assertSee('value="Original"', false)
        ->assertSeeText('Original notes');

    $this->put(route('projects.update', $this->project), ['name' => 'Renamed', 'description' => ''])
        ->assertRedirect(route('projects.show', $this->project))
        ->assertSessionHas('success', 'Project updated.');

    expect($this->project->fresh()->name)->toBe('Renamed')
        ->and($this->project->fresh()->description)->toBeNull();
});

test('another users project cannot be edited or updated', function () {
    $this->actingAs($this->user)->get(route('projects.edit', $this->otherProject))->assertForbidden();
    $this->put(route('projects.update', $this->otherProject), ['name' => 'Hijacked'])->assertForbidden();

    expect($this->otherProject->fresh()->name)->toBe('Not Yours');
});

test('a submitted user_id cannot change project ownership', function () {
    $this->actingAs($this->user)->put(route('projects.update', $this->project), [
        'name' => 'Still Mine',
        'user_id' => $this->otherUser->id,
    ])->assertRedirect();

    $created = $this->post(route('projects.store'), ['name' => 'New One', 'user_id' => $this->otherUser->id]);

    expect($this->project->fresh()->user_id)->toBe($this->user->id)
        ->and(Project::where('name', 'New One')->sole()->user_id)->toBe($this->user->id);
});

test('a project can be deleted and its tasks are deleted with it', function () {
    Task::factory()->count(2)->for($this->project)->create();
    $otherTask = Task::factory()->for($this->otherProject)->create();

    $this->actingAs($this->user)->delete(route('projects.destroy', $this->project))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('success', 'Project deleted.');

    expect(Project::find($this->project->id))->toBeNull()
        ->and(Task::count())->toBe(1)
        ->and(Task::sole()->is($otherTask))->toBeTrue();
});

test('another users project cannot be deleted', function () {
    $this->actingAs($this->user)->delete(route('projects.destroy', $this->otherProject))->assertForbidden();

    expect($this->otherProject->fresh())->not->toBeNull();
});

test('the delete confirmation names the consequences and uses a CSRF protected DELETE form', function () {
    Task::factory()->count(2)->for($this->project)->create();

    $this->actingAs($this->user)->get(route('projects.show', $this->project))
        ->assertSee('<dialog', false)
        ->assertSeeText('and all 2 of its tasks will be permanently deleted.')
        ->assertSee('action="'.route('projects.destroy', $this->project).'"', false)
        ->assertSee('name="_method" value="DELETE"', false)
        ->assertSee('name="_token"', false);
});

test('expired demo users cannot use project routes', function () {
    $expired = User::factory()->demo()->expired()->create();
    $project = Project::factory()->for($expired)->create();

    $this->actingAs($expired)->get(route('projects.show', $project))->assertRedirect(route('login'));
});

test('pages show a breadcrumb trail above their heading', function () {
    $task = Task::factory()->for($this->project)->create(['title' => 'Crumb Task']);
    $dashboard = 'href="'.route('dashboard').'"';
    $projectUrl = 'href="'.route('projects.show', $this->project).'"';

    $this->actingAs($this->user);

    $this->get(route('projects.show', $this->project))
        ->assertSee('aria-label="Breadcrumb"', false)
        ->assertSeeInOrder([$dashboard, 'Projects', 'aria-current="page"', 'Original'], false)
        ->assertDontSee('<a '.$projectUrl.' class="rounded-sm text-teal-700 underline decoration-teal-700/30 underline-offset-4 hover:text-teal-900 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-teal-600">Original</a>', false);

    $this->get(route('projects.create'))->assertSeeInOrder(['Breadcrumb', 'Projects', 'New project', '<h1'], false);
    $this->get(route('projects.edit', $this->project))->assertSeeInOrder(['Breadcrumb', 'Projects', 'Edit project', '<h1'], false);
    $this->get(route('projects.tasks.create', $this->project))->assertSeeInOrder(['Breadcrumb', $projectUrl, 'Original', 'New task', '<h1'], false);

    $taskEdit = $this->get(route('projects.tasks.edit', [$this->project, $task]))
        ->assertSeeInOrder(['Breadcrumb', $projectUrl, 'Original', 'Edit task', '<h1'], false);
    preg_match('/<nav aria-label="Breadcrumb".*?<\/nav>/s', $taskEdit->getContent(), $breadcrumb);
    expect($breadcrumb[0])->not->toContain('Crumb Task');
});
