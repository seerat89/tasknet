<?php

use App\Actions\CreateProject;
use App\Actions\CreateTask;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->demo()->create();
    $this->otherUser = User::factory()->demo()->create();
});

test('the configured defaults are ten projects and one hundred tasks', function () {
    expect(config('tasknet.limits.projects'))->toBe(10)
        ->and(config('tasknet.limits.tasks'))->toBe(100);
});

test('projects can be created below the limit', function () {
    config()->set('tasknet.limits.projects', 3);

    foreach (range(1, 3) as $number) {
        app(CreateProject::class)->create($this->user, ['name' => "Project {$number}"]);
    }

    expect($this->user->projects()->count())->toBe(3);
});

test('creating a project beyond the limit fails with a validation error', function () {
    config()->set('tasknet.limits.projects', 2);
    Project::factory()->count(2)->for($this->user)->create();

    expect(fn () => app(CreateProject::class)->create($this->user, ['name' => 'One too many']))
        ->toThrow(ValidationException::class, 'You can create up to 2 projects in a demo workspace.');
    expect($this->user->projects()->count())->toBe(2);
});

test('the default limit rejects the eleventh project', function () {
    Project::factory()->count(10)->for($this->user)->create();

    expect(fn () => app(CreateProject::class)->create($this->user, ['name' => 'Eleventh']))
        ->toThrow(ValidationException::class, 'You can create up to 10 projects in a demo workspace.');
});

test('other users projects do not count toward the project limit', function () {
    config()->set('tasknet.limits.projects', 2);
    Project::factory()->count(5)->for($this->otherUser)->create();

    $project = app(CreateProject::class)->create($this->user, ['name' => 'Fine']);

    expect($project->exists)->toBeTrue();
});

test('tasks can be created below the limit', function () {
    config()->set('tasknet.limits.tasks', 3);
    $project = Project::factory()->for($this->user)->create();

    foreach (range(1, 3) as $number) {
        app(CreateTask::class)->create($this->user, $project, ['title' => "Task {$number}"]);
    }

    expect($this->user->tasks()->count())->toBe(3);
});

test('creating a task beyond the limit fails with a validation error', function () {
    config()->set('tasknet.limits.tasks', 2);
    $project = Project::factory()->for($this->user)->create();
    Task::factory()->count(2)->for($project)->create();

    expect(fn () => app(CreateTask::class)->create($this->user, $project, ['title' => 'One too many']))
        ->toThrow(ValidationException::class, 'You can create up to 2 tasks in a demo workspace.');
    expect($this->user->tasks()->count())->toBe(2);
});

test('the default limit rejects the 101st task', function () {
    $project = Project::factory()->for($this->user)->create();
    Task::factory()->count(100)->for($project)->create();

    expect(fn () => app(CreateTask::class)->create($this->user, $project, ['title' => 'Task 101']))
        ->toThrow(ValidationException::class, 'You can create up to 100 tasks in a demo workspace.');
});

test('the task limit counts tasks across all projects', function () {
    config()->set('tasknet.limits.tasks', 3);
    $first = Project::factory()->for($this->user)->create();
    $second = Project::factory()->for($this->user)->create();
    Task::factory()->count(2)->for($first)->create();
    Task::factory()->for($second)->create();

    expect(fn () => app(CreateTask::class)->create($this->user, $second, ['title' => 'Over the total']))
        ->toThrow(ValidationException::class);
    expect(fn () => app(CreateTask::class)->create($this->user, $first, ['title' => 'Over the total']))
        ->toThrow(ValidationException::class);
});

test('other users tasks do not count toward the task limit', function () {
    config()->set('tasknet.limits.tasks', 2);
    Task::factory()->count(5)->for(Project::factory()->for($this->otherUser))->create();
    $project = Project::factory()->for($this->user)->create();

    $task = app(CreateTask::class)->create($this->user, $project, ['title' => 'Fine']);

    expect($task->exists)->toBeTrue();
});

test('create project always assigns the supplied user', function () {
    $project = app(CreateProject::class)->create($this->user, ['name' => 'Mine', 'description' => 'Notes']);

    expect($project->user->is($this->user))->toBeTrue()
        ->and($project->name)->toBe('Mine')
        ->and($project->description)->toBe('Notes');
});

test('create task always assigns the supplied project', function () {
    $project = Project::factory()->for($this->user)->create();

    $task = app(CreateTask::class)->create($this->user, $project, [
        'title' => 'Ship it',
        'status' => 'in_progress',
        'priority' => 'high',
        'due_date' => '2026-12-24',
    ]);

    expect($task->project->is($project))->toBeTrue()
        ->and($task->status->value)->toBe('in_progress')
        ->and($task->priority->value)->toBe('high')
        ->and($task->due_date->toDateString())->toBe('2026-12-24');
});

test('create task refuses a project owned by another user', function () {
    $otherProject = Project::factory()->for($this->otherUser)->create();

    expect(fn () => app(CreateTask::class)->create($this->user, $otherProject, ['title' => 'Sneaky']))
        ->toThrow(AuthorizationException::class);
    expect(Task::count())->toBe(0);
});

test('create project refuses expired users', function () {
    $user = User::factory()->demo()->expired()->create();

    expect(fn () => app(CreateProject::class)->create($user, ['name' => 'Too late']))
        ->toThrow(AuthorizationException::class);
});

test('ownership cannot be overridden through input arrays', function () {
    $project = app(CreateProject::class)->create($this->user, [
        'name' => 'Mine',
        'user_id' => $this->otherUser->id,
    ]);

    $ownProject = Project::factory()->for($this->user)->create();
    $otherProject = Project::factory()->for($this->otherUser)->create();
    $task = app(CreateTask::class)->create($this->user, $ownProject, [
        'title' => 'Mine',
        'project_id' => $otherProject->id,
    ]);

    expect($project->user_id)->toBe($this->user->id)
        ->and($task->project_id)->toBe($ownProject->id)
        ->and($otherProject->tasks()->count())->toBe(0)
        ->and($this->otherUser->projects()->count())->toBe(1);
});
