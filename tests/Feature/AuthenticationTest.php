<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

test('guests can open the branded demo login form', function () {
    $response = $this->get(route('login'));

    $response->assertSeeText(['TaskNet', 'Taskboard for AI Agents', 'Log in', 'Demo ID', 'Password', 'Remember me'])
        ->assertSee('action="'.route('login.store').'"', false)
        ->assertSee('method="POST"', false)
        ->assertSee('name="_token"', false)
        ->assertSee('name="demo_id"', false)
        ->assertSee('for="demo_id"', false)
        ->assertSee('name="password"', false)
        ->assertSee('name="remember"', false)
        ->assertDontSee('name="email"', false)
        ->assertDontSeeText(['Email', 'Forgot password', 'Create an account'])
        ->assertSeeText('public MCP testing environment');
});

test('returning demo users can log in with their Demo ID and password', function () {
    $user = User::factory()->demo()->create(['password' => 'correct-test-password']);

    $response = $this->post(route('login.store'), [
        'demo_id' => $user->demo_id,
        'password' => 'correct-test-password',
        'remember' => '1',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
    $this->get(route('home'))->assertSeeText('Log out');
});

test('the Demo ID is accepted in any letter case', function (string $submittedDemoId) {
    $user = User::factory()->demo()->create(['demo_id' => 'TN-AB3CD4', 'password' => 'correct-test-password']);

    $this->post(route('login.store'), [
        'demo_id' => '  '.$submittedDemoId.' ',
        'password' => 'correct-test-password',
    ])->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($user);
})->with([
    'lowercase' => ['tn-ab3cd4'],
    'uppercase' => ['TN-AB3CD4'],
    'mixed case' => ['Tn-aB3cD4'],
]);

test('wrong passwords, unknown Demo IDs, expired workspaces and non-demo users share one generic failure', function (Closure $attempt) {
    $credentials = $attempt();

    $response = $this->from(route('login'))->post(route('login.store'), $credentials);

    $response->assertRedirect(route('login'))
        ->assertSessionHasErrors(['demo_id' => __('auth.failed')])
        ->assertSessionMissing('_old_input.password');
    expect(session('errors')->getBag('default')->all())->toBe([__('auth.failed')]);
    $this->assertGuest();
})->with([
    'wrong password' => [function () {
        $user = User::factory()->demo()->create(['password' => 'correct-test-password']);

        return ['demo_id' => $user->demo_id, 'password' => 'incorrect-test-password'];
    }],
    'unknown Demo ID' => [fn () => ['demo_id' => 'TN-NOSUCH', 'password' => 'correct-test-password']],
    'expired workspace' => [function () {
        $user = User::factory()->demo()->expired()->create(['password' => 'correct-test-password']);

        return ['demo_id' => $user->demo_id, 'password' => 'correct-test-password'];
    }],
    'non-demo user' => [function () {
        $user = User::factory()->create(['demo_id' => 'TN-PLAIN2', 'password' => 'correct-test-password']);

        return ['demo_id' => $user->demo_id, 'password' => 'correct-test-password'];
    }],
]);

test('a workspace that expires after creation can no longer log in', function () {
    $user = User::factory()->demo()->create(['password' => 'correct-test-password']);

    $this->travel(config('tasknet.demo.lifetime_days') + 1)->days();

    $this->from(route('login'))->post(route('login.store'), [
        'demo_id' => $user->demo_id,
        'password' => 'correct-test-password',
    ])->assertSessionHasErrors(['demo_id' => __('auth.failed')]);
    $this->assertGuest();
});

test('the login form shows the failure and keeps only the safe input', function () {
    $user = User::factory()->demo()->create();

    $this->from(route('login'))->post(route('login.store'), [
        'demo_id' => $user->demo_id,
        'password' => 'incorrect-test-password',
    ]);

    $this->withCookie(config('session.cookie'), session()->getId())->get(route('login'))
        ->assertSeeText(__('auth.failed'))
        ->assertSee('aria-describedby="demo_id-error"', false)
        ->assertSee('aria-invalid="true"', false)
        ->assertSee('value="'.$user->demo_id.'"', false)
        ->assertDontSee('incorrect-test-password');
});

test('login attempts are throttled per normalized Demo ID', function () {
    $user = User::factory()->demo()->create(['password' => 'correct-test-password']);

    foreach (range(1, 5) as $attempt) {
        $this->post(route('login.store'), [
            'demo_id' => $attempt % 2 ? strtolower($user->demo_id) : $user->demo_id,
            'password' => 'incorrect-test-password',
        ])->assertSessionHasErrors('demo_id');
    }

    $this->post(route('login.store'), [
        'demo_id' => $user->demo_id,
        'password' => 'correct-test-password',
    ])->assertTooManyRequests();
    $this->assertGuest();
});

test('demo users can log out', function () {
    $user = User::factory()->demo()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('home'));
    $this->assertGuest();
});

test('authenticated users are kept away from the login form', function () {
    $user = User::factory()->demo()->create();

    $this->actingAs($user)->get(route('login'))->assertRedirect(route('dashboard'));
});

test('registration, password reset, email verification and other obsolete flows are unavailable', function (string $method, string $uri) {
    $this->{$method}($uri)->assertNotFound();
})->with([
    'register page' => ['get', '/register'],
    'register submit' => ['post', '/register'],
    'forgot password page' => ['get', '/forgot-password'],
    'forgot password submit' => ['post', '/forgot-password'],
    'reset password page' => ['get', '/reset-password/some-token'],
    'reset password submit' => ['post', '/reset-password'],
    'email verification notice' => ['get', '/email/verify'],
    'email verification link' => ['get', '/email/verify/1/hash'],
    'email verification resend' => ['post', '/email/verification-notification'],
    'two-factor challenge' => ['get', '/two-factor-challenge'],
    'passkey login options' => ['get', '/passkeys/login/options'],
]);
