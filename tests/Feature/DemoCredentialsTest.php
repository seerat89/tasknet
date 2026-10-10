<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Crypt;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->freezeTime();
    $this->post(route('demo.store'));
    $this->user = User::sole();
    $this->password = Crypt::decryptString(session('demo_credentials.password'));
});

test('the credential page shows the Demo ID, password, expiry and warnings', function () {
    $response = $this->get(route('demo.credentials'));
    $expiry = $this->user->expires_at->utc()->format('F j, Y \a\t H:i').' UTC';

    $response->assertOk()
        ->assertSeeText([
            'Your demo workspace is ready',
            $this->user->demo_id,
            $this->password,
            $this->user->expires_at->utc()->format('F j, Y \a\t H:i').' UTC',
            'Save these credentials now. TaskNet stores only a password hash, so your current password cannot be shown or recovered later.',
            'Workspace expires',
            'Temporary demo workspace',
            'TaskNet is for testing MCP connections only. Do not store real projects, confidential information, personal data, or anything important here.',
            'All workspace data will be permanently deleted after 30 days.',
            'I have saved my login credentials and understand that this workspace and all of its data will be permanently deleted on '.$expiry.'.',
            'Copy credentials',
            'Download login details',
            'Continue',
        ])
        ->assertSee('action="'.route('demo.credentials.continue').'"', false)
        ->assertSeeInOrder(['id="acknowledge"', 'type="checkbox"', 'disabled'], false)
        ->assertSeeInOrder(['id="continue-button"', 'disabled'], false)
        ->assertSee('id="save-status"', false)
        ->assertSee('role="status"', false)
        ->assertSee('for="acknowledge"', false)
        ->assertSee('href="'.route('demo.credentials.download').'"', false);
});

test('the credential page is not cacheable', function () {
    $response = $this->get(route('demo.credentials'));

    expect($response->headers->get('Cache-Control'))->toContain('no-store')->toContain('private');
});

test('the credentials can be downloaded as a text file', function () {
    $response = $this->get(route('demo.credentials.download'));

    $response->assertOk()
        ->assertDownload('tasknet-demo-login.txt')
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
    expect($response->headers->get('Cache-Control'))->toContain('no-store')->toContain('private');
    expect($response->getContent())
        ->toContain('TaskNet Demo Workspace')
        ->toContain('Demo ID: '.$this->user->demo_id)
        ->toContain('Password: '.$this->password)
        ->toContain('Expires: '.$this->user->expires_at->utc()->format('F j, Y \a\t H:i').' UTC')
        ->toContain('automatically deleted after 30 days');
});

test('continuing removes the plaintext credentials from the session', function () {
    $response = $this->post(route('demo.credentials.continue'));

    $response->assertRedirect(route('home'));
    expect(session()->has('demo_credentials'))->toBeFalse();
    $this->assertAuthenticatedAs($this->user);
});

test('the credentials cannot be redisplayed after continuing', function () {
    $this->post(route('demo.credentials.continue'));

    $this->get(route('demo.credentials'))
        ->assertRedirect(route('home'));
    $this->get(route('home'))->assertDontSee($this->password);
});

test('the download cannot expose the credentials after continuing', function () {
    $this->post(route('demo.credentials.continue'));

    $response = $this->get(route('demo.credentials.download'));

    $response->assertRedirect(route('home'));
    expect($response->getContent())->not->toContain($this->password);
});

test('guests cannot reach the credential routes', function () {
    auth()->logout();
    $this->flushSession();

    $this->get(route('demo.credentials'))->assertRedirect(route('login'));
    $this->get(route('demo.credentials.download'))->assertRedirect(route('login'));
    $this->post(route('demo.credentials.continue'))->assertRedirect(route('login'));
});
