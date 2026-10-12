<?php

use App\Actions\DeleteDemoWorkspace;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

/**
 * Give a user two projects with tasks and two browser sessions.
 */
function populateWorkspace(User $user): void
{
    $projects = Project::factory()->count(2)->for($user)->create();

    foreach ($projects as $project) {
        Task::factory()->count(2)->for($project)->create();
    }

    foreach (range(1, 2) as $ignored) {
        DB::table('sessions')->insert([
            'id' => Str::random(40),
            'user_id' => $user->id,
            'payload' => '',
            'last_activity' => time(),
        ]);
    }
}

/**
 * Count the rows of every table that holds data belonging to a user.
 *
 * @return array<string, int>
 */
function workspaceFootprint(User $user): array
{
    $accessTokenIds = Passport::token()->newQuery()->where('user_id', $user->id)->pluck('id');

    return [
        'users' => User::whereKey($user->id)->count(),
        'projects' => Project::where('user_id', $user->id)->count(),
        'tasks' => Task::whereIn('project_id', Project::where('user_id', $user->id)->select('id'))->count(),
        'sessions' => DB::table('sessions')->where('user_id', $user->id)->count(),
        'access_tokens' => $accessTokenIds->count(),
        'refresh_tokens' => Passport::refreshToken()->newQuery()->whereIn('access_token_id', $accessTokenIds)->count(),
        'auth_codes' => Passport::authCode()->newQuery()->where('user_id', $user->id)->count(),
    ];
}

function deleteWorkspaceAs(User $user, ?string $confirmation = null)
{
    return test()->actingAs($user)->delete(route('workspace.destroy'), ['confirmation' => $confirmation ?? $user->demo_id]);
}

// --- Central deletion ---------------------------------------------------------

test('deleting a workspace removes the user, projects, tasks, sessions and OAuth records', function () {
    useTemporaryPassportKeys();
    $user = User::factory()->demo()->create(['password' => 'secret-password']);
    $other = User::factory()->demo()->create();
    populateWorkspace($user);
    populateWorkspace($other);

    $tokens = issueOAuthTokens($user);
    $otherTokens = issueOAuthTokens($other);

    $before = workspaceFootprint($user);
    expect($before)->each->toBeGreaterThan(0);
    $otherBefore = workspaceFootprint($other);

    deleteWorkspaceAs($user)->assertRedirect(route('home'))->assertSessionHas('success', 'Workspace deleted.');

    expect(workspaceFootprint($user))->each->toBe(0)
        ->and(workspaceFootprint($other))->toBe($otherBefore);

    auth()->forgetGuards();
    $this->flushSession();

    // The old access token no longer works, but another workspace's token does.
    $this->withToken($tokens['access_token'])
        ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])
        ->assertUnauthorized();

    auth()->forgetGuards();
    $this->withToken($otherTokens['access_token'])
        ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])
        ->assertOk();

    // The refresh token no longer works.
    $refresh = $this->postJson('/oauth/token', [
        'grant_type' => 'refresh_token',
        'refresh_token' => $tokens['refresh_token'],
        'client_id' => $tokens['client_id'],
    ]);
    expect($refresh->getStatusCode())->toBeGreaterThanOrEqual(400);

    // The deleted Demo ID can no longer sign in.
    $this->post(route('login.store'), ['demo_id' => $user->demo_id, 'password' => 'secret-password'])
        ->assertSessionHasErrors();
    // The MCP requests above switch the default guard to the API guard, so name the web guard.
    $this->assertGuest('web');
});

test('the registered OAuth client is left in place because it is shared', function () {
    useTemporaryPassportKeys();
    $user = User::factory()->demo()->create();
    $tokens = issueOAuthTokens($user);

    deleteWorkspaceAs($user)->assertRedirect(route('home'));

    expect(Passport::client()->newQuery()->whereKey($tokens['client_id'])->exists())->toBeTrue();
});

test('deleting a workspace that is already gone is harmless', function () {
    $user = User::factory()->demo()->create();
    populateWorkspace($user);

    app(DeleteDemoWorkspace::class)->delete($user);
    app(DeleteDemoWorkspace::class)->delete($user);

    expect(workspaceFootprint($user))->each->toBe(0);
});

