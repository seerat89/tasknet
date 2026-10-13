<?php

use App\Models\User;

beforeEach(function () {
    $this->withoutVite();
});

test('guests see the TaskNet homepage with demo calls to action', function () {
    $response = $this->get(route('home'));

    $response->assertSeeText([
        'Connect your AI agent.',
        'Test MCP.',
        'TaskNet is a temporary project and task sandbox for testing how AI agents interact with an application through MCP.',
        'Start demo',
        'Sign in',
        'About',
        'Privacy',
    ])->assertSee('href="'.route('demo.create').'"', false)
        ->assertSee('href="'.route('login').'"', false)
        ->assertSee('src="'.asset('brand/tasknet-logo-color.svg').'"', false)
        ->assertSee('src="'.asset('brand/tasknet-mark.svg').'"', false)
        ->assertDontSeeText(['Log out', 'Get started', 'Demo login', 'Simple projects.', 'Connect your AI agent through MCP']);
});

test('the homepage states that demo workspaces are temporary', function () {
    $this->get(route('home'))
        ->assertSeeText('Temporary workspace · automatically deleted after 30 days');
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

test('authenticated demo users see logout and no email verification actions', function () {
    $user = User::factory()->demo()->make();

    $response = $this->actingAs($user)->get(route('home'));

    $response->assertSeeText('Log out')
        ->assertSee('action="'.route('logout').'"', false)
        ->assertSee('method="POST"', false)
        ->assertSee('name="_token"', false)
        ->assertDontSee('href="'.route('login').'"', false)
        ->assertDontSee('href="'.route('demo.create').'"', false)
        ->assertDontSeeText(['Verify email', 'Start demo', 'Sign in'])
        ->assertDontSee('/email/verify', false);
});
