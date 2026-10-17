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
        ->assertSeeText(['Workspace overview', 'A quick look at your projects and tasks.']);
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
        ->assertSeeInOrder(['Projects', '2 / 10', '8 remaining', 'Tasks', '5 / 100', '95 remaining']);
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

test('the dashboard charts task status and tasks per project from the workspace data', function () {
    $big = Project::factory()->for($this->user)->create(['name' => 'Big project']);
    $small = Project::factory()->for($this->user)->create(['name' => 'Small project']);
    Task::factory()->count(2)->for($big)->create(['status' => TaskStatus::Todo]);
    Task::factory()->for($big)->create(['status' => TaskStatus::InProgress]);
    Task::factory()->count(3)->for($small)->create(['status' => TaskStatus::Completed]);

    $this->actingAs($this->user)->get(route('dashboard'))
        ->assertSee('aria-label="Task status: 2 todo, 1 in progress, 3 completed"', false)
        ->assertSeeInOrder(['Task status', 'Todo', '2', 'In progress', '1', 'Completed', '3'])
        ->assertSeeInOrder(['Tasks by project', 'Big project', 'Small project']);
});

test('the dashboard charts do not include other workspaces and still render with empty states without tasks', function () {
    $other = Project::factory()->for(User::factory()->demo()->create())->create(['name' => 'Elsewhere']);
    Task::factory()->for($other)->create();
    Project::factory()->for($this->user)->create(['name' => 'Mine']);

    $this->actingAs($this->user)->get(route('dashboard'))
        ->assertSee('Task status')
        ->assertSee('Tasks by project')
        ->assertSee('No tasks yet. The status breakdown appears once you add tasks.')
        ->assertSee('No tasks yet. Bars appear once your projects have tasks.')
        ->assertDontSee('Elsewhere');
});

test('the dashboard charts render with an empty state in a workspace without projects', function () {
    $this->actingAs($this->user)->get(route('dashboard'))
        ->assertSee('Task status')
        ->assertSee('Tasks by project')
        ->assertSee('No projects yet.');
});

test('the summary cards show the remaining capacity and never go below zero', function () {
    config()->set('tasknet.limits.projects', 1);
    $project = Project::factory()->for($this->user)->create();
    Task::factory()->count(4)->for($project)->create();

    $this->actingAs($this->user)->get(route('dashboard'))
        ->assertSeeInOrder(['Projects', '1 / 1', '0 remaining', 'Tasks', '4 / 100', '96 remaining']);
});

test('the dashboard lists only the four most recently updated projects with a link to all of them', function () {
    foreach (range(1, 6) as $number) {
        Project::factory()->for($this->user)->create([
            'name' => "Project {$number}",
            'updated_at' => now()->subDays(10 - $number),
        ]);
    }

    $response = $this->actingAs($this->user)->get(route('dashboard'))
        ->assertSeeText('Recent projects')
        ->assertSeeText('4 of 6')
        ->assertSeeInOrder(['Project 6', 'Project 5', 'Project 4', 'Project 3'])
        ->assertSee('href="'.route('projects.index').'"', false)
        ->assertSeeText('View all projects');

    expect(substr_count($response->getContent(), 'View all projects'))->toBe(1);
    $response->assertDontSee('>Project 2</h2>', false)->assertDontSee('>Project 1</h2>', false);
});

test('the dashboard shows every project when there are four or fewer', function () {
    Project::factory()->count(3)->for($this->user)->create();

    $this->actingAs($this->user)->get(route('dashboard'))->assertSeeText('3 of 3');
});

test('the tasks-by-project chart shows the five busiest projects, busiest first', function () {
    foreach (range(1, 7) as $number) {
        $project = Project::factory()->for($this->user)->create(['name' => "Chart {$number}"]);
        Task::factory()->count($number)->for($project)->create();
    }

    $this->actingAs($this->user)->get(route('dashboard'))
        ->assertSeeInOrder(['Tasks by project', 'Chart 7', 'Chart 6', 'Chart 5', 'Chart 4', 'Chart 3', 'Recent projects'])
        ->assertSeeText('Task volume across your busiest projects.')
        ->assertDontSee('title="Chart 2"', false)
        ->assertDontSee('title="Chart 1"', false);
});

