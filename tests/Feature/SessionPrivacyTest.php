<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;

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

test('database session rows keep the session itself but not the IP address or User-Agent', function () {
    $user = User::factory()->demo()->create();

    fromKnownClient()->actingAs($user)->get(route('dashboard'))->assertOk();

    $rows = DB::table('sessions')->get();
    expect($rows)->toHaveCount(1);

    $row = $rows->first();
    expect($row->user_id)->toBe($user->id)
        ->and($row->payload)->not->toBeEmpty()
        ->and($row->last_activity)->toBeInt()->toBeGreaterThan(0)
        ->and($row->ip_address)->toBeNull()
        ->and($row->user_agent)->toBeNull()
        ->and(json_encode($row))->not->toContain('203.0.113.42')->not->toContain('TaskNet-Privacy-Test');
});

test('later requests in the same session do not populate the IP address or User-Agent', function () {
    $user = User::factory()->demo()->create();

    fromKnownClient()->actingAs($user)->get(route('dashboard'))->assertOk();
    $sessionId = DB::table('sessions')->value('id');

    fromKnownClient()->withCookie(config('session.cookie'), encrypt($sessionId, false))
        ->get(route('settings'))->assertOk();
    fromKnownClient()->post(route('logout'));

    $rows = DB::table('sessions')->get();
    expect($rows)->not->toBeEmpty();

    foreach ($rows as $row) {
        expect($row->ip_address)->toBeNull()->and($row->user_agent)->toBeNull();
    }
});

test('a new demo workspace session is stored without request metadata', function () {
    fromKnownClient()->post(route('demo.store'))->assertRedirect(route('demo.credentials'));

    $row = DB::table('sessions')->first();

    expect($row->user_id)->not->toBeNull()
        ->and($row->ip_address)->toBeNull()
        ->and($row->user_agent)->toBeNull();
});

test('the migration clears IP addresses and User-Agents stored before', function () {
    DB::table('sessions')->insert([
        'id' => 'legacy-session',
        'user_id' => null,
        'ip_address' => '203.0.113.42',
        'user_agent' => 'Legacy Browser',
        'payload' => 'kept',
        'last_activity' => 1700000000,
    ]);

    (require database_path('migrations/2026_10_12_090000_clear_session_ip_and_user_agent.php'))->up();

    $row = DB::table('sessions')->where('id', 'legacy-session')->first();

    expect($row->ip_address)->toBeNull()
        ->and($row->user_agent)->toBeNull()
        ->and($row->payload)->toBe('kept')
        ->and($row->last_activity)->toBe(1700000000);
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
