<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

test('about, privacy and terms are public', function (string $route) {
    $this->get(route($route))->assertOk();
})->with(['about', 'privacy', 'terms']);

test('about states the demo facts and links to privacy', function () {
    $this->get(route('about'))
        ->assertOk()
        ->assertSee('Taskboard for AI Agents')
        ->assertSee('Projects maximum')
        ->assertSee('Tasks maximum')
        ->assertSee('Workspace lifetime')
        ->assertSee('href="'.route('privacy').'"', false)
        ->assertSee('https://github.com/seerat89/tasknet', false);
});

test('about lists MCP-capable clients without claiming an integration', function () {
    $this->get(route('about'))
        ->assertOk()
        ->assertSee('How it works')
        ->assertSee('Claude')
        ->assertSee('Model Context Protocol (MCP)', false)
        ->assertSee('Gemini CLI')
        ->assertSee('GitHub Copilot')
        ->assertSee('Gemini CLI')
        ->assertSee('ChatGPT')
        ->assertSee('MCP Inspector')
        ->assertSee('Compatible clients')
        ->assertSee('vary between clients and subscription plans')
        ->assertDontSee('manually verified')
        ->assertDontSee('portfolio');
});

test('about explains TaskNet without legal disclaimers and links to the terms', function () {
    $this->get(route('about'))
        ->assertOk()
        ->assertSee('TaskNet explores how AI agents can work with a web application')
        ->assertSee('Shared application rules')
        ->assertSee('OAuth approval')
        ->assertSee('Projects &amp; Tasks', false)
        ->assertSee('Testing locally?')
        ->assertSee('ngrok')
        ->assertSee('Cloudflare Tunnel')
        ->assertSee('docs/development.md#testing-with-remote-ai-clients-tunnels', false)
        ->assertSee('href="'.route('terms').'"', false)
        ->assertDontSee('Demo disclaimer')
        ->assertDontSee('provided as-is')
        ->assertDontSee('small project')
        ->assertDontSee('portfolio')
        ->assertDontSee('public demo');
});

test('terms cover the essentials', function () {
    $this->get(route('terms'))
        ->assertOk()
        ->assertSeeInOrder([
            'Using TaskNet',
            'Demo workspaces',
            'External AI clients',
            'Acceptable use',
            'Availability',
            'Privacy',
        ])
        ->assertSee('demonstration service')
        ->assertSee('10 projects and 100 tasks')
        ->assertSee('deleted automatically 30 days after you create it')
        ->assertSee('delete a workspace yourself at any time')
        ->assertSee('provided as is')
        ->assertSee('You decide which clients to connect. TaskNet limits connected clients to the workspace permissions and MCP tools provided by the service.')
        ->assertDontSee('what they may access')
        ->assertSee('TaskNet cannot control how a provider handles information')
        ->assertSee('href="'.route('privacy').'"', false)
        ->assertDontSee('public demo')
        ->assertDontSee('TASKNET_');
});

test('the footer links to Privacy and Terms on public and authenticated pages', function () {
    $this->get(route('about'))
        ->assertSee('href="'.route('privacy').'"', false)
        ->assertSee('href="'.route('terms').'"', false);

    $this->actingAs(User::factory()->demo()->create())->get(route('dashboard'))
        ->assertSee('href="'.route('terms').'"', false);
});

test('about does not claim a production deployment', function () {
    $this->get(route('about'))
        ->assertDontSee('Vercel')
        ->assertDontSee('Neon');
});

test('privacy states only what is true', function () {
    $this->get(route('privacy'))
        ->assertOk()
        ->assertSeeInOrder(['Information TaskNet stores', 'Cookies', 'Analytics', 'Retention', 'External AI clients'])
        ->assertSee('demonstration service')
        ->assertSee('sensitive, confidential or important information')
        ->assertSee('Demo ID')
        ->assertSee('a hash of your password')
        ->assertSee('It does not use advertising or analytics cookies')
        ->assertSee('Vercel Web Analytics does not use analytics cookies')
        ->assertSee('Technical request information may be processed briefly for security and rate limiting')
        ->assertSee('Hosting and infrastructure providers, such as Vercel, may separately process technical request information')
        ->assertDontSeeText('anonymous')
        ->assertSee('automatically deleted 30 days after creation')
        ->assertSee('delete your workspace at any time from Workspace settings')
        ->assertSee('under its own terms and privacy policy')
        ->assertSee('It does not retain raw IP addresses or User-Agent strings in its application database')
        ->assertSeeText('Infrastructure-provider logs and backups may be retained for a limited period according to the provider\'s own policies')
        ->assertDontSeeText(['fully GDPR compliant', 'never receives', 'never logs', 'stays in Europe'])
        ->assertDontSee('consent')
        ->assertDontSee('Why information is processed')
        ->assertDontSee('Your rights')
        ->assertDontSee('Contact')
        ->assertDontSee('Service providers')
        ->assertDontSee('public demo')
        ->assertDontSee('issues', false)
        ->assertDontSee('TASKNET_')
        ->assertDontSee('Neon')
        ->assertDontSee('operated by');
});

