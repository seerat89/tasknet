<?php

use App\Actions\CreateDemoWorkspace;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

test('guests can start a demo workspace and are sent to the credential handoff', function () {
    $response = $this->post(route('demo.store'));

    $response->assertRedirect(route('demo.credentials'));
    expect(User::count())->toBe(1);
});

test('the created user is a demo workspace with a friendly Demo ID', function () {
    $this->post(route('demo.store'));

    $user = User::sole();

    expect($user->is_demo)->toBeTrue()
        ->and($user->name)->toBe('Demo '.substr($user->demo_id, 3))
        ->and($user->demo_id)->toMatch('/^TN-[A-Z2-9]{6}$/');
});

test('every Demo ID is unique', function () {
    $demoIds = collect(range(1, 25))->map(fn () => app(CreateDemoWorkspace::class)->create()['user']->demo_id);

    expect($demoIds->unique())->toHaveCount(25);
    expect(fn () => User::factory()->demo()->create(['demo_id' => $demoIds->first()]))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('a synthetic internal email is created so visitors never provide one', function () {
    $this->post(route('demo.store'))->assertSessionHasNoErrors();

    $user = User::sole();

    expect($user->email)->toBe(strtolower($user->demo_id).'@tasknet.local');
});

test('the workspace expires thirty days after creation', function () {
    $this->freezeTime();

    $this->post(route('demo.store'));

    $expected = now()->addDays(30);

    expect(User::sole()->expires_at->between($expected->copy()->subSeconds(5), $expected->copy()->addSeconds(5)))->toBeTrue()
        ->and(config('tasknet.demo.lifetime_days'))->toBe(30);
});

test('only the password hash is stored and it matches the generated password', function () {
    $this->post(route('demo.store'));

    $user = User::sole();
    $password = Crypt::decryptString(session('demo_credentials.password'));

    expect(strlen($password))->toBe(20)
        ->and($user->password)->not->toBe($password)
        ->and(Hash::check($password, $user->password))->toBeTrue();
    $this->assertDatabaseMissing('users', ['password' => $password]);
    expect(json_encode(User::query()->get()->makeVisible('password')->toArray()))->not->toContain($password);
});

test('the new demo user is authenticated', function () {
    $this->post(route('demo.store'));

    $this->assertAuthenticatedAs(User::sole());
});

test('authenticated users cannot start another demo workspace', function () {
    $user = User::factory()->demo()->create();

    $this->actingAs($user)->post(route('demo.store'))->assertRedirect(route('home'));

    expect(User::count())->toBe(1);
});

test('demo workspaces cannot be created through GET', function () {
    $this->get('/demo')->assertStatus(405);

    expect(User::count())->toBe(0);
});

test('starting demo workspaces is rate limited per visitor', function () {
    foreach (range(1, 10) as $attempt) {
        auth()->logout();
        $this->post(route('demo.store'))->assertRedirect(route('demo.credentials'));
        $this->app['auth']->forgetGuards();
        $this->flushSession();
    }

    $this->post(route('demo.store'))->assertTooManyRequests();
    expect(User::count())->toBe(10);
});
