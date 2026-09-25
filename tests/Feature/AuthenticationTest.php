<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

test('guests can open the branded authentication forms', function (string $route, string $title, string $action, array $fields) {
    $response = $this->get(route($route));

    $response->assertSeeText(['TaskNet', 'Taskboard for AI Agents', $title])
        ->assertSee('action="'.route($action).'"', false)
        ->assertSee('method="POST"', false)
        ->assertSee('name="_token"', false);

    foreach ($fields as $field) {
        $response->assertSee('name="'.$field.'"', false)
            ->assertSee('for="'.$field.'"', false);
    }
})->with([
    'login' => ['login', 'Welcome back', 'login.store', ['email', 'password', 'remember']],
    'register' => ['register', 'Create your account', 'register.store', ['name', 'email', 'password', 'password_confirmation']],
    'forgot password' => ['password.request', 'Forgot your password?', 'password.email', ['email']],
]);

test('the reset form preserves the link token and email without exposing markup', function () {
    $email = '"><script>alert(1)</script>@example.test';

    $response = $this->get(route('password.reset', ['token' => 'test-reset-token', 'email' => $email]));

    $response->assertSeeText('Reset your password')
        ->assertSee('action="'.route('password.update').'"', false)
        ->assertSee('name="token" value="test-reset-token"', false)
        ->assertSee($email)
        ->assertDontSee($email, false)
        ->assertSee('name="password_confirmation"', false);
});

test('users can log in and reach the existing home page', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
        'remember' => '1',
    ]);

    $response->assertRedirect(route('home'));
    $this->assertAuthenticatedAs($user);
    $this->get(route('home'))->assertSeeText('Log out');
});

test('invalid login displays an associated error and keeps only safe input', function () {
    $user = User::factory()->create();

    $response = $this->from(route('login'))->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'incorrect-test-password',
        'remember' => '1',
    ]);

    $response->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => __('auth.failed')])
        ->assertSessionHasInput('email', $user->email)
        ->assertSessionMissing('_old_input.password');
    $this->assertGuest();
    $this->withCookie(config('session.cookie'), session()->getId())->get(route('login'))
        ->assertSeeText(__('auth.failed'))
        ->assertSee('aria-describedby="email-error"', false)
        ->assertSee('aria-invalid="true"', false)
        ->assertSee('value="'.$user->email.'"', false)
        ->assertDontSee('incorrect-test-password');
});

test('registration creates an unverified account and sends a verification email', function () {
    Notification::fake();
    $user = User::factory()->make();

    $response = $this->post(route('register.store'), [
        'name' => $user->name,
        'email' => $user->email,
        'password' => 'test-password-123',
        'password_confirmation' => 'test-password-123',
    ]);

    $response->assertRedirect(route('verification.notice'));
    $registeredUser = User::where('email', $user->email)->sole();
    expect($registeredUser->name)->toBe($user->name);
    expect($registeredUser->hasVerifiedEmail())->toBeFalse();
    expect(Hash::check('test-password-123', $registeredUser->password))->toBeTrue();
    $this->assertAuthenticatedAs($registeredUser);
    Notification::assertSentTo($registeredUser, VerifyEmail::class);
});

test('registration validation preserves and escapes the name without repopulating passwords', function () {
    Notification::fake();
    $name = '<script>alert(1)</script>';

    $response = $this->from(route('register'))->post(route('register.store'), [
        'name' => $name,
        'email' => 'invalid-email',
        'password' => 'test-password-123',
        'password_confirmation' => 'different-test-password',
    ]);

    $response->assertRedirect(route('register'))
        ->assertSessionHasErrors(['email', 'password'])
        ->assertSessionMissing('_old_input.password')
        ->assertSessionMissing('_old_input.password_confirmation');
    $this->assertDatabaseCount('users', 0);
    $this->assertGuest();
    Notification::assertNothingSent();
    $this->withCookie(config('session.cookie'), session()->getId())->get(route('register'))
        ->assertSee($name)
        ->assertDontSee($name, false)
        ->assertSee('aria-describedby="password-error"', false)
        ->assertSeeText('The password field confirmation does not match.')
        ->assertDontSee('test-password-123');
});

