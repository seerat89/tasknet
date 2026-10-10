<?php

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->user = User::factory()->demo()->create();
    $this->otherUser = User::factory()->demo()->create();
    $this->project = Project::factory()->for($this->user)->create();
    $this->otherProject = Project::factory()->for($this->otherUser)->create();
    $this->task = Task::factory()->for($this->project)->create(['title' => 'Original Task']);
    $this->otherTask = Task::factory()->for($this->otherProject)->create(['title' => 'Not Yours']);
});

test('guests are sent to login for task routes', function () {
    $this->get(route('projects.tasks.create', $this->project))->assertRedirect(route('login'));
    $this->post(route('projects.tasks.store', $this->project))->assertRedirect(route('login'));
    $this->get(route('projects.tasks.edit', [$this->project, $this->task]))->assertRedirect(route('login'));
    $this->put(route('projects.tasks.update', [$this->project, $this->task]))->assertRedirect(route('login'));
    $this->delete(route('projects.tasks.destroy', [$this->project, $this->task]))->assertRedirect(route('login'));
});

test('the task form opens for an own project with sensible defaults', function () {
    $this->actingAs($this->user)->get(route('projects.tasks.create', $this->project))
        ->assertOk()
        ->assertSeeText(['New task', 'Title', 'Status', 'Priority', 'Due date'])
        ->assertSee('<option value="todo" selected>', false)
        ->assertSee('<option value="medium" selected>', false)
        ->assertSee('action="'.route('projects.tasks.store', $this->project).'"', false);
});

test('the task form cannot be opened for another users project', function () {
    $this->actingAs($this->user)->get(route('projects.tasks.create', $this->otherProject))->assertForbidden();
});

test('a task can be created through the web flow', function () {
    $this->actingAs($this->user)->post(route('projects.tasks.store', $this->project), [
        'title' => 'Ship it',
        'description' => 'Before Friday',
        'status' => 'in_progress',
        'priority' => 'high',
        'due_date' => '2026-12-24',
    ])->assertRedirect(route('projects.show', $this->project))->assertSessionHas('success', 'Task created.');

    $task = Task::where('title', 'Ship it')->sole();
    expect($task->project_id)->toBe($this->project->id)
        ->and($task->status)->toBe(TaskStatus::InProgress)
        ->and($task->priority)->toBe(TaskPriority::High)
        ->and($task->due_date->toDateString())->toBe('2026-12-24')
        ->and($task->description)->toBe('Before Friday');
});

test('tasks cannot be created in another users project', function () {
    $this->actingAs($this->user)->post(route('projects.tasks.store', $this->otherProject), [
        'title' => 'Sneaky', 'status' => 'todo', 'priority' => 'low',
    ])->assertForbidden();

    expect(Task::where('title', 'Sneaky')->exists())->toBeFalse();
});

test('task input is validated', function (array $input, string $field) {
    $this->actingAs($this->user)->from(route('projects.tasks.create', $this->project))
        ->post(route('projects.tasks.store', $this->project), $input + ['title' => 'Valid', 'status' => 'todo', 'priority' => 'medium'])
        ->assertRedirect(route('projects.tasks.create', $this->project))
        ->assertSessionHasErrors($field);
})->with([
    'missing title' => [['title' => ''], 'title'],
    'title too long' => [['title' => str_repeat('a', 161)], 'title'],
    'invalid status' => [['status' => 'done'], 'status'],
    'invalid priority' => [['priority' => 'urgent'], 'priority'],
    'invalid due date' => [['due_date' => 'not-a-date'], 'due_date'],
]);

test('the task limit error is shown on the form', function () {
    config()->set('tasknet.limits.tasks', 1);

    $this->actingAs($this->user)->from(route('projects.tasks.create', $this->project))
        ->post(route('projects.tasks.store', $this->project), ['title' => 'Second', 'status' => 'todo', 'priority' => 'medium'])
        ->assertSessionHasErrors(['title' => 'You can create up to 1 tasks in a demo workspace.']);

    $this->followingRedirects()->from(route('projects.tasks.create', $this->project))
        ->post(route('projects.tasks.store', $this->project), ['title' => 'Second', 'status' => 'todo', 'priority' => 'medium'])
        ->assertSeeText('You can create up to 1 tasks in a demo workspace.')
        ->assertSee('aria-describedby="title-error"', false);
    expect($this->user->tasks()->count())->toBe(1);
});

