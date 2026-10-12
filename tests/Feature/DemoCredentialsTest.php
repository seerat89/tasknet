<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->freezeTime();
    $this->post(route('demo.store'));
    $this->user = User::sole();
    $this->password = Crypt::decryptString(session('demo_credentials.password'));
});

test('demo creation signs the new user in', function () {
    $this->assertAuthenticatedAs($this->user);
    expect($this->user->is_demo)->toBeTrue();
});

test('the credential page shows the Demo ID, password and expiry once', function () {
    $response = $this->get(route('demo.credentials'));

    $response->assertOk()
        ->assertSeeText([
            'Your demo workspace is ready',
            'You’re signed in now. Save these login details so you can return after signing out or from another browser.',
            'Save your password now. It cannot be recovered.',
            'TaskNet stores only a hash of your password and has no password reset.',
            'This is the only time it is shown.',
            'you will not be able to sign in again after your current session ends',
            $this->user->demo_id,
            $this->password,
            'Workspace expires',
            $this->user->expires_at->utc()->format('F j, Y \a\t H:i').' UTC',
            'TaskNet is a demonstration service.',
            'deleted automatically 30 days after you create it',
        ])
        ->assertDontSeeText(['public MCP testing environment', 'permanently deleted on']);
});

test('copying and downloading stay available but optional', function () {
    $this->get(route('demo.credentials'))
        ->assertSeeText(['Copy credentials', 'Download login details'])
        ->assertSee('data-copy-text=', false)
        ->assertSee('href="'.route('demo.credentials.download').'"', false)
        ->assertSee('id="save-status"', false);
});

test('continuing needs no checkbox and is never disabled', function () {
    $response = $this->get(route('demo.credentials'));

    $response->assertSeeText('Continue to workspace')
        ->assertSee('action="'.route('demo.credentials.continue').'"', false)
        ->assertDontSee('type="checkbox"', false)
        ->assertDontSee('data-acknowledge', false)
        ->assertDontSee('id="acknowledge"', false)
        ->assertDontSeeText(['Save your credentials', 'Confirm and continue', 'to enable this step']);

    expect($response->getContent())->not->toMatch('/<button[^>]*\sdisabled[\s>=]/');
});

test('continuing without saving anything opens the dashboard', function () {
    $this->post(route('demo.credentials.continue'))->assertRedirect(route('dashboard'));

    $this->get(route('dashboard'))->assertOk();
    $this->assertAuthenticatedAs($this->user);
});

test('the credentials page uses the normal signed-in navigation', function () {
    $this->get(route('demo.credentials'))
        ->assertSeeInOrder(['Projects', 'About', 'Log out'])
        ->assertSee($this->user->demo_id);
});

test('the password is never stored in plaintext', function () {
    expect($this->user->fresh()->password)->not->toBe($this->password)
        ->and(Hash::check($this->password, $this->user->fresh()->password))->toBeTrue()
        ->and(DB::table('users')->where('id', $this->user->id)->value('password'))->not->toContain($this->password);
});

test('leaving the credentials screen discards the plaintext credentials', function () {
    $this->get(route('about'))->assertOk();

    expect(session()->has('demo_credentials'))->toBeFalse();

    $this->get(route('demo.credentials'))->assertRedirect(route('dashboard'));
    $this->get(route('demo.credentials.download'))->assertRedirect(route('dashboard'));
});

test('the credential screen can be refreshed until the user leaves it', function () {
    $this->get(route('demo.credentials'))->assertOk()->assertSeeText($this->password);
    $this->get(route('demo.credentials'))->assertOk()->assertSeeText($this->password);

    expect(User::count())->toBe(1);
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

    $response->assertRedirect(route('dashboard'));
    expect(session()->has('demo_credentials'))->toBeFalse();
    $this->assertAuthenticatedAs($this->user);
});

test('the credentials cannot be redisplayed after continuing', function () {
    $this->post(route('demo.credentials.continue'));

    $this->get(route('demo.credentials'))
        ->assertRedirect(route('dashboard'));
    $this->get(route('home'))->assertDontSee($this->password);
});

test('the download cannot expose the credentials after continuing', function () {
    $this->post(route('demo.credentials.continue'));

    $response = $this->get(route('demo.credentials.download'));

    $response->assertRedirect(route('dashboard'));
    expect($response->getContent())->not->toContain($this->password);
});

test('guests cannot reach the credential routes', function () {
    auth()->logout();
    $this->flushSession();

    $this->get(route('demo.credentials'))->assertRedirect(route('login'));
    $this->get(route('demo.credentials.download'))->assertRedirect(route('login'));
    $this->post(route('demo.credentials.continue'))->assertRedirect(route('login'));
});
