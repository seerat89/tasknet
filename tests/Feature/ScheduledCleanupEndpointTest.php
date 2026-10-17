<?php

use App\Actions\DeleteDemoWorkspace;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

function cronHeaders(string $secret = 'cron-secret-for-tests'): array
{
    return ['Authorization' => "Bearer {$secret}"];
}

beforeEach(fn () => config(['tasknet.cron.secret' => 'cron-secret-for-tests']));

test('the cleanup endpoint rejects requests without the cron secret', function () {
    $expired = User::factory()->demo()->expired()->create();

    $this->getJson(route('cron.cleanup'))->assertUnauthorized();
    $this->getJson(route('cron.cleanup'), cronHeaders('wrong-secret'))->assertUnauthorized();
    $this->getJson(route('cron.cleanup'), ['Authorization' => 'cron-secret-for-tests'])->assertUnauthorized();

    expect($expired->fresh())->not->toBeNull();
});

test('the cleanup endpoint stays closed while no secret is configured', function () {
    config(['tasknet.cron.secret' => null]);
    $expired = User::factory()->demo()->expired()->create();

    $this->getJson(route('cron.cleanup'), ['Authorization' => 'Bearer '])->assertUnauthorized();
    $this->getJson(route('cron.cleanup'), ['Authorization' => 'Bearer'])->assertUnauthorized();
    $this->getJson(route('cron.cleanup'))->assertUnauthorized();

    expect($expired->fresh())->not->toBeNull();
});

test('only GET requests are accepted', function () {
    $this->postJson(route('cron.cleanup'), [], cronHeaders())->assertStatus(405);
});

test('the cleanup endpoint runs both cleanups with the cron secret', function () {
    $expired = User::factory()->demo()->expired()->create();
    $active = User::factory()->demo()->create();

    $this->getJson(route('cron.cleanup'), cronHeaders())
        ->assertOk()
        ->assertJsonPath('tasknet:cleanup-demos.status', 'ok')
        ->assertJsonPath('tasknet:cleanup-demos.output', 'Deleted 1 expired demo workspace(s).')
        ->assertJsonPath('tasknet:cleanup-oauth.status', 'ok');

    expect($expired->fresh())->toBeNull()
        ->and($active->fresh())->not->toBeNull();
});

test('the cleanup endpoint starts no session and sets no cookies', function () {
    $response = $this->getJson(route('cron.cleanup'), cronHeaders())->assertOk();

    expect($response->headers->getCookies())->toBeEmpty();
});

test('a failed cleanup is reported, and the other cleanup still runs', function () {
    $this->mock(DeleteDemoWorkspace::class)->shouldReceive('delete')->andThrow(new RuntimeException('boom'));
    User::factory()->demo()->expired()->create();

    $this->getJson(route('cron.cleanup'), cronHeaders())
        ->assertServerError()
        ->assertJsonPath('tasknet:cleanup-demos.status', 'failed')
        ->assertJsonPath('tasknet:cleanup-oauth.status', 'ok');
});

test('the production cron runs the cleanup once a day at 02:00 UTC', function () {
    $crons = json_decode(file_get_contents(base_path('vercel.json')), true)['crons'];

    expect($crons)->toBe([['path' => '/cron/cleanup', 'schedule' => '0 2 * * *']])
        ->and(route('cron.cleanup', absolute: false))->toBe('/cron/cleanup');
});