test('privacy shows configured provider, operator and contact values safely', function () {
    config()->set('tasknet.hosting_provider', 'Example Host');
    config()->set('tasknet.database_provider', 'Example <b>DB</b>');
    config()->set('tasknet.operator_name', 'Example Operator');
    config()->set('tasknet.privacy_contact', 'privacy@example.com');

    $response = $this->get(route('privacy'))
        ->assertOk()
        ->assertSee('Service providers')
        ->assertSee('Hosting:')
        ->assertSee('Example Host')
        ->assertSee('Database:')
        ->assertSee('TaskNet is operated by Example Operator.')
        ->assertSee('Who operates TaskNet')
        ->assertSee('Privacy questions can be sent to')
        ->assertSee('mailto:privacy@example.com', false);

    expect($response->getContent())->not->toContain('Example <b>DB</b>')->toContain('Example &lt;b&gt;DB&lt;/b&gt;');
});

test('the authenticated navigation links to about and the footer to privacy', function () {
    $user = User::factory()->demo()->create();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('href="'.route('dashboard').'"', false)
        ->assertSeeInOrder(['Projects', 'About'])
        ->assertSee('href="'.route('about').'"', false)
        ->assertSee('href="'.route('privacy').'"', false)
        ->assertSee('30 days left')
        ->assertDontSeeText('Workspace will be deleted');
});

test('the dashboard statistic is labelled Completed tasks', function () {
    $this->actingAs(User::factory()->demo()->create())
        ->get(route('dashboard'))
        ->assertSee('Completed tasks');
});

test('guests can reach both pages from the home page footer', function () {
    $this->get(route('home'))
        ->assertSee('href="'.route('about').'"', false)
        ->assertSee('href="'.route('privacy').'"', false);
});

test('public pages expose no synthetic email, hashes or secrets', function (string $route) {
    $user = User::factory()->demo()->create();

    $response = $this->actingAs($user)->get(route($route))->assertOk();

    $response->assertDontSee($user->email)
        ->assertDontSee('tasknet.local')
        ->assertDontSee($user->password)
        ->assertDontSee('access_token')
        ->assertDontSee('PRIVATE KEY');
})->with(['about', 'privacy']);

test('about and privacy render for authenticated users too', function (string $route) {
    $this->actingAs(User::factory()->demo()->create())
        ->get(route($route))
        ->assertOk()
        ->assertSee('Projects')
        ->assertSee('Log out');
})->with(['about', 'privacy']);

test('authenticated pages share one header with navigation, status and log out', function (string $route) {
    $user = User::factory()->demo()->create();
    $project = Project::factory()->for($user)->create();

    $this->actingAs($user)->get(route($route, $route === 'projects.show' ? $project : []))
        ->assertOk()
        ->assertSeeInOrder(['Projects', 'About', 'Log out'])
        ->assertSee($user->demo_id)
        ->assertSee('30 days left')
        ->assertSee('aria-controls="mobile-menu"', false);
})->with(['dashboard', 'about', 'privacy', 'projects.show']);

test('guests see About, Privacy, Sign in and Start demo on public pages', function (string $route) {
    $this->get(route($route))
        ->assertOk()
        ->assertSeeInOrder(['About', 'Sign in', 'Start demo'])
        ->assertSee('href="'.route('login').'"', false)
        ->assertSee('href="'.route('demo.create').'"', false);
})->with(['home', 'about', 'privacy']);

test('the landing page offers About and Privacy before any demo exists', function () {
    $this->get(route('home'))
        ->assertSee('href="'.route('about').'"', false)
        ->assertSee('href="'.route('privacy').'"', false);
});

test('login and credentials-free auth pages link back to the public pages', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('href="'.route('home').'"', false)
        ->assertSee('href="'.route('about').'"', false)
        ->assertSee('href="'.route('privacy').'"', false);
});

test('authenticated pages never show Sign in or Start demo', function (string $route) {
    $this->actingAs(User::factory()->demo()->create())
        ->get(route($route))
        ->assertOk()
        ->assertDontSee('href="'.route('login').'"', false)
        ->assertDontSee('href="'.route('demo.create').'"', false)
        ->assertDontSeeText(['Sign in', 'Start demo']);
})->with(['dashboard', 'about', 'privacy']);

test('guests see About and Privacy without workspace status or log out', function (string $route) {
    $this->get(route($route))
        ->assertOk()
        ->assertSee('About')
        ->assertDontSee('Log out')
        ->assertDontSee('days left');
})->with(['about', 'privacy']);

test('the Terms link to Privacy is generated from the named route', function () {
    expect(file_get_contents(resource_path('views/terms.blade.php')))->toContain("route('privacy')")->not->toContain('localhost');

    $this->get(route('terms'))->assertSee('href="'.route('privacy').'"', false);
});

test('external links open in a new tab and internal links do not', function () {
    $about = $this->get(route('about'))->assertOk()->getContent();

    expect($about)
        ->toMatch('#<a target="_blank" rel="noopener noreferrer" href="https://github\.com/seerat89/tasknet"#')
        ->toContain('(opens in a new tab)')
        ->not->toMatch('#<a [^>]*href="'.preg_quote(route('privacy'), '#').'"[^>]*target=#');

    $privacy = $this->get(route('privacy'))->assertOk()->getContent();

    expect($privacy)->not->toContain('target="_blank"');
});
