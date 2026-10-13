<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

test('an authenticated expired demo user is logged out by the expiry middleware', function () {
    $user = User::factory()->demo()->expired()->create();

    $response = $this->actingAs($user)->get(route('home'));

    $response->assertRedirect(route('login'))
        ->assertSessionHas('status', 'Your demo workspace has expired. Start a new demo to keep testing.');
    $this->assertGuest();
});

test('a session is logged out once its workspace expires mid-session', function () {
    $user = User::factory()->demo()->create();
    $this->actingAs($user)->get(route('home'))->assertOk();

    $this->travel(config('tasknet.demo.lifetime_days') + 1)->days();

    $this->get(route('home'))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('an active demo user remains authenticated', function () {
    $user = User::factory()->demo()->create();

    $this->actingAs($user)->get(route('home'))->assertOk()->assertSeeText('Log out');
    $this->assertAuthenticatedAs($user);
});

test('non-demo users are never expired by the middleware', function () {
    $user = User::factory()->create(['expires_at' => now()->subDay()]);

    $this->actingAs($user)->get(route('home'))->assertOk();
    $this->assertAuthenticatedAs($user);
});

test('the cleanup command deletes expired demo users and reports the count', function () {
    User::factory()->demo()->expired()->count(2)->create();

    $this->artisan('tasknet:cleanup-demos')
        ->expectsOutputToContain('Deleted 2 expired demo workspace(s).')
        ->assertSuccessful();

    expect(User::count())->toBe(0);
});

test('the cleanup command deletes workspaces that expire exactly now', function () {
    $this->freezeTime();
    User::factory()->demo()->create(['expires_at' => now()]);

    $this->artisan('tasknet:cleanup-demos')->assertSuccessful();

    expect(User::count())->toBe(0);
});

test('the cleanup command preserves active demo users', function () {
    $active = User::factory()->demo()->create();

    $this->artisan('tasknet:cleanup-demos')
        ->expectsOutputToContain('Deleted 0 expired demo workspace(s).')
        ->assertSuccessful();

    expect($active->fresh())->not->toBeNull();
});

test('the cleanup command preserves non-demo users even with a past expiry', function () {
    $user = User::factory()->create(['expires_at' => now()->subYear()]);

    $this->artisan('tasknet:cleanup-demos')->assertSuccessful();

    expect($user->fresh())->not->toBeNull();
});

test('the cleanup command is safe to run repeatedly', function () {
    User::factory()->demo()->expired()->create();

    $this->artisan('tasknet:cleanup-demos')->expectsOutputToContain('Deleted 1 ');
    $this->artisan('tasknet:cleanup-demos')->expectsOutputToContain('Deleted 0 ')->assertSuccessful();
});

test('the workspace limits are defined centrally', function () {
    expect(config('tasknet.limits.projects'))->toBe(10)
        ->and(config('tasknet.limits.tasks'))->toBe(100);
});