test('the chart subtitle stays simple with five or fewer projects', function () {
    $project = Project::factory()->for($this->user)->create();
    Task::factory()->for($project)->create();

    $this->actingAs($this->user)->get(route('dashboard'))
        ->assertSeeText('Task volume across your current projects.');
});

/**
 * Get the project rows the dashboard embeds for the chart search.
 *
 * @return array<int, array{name: string, description: string, tasks: int}>
 */
function chartSearchData(string $html): array
{
    preg_match('#<script type="application/json" data-project-data>(.*?)</script>#s', $html, $matches);

    return json_decode(html_entity_decode($matches[1]), true);
}

test('the chart lists at most five projects by default, busiest first, and breaks ties by recent update', function () {
    foreach ([['Busiest', 9, 5], ['Tied old', 3, 4], ['Tied new', 3, 1], ['Two', 2, 2], ['One', 1, 3], ['Zero A', 0, 6], ['Zero B', 0, 7]] as [$name, $tasks, $daysAgo]) {
        $project = Project::factory()->for($this->user)->create(['name' => $name, 'updated_at' => now()->subDays($daysAgo)]);
        Task::factory()->count($tasks)->for($project)->create();
    }

    $html = $this->actingAs($this->user)->get(route('dashboard'))
        ->assertSeeInOrder(['title="Busiest"', 'title="Tied new"', 'title="Tied old"', 'title="Two"', 'title="One"'], false)
        ->assertDontSee('title="Zero A"', false)
        ->assertDontSee('title="Zero B"', false)
        ->getContent();

    expect(substr_count($html, 'class="truncate font-semibold" title='))->toBe(5);
});

test('the chart search data contains every project, including those without tasks', function () {
    $busy = Project::factory()->for($this->user)->create(['name' => 'Busy one', 'description' => 'Has work']);
    Task::factory()->count(2)->for($busy)->create();
    foreach (range(1, 6) as $number) {
        Project::factory()->for($this->user)->create(['name' => "Quiet {$number}"]);
    }
    Project::factory()->for(User::factory()->demo()->create())->create(['name' => 'Other workspace']);

    $data = chartSearchData($this->actingAs($this->user)->get(route('dashboard'))->getContent());

    expect($data)->toHaveCount(7)
        ->and($data[0])->toBe(['name' => 'Busy one', 'description' => 'Has work', 'tasks' => 2])
        ->and(collect($data)->pluck('name'))->toContain('Quiet 6')->not->toContain('Other workspace')
        ->and(collect($data)->firstWhere('name', 'Quiet 6')['tasks'])->toBe(0);
});

test('the chart card offers a labelled search with a clear button and a no-results message', function () {
    $project = Project::factory()->for($this->user)->create();
    Task::factory()->for($project)->create();

    $this->actingAs($this->user)->get(route('dashboard'))
        ->assertSee('<label for="project-chart-search" class="sr-only">Search projects</label>', false)
        ->assertSee('placeholder="Search projects..."', false)
        ->assertSee('aria-label="Clear search"', false)
        ->assertSeeText('No matching projects');
});

test('the chart data reflects new tasks after a refresh', function () {
    $project = Project::factory()->for($this->user)->create(['name' => 'Live']);
    Task::factory()->for($project)->create();

    $first = chartSearchData($this->actingAs($this->user)->get(route('dashboard'))->getContent());
    Task::factory()->count(2)->for($project)->create();
    $second = chartSearchData($this->actingAs($this->user)->get(route('dashboard'))->getContent());

    expect($first[0]['tasks'])->toBe(1)->and($second[0]['tasks'])->toBe(3);
});
