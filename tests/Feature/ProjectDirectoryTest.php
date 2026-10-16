<?php

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->user = User::factory()->demo()->create();
    config()->set('tasknet.limits.projects', 100);
});

/**
 * Get the project names shown in the directory, in order.
 *
 * @return array<int, string>
 */
function listedProjects(array $query = []): array
{
    $content = test()->actingAs(test()->user)->get(route('projects.index', $query))->assertOk()->getContent();
    preg_match_all('#<a href="[^"]*/projects/\d+" title="([^"]*)"#', $content, $matches);

    return array_map('html_entity_decode', $matches[1]);
}

test('guests are sent to log in', function () {
    $this->get(route('projects.index'))->assertRedirect(route('login'));
});

test('the directory lists the workspace projects with their counts', function () {
    $project = Project::factory()->for($this->user)->create(['name' => 'Roadmap', 'description' => 'Plans for next year']);
    Task::factory()->count(2)->for($project)->create(['status' => TaskStatus::Completed]);
    Task::factory()->for($project)->create(['status' => TaskStatus::Todo]);

    $this->actingAs($this->user)->get(route('projects.index'))
        ->assertSeeText(['Roadmap', 'Plans for next year', 'Tasks', 'Completed', 'Updated'])
        ->assertSee('href="'.route('projects.show', $project).'"', false)
        ->assertSeeInOrder(['Roadmap', '3', '2']);
});

test('the Projects navigation item leads to the directory', function () {
    $this->actingAs($this->user)->get(route('dashboard'))
        ->assertSee('href="'.route('projects.index').'"', false);
});

test('search matches the project name', function () {
    Project::factory()->for($this->user)->create(['name' => 'TaskNet launch']);
    Project::factory()->for($this->user)->create(['name' => 'Garden']);

    expect(listedProjects(['q' => 'tasknet']))->toBe(['TaskNet launch']);
});

test('search matches the project description', function () {
    Project::factory()->for($this->user)->create(['name' => 'Alpha', 'description' => 'Contains the keyword hummingbird']);
    Project::factory()->for($this->user)->create(['name' => 'Beta', 'description' => 'Nothing relevant']);

    expect(listedProjects(['q' => 'HUMMINGBIRD']))->toBe(['Alpha']);
});

test('search treats wildcard characters literally', function () {
    Project::factory()->for($this->user)->create(['name' => '100% done']);
    Project::factory()->for($this->user)->create(['name' => 'Another']);

    expect(listedProjects(['q' => '%']))->toBe(['100% done']);
});

test('search never exposes another workspace', function () {
    $other = User::factory()->demo()->create();
    Project::factory()->for($other)->create(['name' => 'Secret plans', 'description' => 'secret']);
    Project::factory()->for($this->user)->create(['name' => 'Mine']);

    expect(listedProjects(['q' => 'secret']))->toBe([]);
    $this->actingAs($this->user)->get(route('projects.index', ['q' => 'secret']))->assertDontSee('Secret plans');
});

test('the search term stays in the input', function () {
    Project::factory()->for($this->user)->create(['name' => 'Alpha']);

    $this->actingAs($this->user)->get(route('projects.index', ['q' => 'alp']))
        ->assertSee('value="alp"', false);
});

test('projects can be sorted by recent update, name and task count', function () {
    Project::factory()->for($this->user)->create(['name' => 'Bravo', 'updated_at' => now()->subDays(3)]);
    $new = Project::factory()->for($this->user)->create(['name' => 'Charlie', 'updated_at' => now()->subDay()]);
    $busy = Project::factory()->for($this->user)->create(['name' => 'Alpha', 'updated_at' => now()->subDays(5)]);
    Task::factory()->count(3)->for($busy)->create();
    Task::factory()->count(1)->for($new)->create();

    expect(listedProjects())->toBe(['Charlie', 'Bravo', 'Alpha'])
        ->and(listedProjects(['sort' => 'updated']))->toBe(['Charlie', 'Bravo', 'Alpha'])
        ->and(listedProjects(['sort' => 'name']))->toBe(['Alpha', 'Bravo', 'Charlie'])
        ->and(listedProjects(['sort' => 'tasks']))->toBe(['Alpha', 'Charlie', 'Bravo']);
});

test('an unknown sort falls back to recently updated', function () {
    Project::factory()->for($this->user)->create(['name' => 'Old', 'updated_at' => now()->subDay()]);
    Project::factory()->for($this->user)->create(['name' => 'New', 'updated_at' => now()]);

    expect(listedProjects(['sort' => 'DROP TABLE']))->toBe(['New', 'Old']);
});

test('the directory shows 20 projects per page and keeps the query on page links', function () {
    foreach (range(1, 25) as $number) {
        Project::factory()->for($this->user)->create(['name' => sprintf('Match %02d', $number)]);
    }

    expect(listedProjects(['sort' => 'name', 'q' => 'match']))->toHaveCount(20);

    $page = $this->actingAs($this->user)->get(route('projects.index', ['sort' => 'name', 'q' => 'match']))
        ->assertSeeText('Page 1 of 2');
    $content = html_entity_decode($page->getContent());

    expect($content)->toContain('rel="next"')
        ->and($content)->toContain(route('projects.index', ['sort' => 'name', 'q' => 'match', 'page' => 2]));

    expect(listedProjects(['sort' => 'name', 'q' => 'match', 'page' => 2]))->toHaveCount(5);
});

test('there is no pagination for a single page', function () {
    Project::factory()->count(3)->for($this->user)->create();

    $this->actingAs($this->user)->get(route('projects.index'))->assertDontSee('aria-label="Pagination"', false);
});

test('an empty workspace shows a friendly empty state', function () {
    $this->actingAs($this->user)->get(route('projects.index'))
        ->assertSeeText('No projects yet.')
        ->assertSee('href="'.route('projects.create').'"', false)
        ->assertDontSee('role="search"', false);
});

test('a search without results explains it and offers to clear it', function () {
    Project::factory()->for($this->user)->create(['name' => 'Alpha']);

    $this->actingAs($this->user)->get(route('projects.index', ['q' => 'zzz', 'sort' => 'name']))
        ->assertSeeText('No projects matched “zzz”.')
        ->assertSeeText('Clear search')
        ->assertSee('href="'.route('projects.index', ['sort' => 'name']).'"', false);
});

test('long project names are truncated visually but keep the full name available', function () {
    $name = trim(str_repeat('Very long project name ', 8));
    Project::factory()->for($this->user)->create(['name' => $name]);

    $this->actingAs($this->user)->get(route('projects.index'))
        ->assertSee('title="'.$name.'"', false)
        ->assertSee('truncate', false);
});
