<?php

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Policies\ProjectPolicy;
use App\Policies\TaskPolicy;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->demo()->create();
    $this->otherUser = User::factory()->demo()->create();
    $this->project = Project::factory()->for($this->user)->create();
    $this->otherProject = Project::factory()->for($this->otherUser)->create();
    $this->task = Task::factory()->for($this->project)->create();
    $this->otherTask = Task::factory()->for($this->otherProject)->create();
});

test('policies are discovered for both models', function () {
    expect(Gate::getPolicyFor(Project::class))->toBeInstanceOf(ProjectPolicy::class)
        ->and(Gate::getPolicyFor(Task::class))->toBeInstanceOf(TaskPolicy::class);
});

test('users can act on their own projects', function (string $ability) {
    expect($this->user->can($ability, $this->project))->toBeTrue();
})->with(['view', 'update', 'delete']);

test('users cannot act on another users project', function (string $ability) {
    expect($this->user->can($ability, $this->otherProject))->toBeFalse();
})->with(['view', 'update', 'delete']);

test('active demo users can list and create projects', function () {
    expect($this->user->can('viewAny', Project::class))->toBeTrue()
        ->and($this->user->can('create', Project::class))->toBeTrue();
});

test('expired and non-demo users cannot list or create projects', function (Closure $makeUser) {
    $user = $makeUser();

    expect($user->can('viewAny', Project::class))->toBeFalse()
        ->and($user->can('create', Project::class))->toBeFalse();
})->with([
    'expired demo' => [fn () => User::factory()->demo()->expired()->create()],
    'non-demo' => [fn () => User::factory()->create()],
]);

test('users can act on their own tasks', function (string $ability) {
    expect($this->user->can($ability, $this->task))->toBeTrue();
})->with(['view', 'update', 'delete']);

test('users cannot act on another users task', function (string $ability) {
    expect($this->user->can($ability, $this->otherTask))->toBeFalse();
})->with(['view', 'update', 'delete']);

test('tasks can only be created inside the users own project', function () {
    expect($this->user->can('create', [Task::class, $this->project]))->toBeTrue()
        ->and($this->user->can('create', [Task::class, $this->otherProject]))->toBeFalse();
});

test('expired users cannot create tasks even in their own project', function () {
    $user = User::factory()->demo()->expired()->create();
    $project = Project::factory()->for($user)->create();

    expect($user->can('create', [Task::class, $project]))->toBeFalse();
});
