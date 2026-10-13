<?php

use App\Actions\CreateDemoWorkspace;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

test('guests can start a demo workspace and are sent to the credential handoff', function () {
    $response = $this->post(route('demo.store'), ['terms' => '1']);

    $response->assertRedirect(route('demo.credentials'));
    expect(User::count())->toBe(1);
});

test('the created user is a demo workspace with a friendly Demo ID', function () {
    $this->post(route('demo.store'), ['terms' => '1']);

    $user = User::sole();

    expect($user->is_demo)->toBeTrue()
        ->and($user->name)->toBe('Demo '.substr($user->demo_id, 3))
        ->and($user->demo_id)->toMatch('/^TN-[A-Z2-9]{6}$/');
});

test('every Demo ID is unique', function () {
    $demoIds = collect(range(1, 25))->map(fn () => app(CreateDemoWorkspace::class)->create()['user']->demo_id);

    expect($demoIds->unique())->toHaveCount(25);
    expect(fn () => User::factory()->demo()->create(['demo_id' => $demoIds->first()]))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('a synthetic internal email is created so visitors never provide one', function () {
    $this->post(route('demo.store'), ['terms' => '1'])->assertSessionHasNoErrors();

    $user = User::sole();

    expect($user->email)->toBe(strtolower($user->demo_id).'@tasknet.local');
});

test('the workspace expires thirty days after creation', function () {
    $this->freezeTime();

    $this->post(route('demo.store'), ['terms' => '1']);

    $expected = now()->addDays(30);

    expect(User::sole()->expires_at->between($expected->copy()->subSeconds(5), $expected->copy()->addSeconds(5)))->toBeTrue()
        ->and(config('tasknet.demo.lifetime_days'))->toBe(30);
});

test('only the password hash is stored and it matches the generated password', function () {
    $this->post(route('demo.store'), ['terms' => '1']);

    $user = User::sole();
    $password = Crypt::decryptString(session('demo_credentials.password'));

    expect(strlen($password))->toBe(20)
        ->and($user->password)->not->toBe($password)
        ->and(Hash::check($password, $user->password))->toBeTrue();
    $this->assertDatabaseMissing('users', ['password' => $password]);
    expect(json_encode(User::query()->get()->makeVisible('password')->toArray()))->not->toContain($password);
});

test('the new demo user is authenticated', function () {
    $this->post(route('demo.store'), ['terms' => '1']);

    $this->assertAuthenticatedAs(User::sole());
});

test('authenticated users cannot start another demo workspace', function () {
    $user = User::factory()->demo()->create();

    $this->actingAs($user)->post(route('demo.store'), ['terms' => '1'])->assertRedirect(route('dashboard'));

    expect(User::count())->toBe(1);
});

test('visiting the start page does not create a workspace', function () {
    $this->get(route('demo.create'))->assertOk();

    expect(User::count())->toBe(0);
});

test('the start page shows the unchecked Terms acknowledgment', function () {
    $this->get(route('demo.create'))
        ->assertOk()
        ->assertSeeText('I agree to the Terms and understand that TaskNet is a demonstration service and should not be used for sensitive, confidential or important information.')
        ->assertSeeText('By creating a demo workspace, you acknowledge the Privacy Notice.')
        ->assertSee('href="'.route('terms').'"', false)
        ->assertSee('href="'.route('privacy').'"', false)
        ->assertSee('id="terms" name="terms" type="checkbox"', false)
        ->assertDontSee('checked', false);
});

test('a demo workspace is not created without accepting the Terms', function (array $payload) {
    $this->from(route('demo.create'))->post(route('demo.store'), $payload)
        ->assertRedirect(route('demo.create'))
        ->assertSessionHasErrors('terms');

    expect(User::count())->toBe(0);
    $this->assertGuest();
    $this->assertDatabaseCount('sessions', 0);
})->with([
    'missing' => [[]],
    'empty' => [['terms' => '']],
    'declined' => [['terms' => '0']],
    'false' => [['terms' => 'false']],
]);

test('the Terms error is shown next to the checkbox', function () {
    $this->followingRedirects()->from(route('demo.create'))->post(route('demo.store'))
        ->assertSeeText('Please agree to the Terms to create a demo workspace.')
        ->assertSee('aria-describedby="terms-error"', false);
});

test('the Terms acknowledgment is not stored', function () {
    $this->post(route('demo.store'), ['terms' => '1']);

    expect(User::sole()->getAttributes())->not->toHaveKeys(['terms', 'terms_accepted_at']);
});

test('starting demo workspaces is rate limited per visitor', function () {
    foreach (range(1, 10) as $attempt) {
        auth()->logout();
        $this->post(route('demo.store'), ['terms' => '1'])->assertRedirect(route('demo.credentials'));
        $this->app['auth']->forgetGuards();
        $this->flushSession();
    }

    $this->post(route('demo.store'), ['terms' => '1'])->assertTooManyRequests();
    expect(User::count())->toBe(10);
});
