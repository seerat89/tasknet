<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();

    // The suite uses array sessions; these tests exercise the real database driver.
    config(['session.driver' => 'database']);
});

/**
 * Make a request that looks like it comes from a recognizable client.
 */
function fromKnownClient(): mixed
{
    return test()
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.42'])
        ->withHeader('User-Agent', 'TaskNet-Privacy-Test/1.0');
}

test('the sessions table has no IP address or User-Agent columns', function () {
    expect(Schema::hasColumns('sessions', ['id', 'user_id', 'payload', 'last_activity']))->toBeTrue()
        ->and(Schema::hasColumn('sessions', 'ip_address'))->toBeFalse()
        ->and(Schema::hasColumn('sessions', 'user_agent'))->toBeFalse();
});

test('database sessions are stored normally without request metadata', function () {
    $user = User::factory()->demo()->create();

    fromKnownClient()->actingAs($user)->get(route('dashboard'))->assertOk();

    $rows = DB::table('sessions')->get();
    expect($rows)->toHaveCount(1);

    $row = $rows->first();
    expect($row->user_id)->toBe($user->id)
        ->and($row->payload)->not->toBeEmpty()
        ->and($row->last_activity)->toBeInt()->toBeGreaterThan(0)
        ->and(json_encode($row))->not->toContain('203.0.113.42')->not->toContain('TaskNet-Privacy-Test');
});

test('later requests in the same session keep working', function () {
    $user = User::factory()->demo()->create();

    fromKnownClient()->actingAs($user)->get(route('dashboard'))->assertOk();
    $sessionId = DB::table('sessions')->value('id');

    fromKnownClient()->withCookie(config('session.cookie'), encrypt($sessionId, false))
        ->get(route('settings'))->assertOk();
    fromKnownClient()->post(route('logout'));

    expect(DB::table('sessions')->count())->toBeGreaterThan(0);
});

test('a new demo workspace session is stored with its user', function () {
    fromKnownClient()->post(route('demo.store'))->assertRedirect(route('demo.credentials'));

    expect(DB::table('sessions')->first()->user_id)->not->toBeNull();
});

test('IP-based abuse controls still apply per client address', function () {
    foreach (range(1, 10) as $ignored) {
        fromKnownClient()->post(route('demo.store'))->assertRedirect(route('demo.credentials'));

        // Each attempt is a new visitor; the throttle only knows the address.
        auth()->guard('web')->forgetUser();
        $this->flushSession();
    }

    fromKnownClient()->post(route('demo.store'))->assertTooManyRequests();

    // A different address has its own allowance.
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.99'])
        ->post(route('demo.store'))->assertRedirect(route('demo.credentials'));
});
