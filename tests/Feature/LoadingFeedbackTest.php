<?php

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->demo()->create();
    $this->project = Project::factory()->for($this->user)->create();
    $this->task = Task::factory()->for($this->project)->create();
});

test('every layout carries the progress bar, the delayed loader and a polite status region', function () {
    foreach ([fn () => $this->get(route('home')), fn () => $this->get(route('login')), fn () => $this->actingAs($this->user)->get(route('dashboard'))] as $request) {
        $request()->assertOk()
            ->assertSee('data-progress', false)
            ->assertSee('data-loader', false)
            ->assertSee(asset('brand/tasknet-mark.svg'), false)
            ->assertSee('role="status" aria-live="polite"', false);
    }
});

test('the loading UI is decorative and never takes focus', function () {
    $html = $this->get(route('home'))->getContent();

    expect($html)
        ->toContain('data-progress popover="manual" hidden aria-hidden="true"')
        ->toContain('data-loader popover="manual" hidden aria-hidden="true"')
        ->toContain('pointer-events-none');
});

test('important forms name what their button is doing while the request is sent', function () {
    $this->get(route('login'))->assertSee('data-loading-text="Signing in…"', false);
    $this->get(route('demo.create'))->assertSee('data-loading-text="Starting…"', false);

    $this->actingAs($this->user);

    $this->get(route('projects.create'))->assertSee('data-loading-text="Creating…"', false);
    $this->get(route('projects.edit', $this->project))->assertSee('data-loading-text="Saving…"', false);
    $this->get(route('projects.tasks.create', $this->project))->assertSee('data-loading-text="Creating…"', false);
    $this->get(route('projects.tasks.edit', [$this->project, $this->task]))->assertSee('data-loading-text="Saving…"', false);
    $this->get(route('projects.show', $this->project))->assertSee('data-loading-text="Deleting…"', false);
    $this->get(route('settings'))->assertSee('data-loading-text="Deleting…"', false)->assertSee('data-loading-text="Logging out…"', false);
});

test('the search form and the credentials download are left alone', function () {
    $this->actingAs($this->user)->get(route('projects.index'))
        ->assertSee('role="search"', false)
        ->assertDontSee('data-loading-text="Searching', false);
});
