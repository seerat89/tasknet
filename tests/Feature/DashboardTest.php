<?php

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->user = User::factory()->demo()->create();
});

test('guests cannot access the dashboard', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('active demo users can access the dashboard', function () {
    $this->actingAs($this->user)->get(route('dashboard'))
        ->assertOk()
        ->assertSeeText(['Projects', 'Organize a few projects and tasks, then connect your AI agent through MCP.']);
});

test('expired users cannot reach the dashboard', function () {
    $user = User::factory()->demo()->expired()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('the dashboard only shows the authenticated users projects', function () {
    Project::factory()->for($this->user)->create(['name' => 'My Visible Project']);
    Project::factory()->create(['name' => 'Someone Elses Project']);

    $this->actingAs($this->user)->get(route('dashboard'))
        ->assertSeeText('My Visible Project')
        ->assertDontSeeText('Someone Elses Project');
});

test('the dashboard shows project cards with task counts', function () {
    $project = Project::factory()->for($this->user)->create(['name' => 'Counted', 'description' => 'About counting']);
    Task::factory()->count(2)->for($project)->create(['status' => TaskStatus::Completed]);
    Task::factory()->for($project)->create(['status' => TaskStatus::Todo]);

    $this->actingAs($this->user)->get(route('dashboard'))
        ->assertSeeText(['Counted', 'About counting', '3 tasks', '2 completed'])
        ->assertSee('href="'.route('projects.show', $project).'"', false);
});

test('the dashboard shows workspace usage against the configured limits', function () {
    $first = Project::factory()->for($this->user)->create();
    $second = Project::factory()->for($this->user)->create();
    Task::factory()->count(2)->for($first)->create();
    Task::factory()->count(3)->for($second)->create();
    Task::factory()->count(4)->create();

    $this->actingAs($this->user)->get(route('dashboard'))
        ->assertSeeInOrder(['Projects', '2 / 10', 'Tasks', '5 / 100']);
});

test('the dashboard shows an empty state when there are no projects', function () {
    $this->actingAs($this->user)->get(route('dashboard'))
        ->assertSeeText('No projects yet.')
        ->assertSee('href="'.route('projects.create').'"', false);
});

test('the dashboard indicates the project limit when it is reached', function () {
    config()->set('tasknet.limits.projects', 1);
    Project::factory()->for($this->user)->create();

    $this->actingAs($this->user)->get(route('dashboard'))
        ->assertSeeText('This demo workspace has reached its limit of 1 projects.')
        ->assertDontSee('href="'.route('projects.create').'"', false);
});

test('the header shows the Demo ID and remaining days but never the synthetic email', function () {
    $response = $this->actingAs($this->user)->get(route('dashboard'));

    $response->assertSeeText([$this->user->demo_id, '30 days left', 'Log out'])
        ->assertDontSee($this->user->email)
        ->assertDontSee('@tasknet.local')
        ->assertSee('href="'.route('dashboard').'"', false);
});

test('remaining days are rounded up and pluralized correctly', function (int $hours, string $label) {
    $this->travelTo(Carbon::parse('2026-10-10 12:00:00'));
    $user = User::factory()->demo()->create(['expires_at' => now()->addHours($hours)]);

    $this->actingAs($user)->get(route('dashboard'))->assertSeeText($label);
})->with([
    'a few hours left' => [5, 'Expires today'],
    'exactly one day' => [24, '1 day left'],
    'just over one day' => [25, '2 days left'],
    'two days' => [48, '2 days left'],
]);

test('login redirects to the dashboard', function () {
    $user = User::factory()->demo()->create(['password' => 'correct-test-password']);

    $this->post(route('login.store'), ['demo_id' => $user->demo_id, 'password' => 'correct-test-password'])
        ->assertRedirect(route('dashboard'));
});

test('continuing from the credentials page redirects to the dashboard', function () {
    $this->post(route('demo.store'), ['terms' => '1']);

    $this->post(route('demo.credentials.continue'))->assertRedirect(route('dashboard'));
});

test('the homepage links authenticated users to the dashboard', function () {
    $this->actingAs($this->user)->get(route('home'))
        ->assertSeeText(['Projects', 'Open dashboard'])
        ->assertSee('href="'.route('dashboard').'"', false);
});

test('the header colors only the remaining time according to urgency', function (Closure $expiresAt, string $label, string $classes) {
    $this->travelTo(Carbon::parse('2026-10-10 12:00:00'));
    $user = User::factory()->demo()->create(['expires_at' => $expiresAt()]);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertSee('<span class="'.$classes.'">'.$label.'</span>', false)
        ->assertSee('<span class="font-semibold text-stone-700">'.$user->demo_id.'</span>', false)
        ->assertSee('<span aria-hidden="true">·</span>', false);
})->with([
    '30 days' => [fn () => now()->addDays(30), '30 days left', 'text-teal-700'],
    '8 days' => [fn () => now()->addDays(8), '8 days left', 'text-teal-700'],
    '7 days' => [fn () => now()->addDays(7), '7 days left', 'text-amber-700'],
    '4 days' => [fn () => now()->addDays(4), '4 days left', 'text-amber-700'],
    '3 days' => [fn () => now()->addDays(3), '3 days left', 'text-red-700'],
    '1 day' => [fn () => now()->addDay(), '1 day left', 'text-red-700'],
    'expires today' => [fn () => now()->addHours(3), 'Expires today', 'font-semibold text-red-700'],
]);

test('an actually expired workspace is still logged out instead of shown', function () {
    $user = User::factory()->demo()->expired()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('login'));
});
