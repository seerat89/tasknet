<?php

use App\Models\User;

beforeEach(function () {
    $this->withoutVite();
});

test('guests see the TaskNet homepage with registration and login links', function () {
    $response = $this->get(route('home'));

    $response->assertSeeText([
        'Simple projects.',
        'Clear tasks.',
        'TaskNet helps you organize your work with projects and tasks — simple, clean and focused.',
        'Connect your AI agent through MCP to manage your projects and tasks.',
        'Get started',
        'Log in',
    ])->assertSee('href="'.route('register').'"', false)
        ->assertSee('href="'.route('login').'"', false)
        ->assertSee('src="'.asset('brand/tasknet-logo-color.svg').'"', false)
        ->assertSee('src="'.asset('brand/tasknet-mark.svg').'"', false)
        ->assertDontSeeText('Log out');
});

test('the homepage keeps its quiet footer and decoration without an application preview', function () {
    $response = $this->get(route('home'));

    $response->assertSeeText('TaskNet · Taskboard for AI Agents')
        ->assertSee('aria-hidden="true" inert', false)
        ->assertDontSeeText([
            'Website Redesign',
            'Design landing page',
            'Set up project structure',
            'Implement homepage',
            'Write content',
            'Deploy to production',
            'Add task',
        ]);
});

test('verified users see logout instead of guest calls to action', function () {
    $user = User::factory()->make();

    $response = $this->actingAs($user)->get(route('home'));

    $response->assertSeeText('Log out')
        ->assertSee('action="'.route('logout').'"', false)
        ->assertSee('method="POST"', false)
        ->assertSee('name="_token"', false)
        ->assertDontSee('href="'.route('login').'"', false)
        ->assertDontSee('href="'.route('register').'"', false)
        ->assertDontSee('href="'.route('verification.notice').'"', false)
        ->assertDontSeeText('Get started');
});

test('unverified users can reach email verification from the homepage', function () {
    $user = User::factory()->unverified()->make();

    $response = $this->actingAs($user)->get(route('home'));

    $response->assertSeeText(['Verify email', 'Log out'])
        ->assertSee('href="'.route('verification.notice').'"', false)
        ->assertDontSee('href="'.route('login').'"', false)
        ->assertDontSee('href="'.route('register').'"', false);
});
