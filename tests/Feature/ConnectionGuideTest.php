<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

// The public guide to connecting an AI client, and the links that lead to it.

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

/**
 * Parse a page, so structure can be checked rather than guessed from strings.
 */
function pageDocument(string $html): DOMXPath
{
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="utf-8">'.$html);

    return new DOMXPath($document);
}

test('the guide is public and open to signed-in users', function () {
    $this->get(route('connect'))->assertOk()->assertSeeText('Connect an AI client');

    $this->actingAs(User::factory()->demo()->create())->get(route('connect'))->assertOk();
});

test('the server URL comes from the configured application URL', function () {
    config(['app.url' => 'https://tasks.example.test/']);

    $this->get(route('connect'))
        ->assertSee('data-copy-text="https://tasks.example.test/mcp"', false)
        ->assertSeeText('https://tasks.example.test/mcp')
        ->assertDontSee('tasknet.seerat.me')
        ->assertDontSeeText('This address does not use HTTPS');
});

test('the server URL is the resource the OAuth metadata names', function () {
    $resource = $this->getJson('/.well-known/oauth-protected-resource/mcp')->assertOk()->json('resource');

    $this->get(route('connect'))->assertSee('data-copy-text="'.$resource.'"', false);
});

test('an address without HTTPS is flagged as unusable by cloud assistants', function () {
    config(['app.url' => 'http://localhost:8000']);

    $this->get(route('connect'))->assertSeeText('This address does not use HTTPS');
});

test('the guide covers each client, sign-in, prompts, permissions and troubleshooting', function () {
    $this->get(route('connect'))->assertSeeInOrder([
        'MCP server URL', 'Before you start',
        'ChatGPT', 'Add custom MCP server', 'Create as a plugin',
        'Claude', 'Add custom connector', 'Register automatically',
        'Other MCP clients', 'Streamable HTTP', 'Dynamic client registration', 'mcp:use',
        'Sign-in and approval', 'Demo ID', 'Approve',
        'Example prompts', 'Ask in conversation', 'List my TaskNet projects.', 'Open an interactive view', 'Show my TaskNet projects.',
        'Permissions and removing access', 'View your projects and tasks', 'It cannot delete anything', 'Revoke access',
        'Troubleshooting', 'invalid_redirect_uri', '429',
    ]);
});

test('the stated limits and lifetimes follow the configuration', function () {
    config(['tasknet.mcp.requests_per_minute' => 77, 'tasknet.mcp.oauth.access_token_days' => 2, 'tasknet.mcp.oauth.refresh_token_days' => 45]);

    $this->get(route('connect'))->assertSeeText(['77 MCP requests per minute', 'lasts 2 days', 'goes 45 days without renewing']);
});

// The guide tells Claude users to register automatically, which is only right while this stays true.
test('TaskNet offers dynamic registration and not client ID metadata documents', function () {
    $metadata = $this->getJson('/.well-known/oauth-authorization-server')->assertOk()->json();

    expect($metadata)->toHaveKey('registration_endpoint')
        ->not->toHaveKey('client_id_metadata_document_supported')
        ->and($metadata['code_challenge_methods_supported'])->toBe(['S256'])
        ->and($metadata['scopes_supported'])->toBe(['mcp:use']);
});

test('the guide links to the sign-up page for guests and to Settings for signed-in users', function () {
    $this->get(route('connect'))
        ->assertSee('href="'.route('demo.create').'"', false)
        ->assertDontSee('href="'.route('settings').'"', false);

    $user = User::factory()->demo()->create();

    $this->actingAs($user)->get(route('connect'))
        ->assertSee('href="'.route('settings').'"', false)
        ->assertSeeText($user->demo_id)
        ->assertDontSee($user->email)
        ->assertDontSee($user->password);
});

test('the guide has labelled sections, working page links and a named copy control', function () {
    $page = pageDocument($this->get(route('connect'))->getContent());

    expect($page->query('//main//h1')->length)->toBe(1);

    foreach ($page->query('//main//section') as $section) {
        $label = $section->getAttribute('aria-labelledby');

        expect($label)->not->toBeEmpty()
            ->and($page->query("//*[@id='{$label}']")->length)->toBe(1);
    }

    $links = $page->query('//nav[@aria-label="On this page"]//a');

    expect($links->length)->toBeGreaterThan(5);

    foreach ($links as $link) {
        expect($page->query('//*[@id="'.ltrim($link->getAttribute('href'), '#').'"]')->length)->toBe(1);
    }

    expect(trim($page->query('//button[@data-copy-text]')->item(0)->textContent))->toBe('Copy server URL')
        ->and($page->query('//*[@id="save-status"][@role="status"]')->length)->toBe(1);
});

test('external documentation opens in a new tab and says so', function () {
    $page = pageDocument($this->get(route('connect'))->getContent());
    $external = $page->query('//main//a[starts-with(@href, "https://")]');

    expect($external->length)->toBeGreaterThan(3);

    foreach ($external as $link) {
        if (str_starts_with($link->getAttribute('href'), config('app.url'))) {
            continue;
        }

        expect($link->getAttribute('rel'))->toBe('noopener noreferrer')
            ->and($link->textContent)->toContain('opens in a new tab');
    }
});

// The link component ends without a newline, which would otherwise show as a space before punctuation.
test('no space separates an external link from the punctuation after it', function () {
    expect($this->get(route('connect'))->getContent())->not->toMatch('#opens in a new tab\)</span></a>\s+[.,;:]#');
});

test('the landing page links to the guide', function () {
    $this->get(route('home'))->assertSee('How to connect ChatGPT, Claude and other clients');

    $page = pageDocument($this->get(route('home'))->getContent());

    expect($page->query('//main//a[@href="'.route('connect').'"]')->length)->toBe(1);
});

test('the Settings client section links to the guide', function () {
    $settings = pageDocument($this->actingAs(User::factory()->demo()->create())->get(route('settings'))->getContent());

    expect($settings->query('//section[@aria-labelledby="clients-heading"]//a[@href="'.route('connect').'"]')->length)->toBe(1);
});

test('the footer links to the guide on every page', function (string $route, bool $signedIn) {
    if ($signedIn) {
        $this->actingAs(User::factory()->demo()->create());
    }

    $page = pageDocument($this->get(route($route))->assertOk()->getContent());

    $links = $page->query('//footer//a[@href="'.route('connect').'"]');

    expect($links->length)->toBe(1)
        ->and(trim($links->item(0)->textContent))->toBe('How to connect an AI client?');
})->with([
    ['home', false], ['about', false], ['privacy', false], ['terms', false], ['connect', false],
    ['dashboard', true], ['settings', true], ['projects.index', true],
]);

test('the privacy notice names the connection dates TaskNet keeps', function () {
    $this->get(route('privacy'))->assertSeeText('when you approved it and when it last used your workspace');
});