test('only demo workspaces can be deleted', function () {
    $user = User::factory()->create();

    expect(fn () => app(DeleteDemoWorkspace::class)->delete($user))->toThrow(InvalidArgumentException::class);
    expect($user->fresh())->not->toBeNull();
});

test('manual deletion leaves the browser signed out with a fresh session', function () {
    $user = User::factory()->demo()->create();

    $response = deleteWorkspaceAs($user)->assertRedirect(route('home'));

    $this->assertGuest();
    expect($response->headers->get('Location'))->not->toContain($user->demo_id);
    $this->get(route('home'))->assertOk()->assertDontSee($user->demo_id);
});

// --- Security -----------------------------------------------------------------

test('guests cannot delete a workspace', function () {
    $user = User::factory()->demo()->create();

    $this->delete(route('workspace.destroy'), ['confirmation' => $user->demo_id])->assertRedirect(route('login'));

    expect($user->fresh())->not->toBeNull();
});

test('a wrong or missing confirmation is rejected and nothing is deleted', function (?string $confirmation) {
    $user = User::factory()->demo()->create();
    populateWorkspace($user);

    $this->actingAs($user)->from(route('settings'))
        ->delete(route('workspace.destroy'), $confirmation === null ? [] : ['confirmation' => $confirmation])
        ->assertRedirect(route('settings'))
        ->assertSessionHasErrors('confirmation');

    $this->assertAuthenticatedAs($user);
    expect(workspaceFootprint($user)['users'])->toBe(1)
        ->and(workspaceFootprint($user)['projects'])->toBe(2);
})->with([
    'missing' => [null],
    'empty' => [''],
    'wrong id' => ['TN-WRONG1'],
    'password instead' => ['secret-password'],
]);

test('another workspace’s Demo ID cannot be used to delete either workspace', function () {
    $user = User::factory()->demo()->create();
    $other = User::factory()->demo()->create();

    deleteWorkspaceAs($user, $other->demo_id)->assertSessionHasErrors('confirmation');

    expect($user->fresh())->not->toBeNull()->and($other->fresh())->not->toBeNull();
});

test('the correct Demo ID deletes only the signed-in workspace and no password is needed', function () {
    $user = User::factory()->demo()->create();
    $other = User::factory()->demo()->create();
    populateWorkspace($other);

    deleteWorkspaceAs($user, '  '.$user->demo_id.'  ')->assertRedirect(route('home'));

    expect($user->fresh())->toBeNull()
        ->and($other->fresh())->not->toBeNull()
        ->and(workspaceFootprint($other)['projects'])->toBe(2);
});

test('deletion cannot be triggered with GET', function () {
    $user = User::factory()->demo()->create();

    $this->actingAs($user)->get('/settings/workspace')->assertStatus(405);

    expect($user->fresh())->not->toBeNull();
});

test('a typed Demo ID never selects which workspace is deleted', function () {
    $user = User::factory()->demo()->create();
    $other = User::factory()->demo()->create();

    $this->actingAs($user)->delete(route('workspace.destroy'), [
        'confirmation' => $user->demo_id,
        'user_id' => $other->id,
        'demo_id' => $other->demo_id,
    ])->assertRedirect(route('home'));

    expect($user->fresh())->toBeNull()->and($other->fresh())->not->toBeNull();
});

test('an expired workspace cannot use the delete route', function () {
    $expired = User::factory()->demo()->expired()->create();

    $this->actingAs($expired)->delete(route('workspace.destroy'), ['confirmation' => $expired->demo_id])
        ->assertRedirect(route('login'));

    expect($expired->fresh())->not->toBeNull();
});

// --- Automatic cleanup --------------------------------------------------------

test('the cleanup command removes expired workspaces completely and keeps active ones', function () {
    useTemporaryPassportKeys();
    $expired = User::factory()->demo()->create();
    $active = User::factory()->demo()->create();
    populateWorkspace($expired);
    populateWorkspace($active);

    $expiredTokens = issueOAuthTokens($expired);
    $activeTokens = issueOAuthTokens($active);
    $activeBefore = workspaceFootprint($active);

    $expired->forceFill(['expires_at' => now()->subMinute()])->save();

    $this->artisan('tasknet:cleanup-demos')
        ->expectsOutputToContain('Deleted 1 expired demo workspace(s).')
        ->assertSuccessful();

    expect(workspaceFootprint($expired))->each->toBe(0)
        ->and(workspaceFootprint($active))->toBe($activeBefore);

    auth()->forgetGuards();
    $this->withToken($expiredTokens['access_token'])
        ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])
        ->assertUnauthorized();

    auth()->forgetGuards();
    $this->withToken($activeTokens['access_token'])
        ->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])
        ->assertOk();

    $this->artisan('tasknet:cleanup-demos')
        ->expectsOutputToContain('Deleted 0 expired demo workspace(s).')
        ->assertSuccessful();
});

