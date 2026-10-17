<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

test('a request forwarded as https is secure and generates https URLs', function () {
    $response = $this->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Port' => '443'])->get(route('about'))->assertOk();

    expect(request()->isSecure())->toBeTrue();
    $response->assertSee('href="https://localhost/privacy"', false)
        ->assertDontSee('href="http://localhost/privacy"', false);
});

test('a request without forwarded headers stays on http', function () {
    $response = $this->get(route('about'))->assertOk();

    expect(request()->isSecure())->toBeFalse();
    $response->assertSee('href="http://localhost:8000/privacy"', false);
});

test('the forwarded host and port are used for generated URLs', function () {
    $this->withHeaders([
        'X-Forwarded-Proto' => 'https',
        'X-Forwarded-Host' => 'tasknet.seerat.me',
        'X-Forwarded-Port' => '443',
    ])->get(route('about'))->assertSee('href="https://tasknet.seerat.me/privacy"', false);
});

test('OAuth metadata uses https behind the proxy', function () {
    $metadata = $this->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'tasknet.seerat.me', 'X-Forwarded-Port' => '443'])
        ->getJson('/.well-known/oauth-authorization-server')->assertOk()->json();

    foreach (['issuer', 'authorization_endpoint', 'token_endpoint', 'registration_endpoint'] as $key) {
        expect($metadata[$key])->toStartWith('https://tasknet.seerat.me');
    }
});

test('protected resource metadata uses https behind the proxy', function () {
    $response = $this->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'tasknet.seerat.me', 'X-Forwarded-Port' => '443'])
        ->getJson('/.well-known/oauth-protected-resource/mcp')->assertOk();

    expect($response->json('resource'))->toBe('https://tasknet.seerat.me/mcp')
        ->and($response->json('authorization_servers'))->toBe(['https://tasknet.seerat.me']);
});

test('redirects and the OAuth consent form use https behind the proxy', function () {
    $this->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Port' => '443'])->get(route('dashboard'))
        ->assertRedirect('https://localhost/login');

    $user = User::factory()->demo()->create();
    $client = registerTestClient();

    $this->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Port' => '443'])->actingAs($user)
        ->get(authorizeUrl($client))->assertOk()
        ->assertSee('action="https://localhost/oauth/authorize"', false);
});