test('the project page indicates the workspace task limit when it is reached', function () {
    config()->set('tasknet.limits.tasks', 1);

    $this->actingAs($this->user)->get(route('projects.show', $this->project))
        ->assertSeeText('This demo workspace has reached its limit of 1 tasks.')
        ->assertDontSee('href="'.route('projects.tasks.create', $this->project).'"', false);
});

test('the project page lists tasks with readable status, priority and due date', function () {
    Task::factory()->for($this->project)->create([
        'title' => 'Labelled', 'status' => 'in_progress', 'priority' => 'high', 'due_date' => '2026-12-24',
    ]);

    $this->actingAs($this->user)->get(route('projects.show', $this->project))
        ->assertSeeText(['Labelled', 'In progress', 'High', 'Dec 24, 2026']);
});

test('the project page shows an empty state when there are no tasks', function () {
    $this->task->delete();

    $this->actingAs($this->user)->get(route('projects.show', $this->project))
        ->assertSeeText('No tasks yet.')
        ->assertSee('href="'.route('projects.tasks.create', $this->project).'"', false);
});

test('an own task can be edited and updated', function () {
    $this->actingAs($this->user)->get(route('projects.tasks.edit', [$this->project, $this->task]))
        ->assertOk()
        ->assertSee('value="Original Task"', false);

    $this->put(route('projects.tasks.update', [$this->project, $this->task]), [
        'title' => 'Renamed', 'description' => '', 'status' => 'completed', 'priority' => 'low', 'due_date' => '',
    ])->assertRedirect(route('projects.show', $this->project))->assertSessionHas('success', 'Task updated.');

    $task = $this->task->fresh();
    expect($task->title)->toBe('Renamed')
        ->and($task->status)->toBe(TaskStatus::Completed)
        ->and($task->priority)->toBe(TaskPriority::Low)
        ->and($task->due_date)->toBeNull();
});

test('another users task cannot be edited or updated', function () {
    $this->actingAs($this->user)->get(route('projects.tasks.edit', [$this->otherProject, $this->otherTask]))->assertForbidden();
    $this->put(route('projects.tasks.update', [$this->otherProject, $this->otherTask]), [
        'title' => 'Hijacked', 'status' => 'todo', 'priority' => 'low',
    ])->assertForbidden();

    expect($this->otherTask->fresh()->title)->toBe('Not Yours');
});

test('a submitted project_id cannot move a task to another project', function () {
    $this->actingAs($this->user)->put(route('projects.tasks.update', [$this->project, $this->task]), [
        'title' => 'Stay', 'status' => 'todo', 'priority' => 'low', 'project_id' => $this->otherProject->id,
    ])->assertRedirect();

    $this->post(route('projects.tasks.store', $this->project), [
        'title' => 'Created', 'status' => 'todo', 'priority' => 'low', 'project_id' => $this->otherProject->id,
    ]);

    expect($this->task->fresh()->project_id)->toBe($this->project->id)
        ->and(Task::where('title', 'Created')->sole()->project_id)->toBe($this->project->id);
});

test('a task can be deleted', function () {
    $this->actingAs($this->user)->delete(route('projects.tasks.destroy', [$this->project, $this->task]))
        ->assertRedirect(route('projects.show', $this->project))
        ->assertSessionHas('success', 'Task deleted.');

    expect(Task::find($this->task->id))->toBeNull();
});

test('another users task cannot be deleted', function () {
    $this->actingAs($this->user)->delete(route('projects.tasks.destroy', [$this->otherProject, $this->otherTask]))->assertForbidden();

    expect($this->otherTask->fresh())->not->toBeNull();
});

test('the task delete confirmation identifies the task', function () {
    $this->actingAs($this->user)->get(route('projects.tasks.edit', [$this->project, $this->task]))
        ->assertSee('<dialog', false)
        ->assertSeeText('“Original Task” will be permanently deleted.')
        ->assertSee('action="'.route('projects.tasks.destroy', [$this->project, $this->task]).'"', false)
        ->assertSee('name="_method" value="DELETE"', false);
});

test('a task cannot be manipulated through a mismatched project url', function () {
    $second = Project::factory()->for($this->user)->create();

    $this->actingAs($this->user);
    $this->get(route('projects.tasks.edit', [$second, $this->task]))->assertNotFound();
    $this->put(route('projects.tasks.update', [$second, $this->task]), ['title' => 'X', 'status' => 'todo', 'priority' => 'low'])->assertNotFound();
    $this->delete(route('projects.tasks.destroy', [$second, $this->task]))->assertNotFound();

    expect($this->task->fresh()->title)->toBe('Original Task')
        ->and($this->task->fresh()->project_id)->toBe($this->project->id);
});