test('the cleanup command keeps going and reports failures', function () {
    $first = User::factory()->demo()->expired()->create();
    $second = User::factory()->demo()->expired()->create();

    $this->mock(DeleteDemoWorkspace::class, function ($mock) use ($first, $second) {
        $mock->shouldReceive('delete')->once()->withArgs(fn (User $user) => $user->is($first))->andThrow(new RuntimeException('boom'));
        $mock->shouldReceive('delete')->once()->withArgs(fn (User $user) => $user->is($second));
    });

    $this->artisan('tasknet:cleanup-demos')
        ->expectsOutputToContain('Deleted 1 expired demo workspace(s).')
        ->expectsOutputToContain('Failed to delete 1 expired demo workspace(s).')
        ->assertFailed();
});

// --- One shared deletion path -------------------------------------------------

test('manual deletion and automatic cleanup both use the same deletion action', function () {
    $manual = User::factory()->demo()->create();
    $expired = User::factory()->demo()->expired()->create();
    $deleted = [];

    $this->mock(DeleteDemoWorkspace::class, function ($mock) use (&$deleted) {
        $mock->shouldReceive('delete')->twice()->andReturnUsing(function (User $user) use (&$deleted) {
            $deleted[] = $user->id;
        });
    });

    deleteWorkspaceAs($manual)->assertRedirect(route('home'));
    $this->artisan('tasknet:cleanup-demos')->assertSuccessful();

    expect($deleted)->toBe([$manual->id, $expired->id]);
});

// --- Settings page ------------------------------------------------------------

test('guests cannot open the workspace settings', function () {
    $this->get(route('settings'))->assertRedirect(route('login'));
});

test('the settings page shows the workspace and the delete control', function () {
    $user = User::factory()->demo()->create();

    $response = $this->actingAs($user)->get(route('settings'))
        ->assertOk()
        ->assertSeeText(['Workspace settings', 'Demo ID', $user->demo_id, 'Expires', '30 days left', 'Danger zone', 'Delete workspace', 'Delete workspace permanently?', 'This action cannot be undone.'])
        ->assertSee('action="'.route('workspace.destroy').'"', false)
        ->assertSee('name="_token"', false)
        ->assertSee('name="_method" value="DELETE"', false)
        ->assertSee('name="confirmation"', false)
        ->assertDontSee($user->email)
        ->assertDontSee('name="password"', false);

    expect($response->getContent())->toMatch('/<input id="confirmation"[^>]*\srequired/');
});

test('Settings appears in the signed-in navigation only', function () {
    $this->get(route('about'))->assertDontSee('href="'.route('settings').'"', false);

    $this->actingAs(User::factory()->demo()->create())->get(route('about'))
        ->assertSee('href="'.route('settings').'"', false)
        ->assertSeeInOrder(['Projects', 'About', 'Settings', 'Log out']);
});

test('a failed confirmation reopens the delete form with the error', function () {
    $user = User::factory()->demo()->create();

    $response = $this->actingAs($user)->from(route('settings'))
        ->followingRedirects()
        ->delete(route('workspace.destroy'), ['confirmation' => 'nope'])
        ->assertOk()
        ->assertSee('The Demo ID you typed does not match this workspace.');

    expect($response->getContent())->toMatch('/<details[^>]*\sopen/');
});

test('a successful deletion lands on the home page with a confirmation', function () {
    $user = User::factory()->demo()->create();

    $this->actingAs($user)->followingRedirects()
        ->delete(route('workspace.destroy'), ['confirmation' => $user->demo_id])
        ->assertOk()
        ->assertSee('Workspace deleted.')
        ->assertSee('Start demo')
        ->assertDontSee($user->demo_id);
});