test('users can request a reset link and see the confirmation', function () {
    Notification::fake();
    $user = User::factory()->create();

    $response = $this->from(route('password.request'))->post(route('password.email'), ['email' => $user->email]);

    $response->assertRedirect(route('password.request'))
        ->assertSessionHas('status', __('passwords.sent'));
    Notification::assertSentTo($user, ResetPassword::class);
    $this->get(route('password.request'))->assertSeeText(__('passwords.sent'));
});

test('invalid reset link requests display the email validation error', function () {
    Notification::fake();

    $response = $this->from(route('password.request'))->post(route('password.email'), ['email' => 'invalid-email']);

    $response->assertRedirect(route('password.request'))->assertSessionHasErrors('email');
    Notification::assertNothingSent();
    $this->withCookie(config('session.cookie'), session()->getId())->get(route('password.request'))
        ->assertSeeText('The email field must be a valid email address.')
        ->assertSee('aria-describedby="email-error"', false);
});

test('users can reset their password and see the success message on login', function () {
    $user = User::factory()->create();
    $token = Password::broker()->createToken($user);

    $response = $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => 'replacement-test-password',
        'password_confirmation' => 'replacement-test-password',
    ]);

    $response->assertRedirect(route('login'))
        ->assertSessionHas('status', __('passwords.reset'));
    expect(Hash::check('replacement-test-password', $user->fresh()->password))->toBeTrue();
    expect(Password::broker()->tokenExists($user, $token))->toBeFalse();
    $this->get(route('login'))->assertSeeText(__('passwords.reset'));
});

test('an invalid reset token leaves the password unchanged and displays an error', function () {
    $user = User::factory()->create();
    $resetUrl = route('password.reset', ['token' => 'invalid-test-token', 'email' => $user->email]);

    $response = $this->from($resetUrl)->post(route('password.update'), [
        'token' => 'invalid-test-token',
        'email' => $user->email,
        'password' => 'replacement-test-password',
        'password_confirmation' => 'replacement-test-password',
    ]);

    $response->assertRedirect($resetUrl)
        ->assertSessionHasErrors(['email' => __('passwords.token')]);
    expect($user->fresh()->password)->toBe($user->password);
    $this->withCookie(config('session.cookie'), session()->getId())->get($resetUrl)
        ->assertSeeText(__('passwords.token'));
});

test('unverified users can see their email and verification actions', function () {
    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->get(route('verification.notice'));

    $response->assertSeeText(['Check your email', $user->email, 'Resend verification email', 'Log out'])
        ->assertSee('action="'.route('verification.send').'"', false)
        ->assertSee('action="'.route('logout').'"', false);
    $this->get(route('home'))->assertSee('href="'.route('verification.notice').'"', false);
});

test('guests must log in before viewing or resending verification', function (string $method, string $route) {
    $response = $this->{$method}(route($route));

    $response->assertRedirect(route('login'));
})->with([
    'notice' => ['get', 'verification.notice'],
    'resend' => ['post', 'verification.send'],
]);

test('verified users are redirected from the verification notice', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('verification.notice'));

    $response->assertRedirect(route('home'));
});

test('verification resends only to the authenticated user and displays confirmation', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();
    $otherUser = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->from(route('verification.notice'))
        ->post(route('verification.send'), ['email' => $otherUser->email]);

    $response->assertRedirect(route('verification.notice'))
        ->assertSessionHas('status', 'verification-link-sent');
    Notification::assertSentTo($user, VerifyEmail::class);
    Notification::assertNotSentTo($otherUser, VerifyEmail::class);
    $this->get(route('verification.notice'))
        ->assertSeeText('A new verification link has been sent to your email address.');
});

test('users can verify their email with a signed link', function () {
    $user = User::factory()->unverified()->create();
    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $user->id,
        'hash' => sha1($user->email),
    ]);

    $response = $this->actingAs($user)->get($url);

    $response->assertRedirect(route('home').'?verified=1');
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('users cannot verify another account with its signed link', function () {
    $user = User::factory()->unverified()->create();
    $otherUser = User::factory()->unverified()->create();
    $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
        'id' => $otherUser->id,
        'hash' => sha1($otherUser->email),
    ]);

    $response = $this->actingAs($user)->get($url);

    $response->assertForbidden();
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
    expect($otherUser->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('users can log out from the verification screen', function () {
    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('home'));
    $this->assertGuest();
});
