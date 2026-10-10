<?php

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;

uses(LazilyRefreshDatabase::class);

test('a user has many projects', function () {
    $user = User::factory()->demo()->create();
    $projects = Project::factory()->count(2)->for($user)->create();

    expect($user->projects())->toBeInstanceOf(HasMany::class)
        ->and($user->projects->pluck('id')->all())->toEqualCanonicalizing($projects->pluck('id')->all());
});

test('a project belongs to a user', function () {
    $user = User::factory()->demo()->create();
    $project = Project::factory()->for($user)->create();

    expect($project->user())->toBeInstanceOf(BelongsTo::class)
        ->and($project->user->is($user))->toBeTrue();
});

test('a project has many tasks', function () {
    $project = Project::factory()->create();
    $tasks = Task::factory()->count(3)->for($project)->create();

    expect($project->tasks())->toBeInstanceOf(HasMany::class)
        ->and($project->tasks->pluck('id')->all())->toEqualCanonicalizing($tasks->pluck('id')->all());
});

test('a task belongs to a project', function () {
    $project = Project::factory()->create();
    $task = Task::factory()->for($project)->create();

    expect($task->project())->toBeInstanceOf(BelongsTo::class)
        ->and($task->project->is($project))->toBeTrue();
});

test('factories create internally consistent relationships', function () {
    $task = Task::factory()->create();

    expect($task->project->user->is_demo)->toBeTrue()
        ->and(Project::count())->toBe(1)
        ->and(User::count())->toBe(1);
});

test('a user reaches all of their tasks across projects', function () {
    $user = User::factory()->demo()->create();
    Task::factory()->count(2)->for(Project::factory()->for($user))->create();
    Task::factory()->count(3)->for(Project::factory()->for($user))->create();
    Task::factory()->create();

    expect($user->tasks()->count())->toBe(5);
});

test('task status casts to the TaskStatus enum', function () {
    $task = Task::factory()->create(['status' => 'in_progress']);

    expect($task->fresh()->status)->toBe(TaskStatus::InProgress);
});

test('task priority casts to the TaskPriority enum', function () {
    $task = Task::factory()->create(['priority' => 'high']);

    expect($task->fresh()->priority)->toBe(TaskPriority::High);
});

test('due date casts to a date', function () {
    $task = Task::factory()->create(['due_date' => '2026-12-24']);

    expect($task->fresh()->due_date)->toBeInstanceOf(Carbon::class)
        ->and($task->fresh()->due_date->toDateString())->toBe('2026-12-24');
});

test('due date is optional', function () {
    $task = Task::factory()->create(['due_date' => null]);

    expect($task->fresh()->due_date)->toBeNull();
});

test('a new task defaults to todo status and medium priority', function () {
    $project = Project::factory()->create();

    $task = $project->tasks()->create(['title' => 'Write tests']);

    expect($task->status)->toBe(TaskStatus::Todo)
        ->and($task->priority)->toBe(TaskPriority::Medium)
        ->and($task->fresh()->status)->toBe(TaskStatus::Todo)
        ->and($task->fresh()->priority)->toBe(TaskPriority::Medium);
});

test('the database defaults match the model defaults', function () {
    $project = Project::factory()->create();

    $project->tasks()->getQuery()->insert([
        'project_id' => $project->id,
        'title' => 'Raw insert',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $task = Task::query()->where('title', 'Raw insert')->sole();

    expect($task->status)->toBe(TaskStatus::Todo)
        ->and($task->priority)->toBe(TaskPriority::Medium);
});

test('deleting a user deletes their projects and tasks', function () {
    $user = User::factory()->demo()->create();
    $other = Project::factory()->create();
    Task::factory()->count(2)->for(Project::factory()->for($user))->create();

    $user->delete();

    expect(Project::count())->toBe(1)
        ->and(Project::sole()->is($other))->toBeTrue()
        ->and(Task::count())->toBe(0);
});

test('deleting a project deletes its tasks', function () {
    $project = Project::factory()->create();
    Task::factory()->count(2)->for($project)->create();
    $otherTask = Task::factory()->create();

    $project->delete();

    expect(Task::count())->toBe(1)
        ->and(Task::sole()->is($otherTask))->toBeTrue();
});

test('ownership columns cannot be mass assigned', function () {
    $other = User::factory()->demo()->create();
    $otherProject = Project::factory()->create();

    $project = new Project(['name' => 'Mine', 'user_id' => $other->id]);
    $task = new Task(['title' => 'Mine', 'project_id' => $otherProject->id]);

    expect($project->user_id)->toBeNull()
        ->and($task->project_id)->toBeNull();
});
