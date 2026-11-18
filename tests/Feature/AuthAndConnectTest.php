<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuthAndConnectTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_matches_email_case_insensitively(): void
    {
        $user = User::factory()->create([
            'email' => 'Buyer@Example.com',
            'password' => 'password',
        ]);

        $this->post('/login', [
            'email' => 'buyer@example.com',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_goes_directly_to_dashboard(): void
    {
        $this->post('/register', [
            'name' => 'New Buyer',
            'email' => 'NEW@Example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'new@example.com']);
        $this->assertDatabaseHas('eurocross_settings', [
            'user_id' => auth()->id(),
            'billing_email' => 'new@example.com',
            'plan' => 'starter',
        ]);
    }

    public function test_firebase_login_sends_api_key_in_query_and_token_in_body(): void
    {
        config()->set('services.firebase.api_key', 'test-firebase-key');

        Http::fake([
            'identitytoolkit.googleapis.com/*' => Http::response([
                'users' => [[
                    'email' => 'Buyer@Example.com',
                    'displayName' => 'Buyer',
                    'photoUrl' => 'https://lh3.googleusercontent.com/buyer.png',
                ]],
            ]),
        ]);

        $this->postJson('/auth/firebase', ['id_token' => 'google-token'])
            ->assertOk()
            ->assertJson(['success' => true]);

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://identitytoolkit.googleapis.com/v1/accounts:lookup?key=test-firebase-key'
                && data_get($request->data(), 'idToken') === 'google-token';
        });

        $this->assertDatabaseHas('users', [
            'email' => 'buyer@example.com',
            'avatar' => 'https://lh3.googleusercontent.com/buyer.png',
        ]);
        $this->assertAuthenticated();
    }

    public function test_firebase_login_backfills_avatar_for_existing_user(): void
    {
        $user = User::factory()->create([
            'email' => 'buyer@example.com',
            'avatar' => null,
        ]);

        Http::fake([
            'identitytoolkit.googleapis.com/*' => Http::response([
                'users' => [[
                    'email' => 'buyer@example.com',
                    'photoUrl' => 'https://lh3.googleusercontent.com/backfilled.png',
                ]],
            ]),
        ]);

        $this->postJson('/auth/firebase', ['id_token' => 'google-token'])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'avatar' => 'https://lh3.googleusercontent.com/backfilled.png',
        ]);
        $this->assertAuthenticatedAs($user);
    }

    public function test_avatar_url_rejects_unsafe_values(): void
    {
        $user = User::factory()->create(['avatar' => 'javascript:alert(1)']);

        $this->assertNull($user->avatarUrl());
    }

    public function test_auth_pages_do_not_render_onboarding_or_old_dashboard_promos(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Continue with Google')
            ->assertSee('data-google-error', false)
            ->assertSee('href="'.asset('images/favicon.svg').'"', false)
            ->assertSee('New here?')
            ->assertSee('Create an account')
            ->assertSee('Ready when you are')
            ->assertSee('lg:min-h-screen')
            ->assertSee('Give your store a')
            ->assertSee('bigger world.')
            ->assertDontSee('around-the-world.svg')
            ->assertDontSee('Advanced API dashboard')
            ->assertDontSee('open /app')
            ->assertDontSee('Getting started in 3 steps')
            ->assertDontSee('14-day');

        $this->get(route('register'))
            ->assertOk()
            ->assertSee('7-day free trial')
            ->assertSee('Ready when you are')
            ->assertSee('lg:min-h-screen')
            ->assertSee('Give your store a')
            ->assertSee('bigger world.')
            ->assertDontSee('around-the-world.svg')
            ->assertDontSee('14-day')
            ->assertDontSee('Getting started in 3 steps')
            ->assertDontSee('Advanced API dashboard');
    }

    public function test_home_renders_google_signin_popup_for_guests(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('data-auth-popup', false)
            ->assertSee('data-auth-popup-error', false)
            ->assertSee('data-guest-sign-in', false)
            ->assertSee('data-guest-trial', false)
            ->assertSee('data-firebase-api-key', false)
            ->assertSee('Continue as a EuroCross user')
            ->assertSee('Continue with Google');
    }

    public function test_home_shows_avatar_and_dashboard_for_authenticated_users(): void
    {
        $avatar = 'https://lh3.googleusercontent.com/avatar.png';
        $user = User::factory()->create([
            'name' => 'Amazon Buyer',
            'avatar' => $avatar,
        ]);

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertSee('data-account-dashboard', false)
            ->assertSee('href="'.route('dashboard').'"', false)
            ->assertSee('src="'.$avatar.'"', false)
            ->assertSee('Dashboard')
            ->assertDontSee('data-guest-sign-in', false)
            ->assertDontSee('data-guest-trial', false)
            ->assertDontSee('data-auth-popup', false);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('src="'.$avatar.'"', false);
    }

    public function test_home_does_not_render_google_signin_popup_for_authenticated_users(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertDontSee('data-auth-popup', false)
            ->assertDontSee('Continue as a EuroCross user');
    }

    public function test_home_loads_google_one_tap_when_client_id_is_configured(): void
    {
        config()->set('services.google.client_id', 'test-client.apps.googleusercontent.com');

        $this->get('/')
            ->assertOk()
            ->assertSee('https://accounts.google.com/gsi/client', false)
            ->assertSee('data-google-client-id="test-client.apps.googleusercontent.com"', false);
    }

    public function test_home_skips_google_one_tap_without_client_id(): void
    {
        config()->set('services.google.client_id', null);

        $this->get('/')
            ->assertOk()
            ->assertDontSee('https://accounts.google.com/gsi/client', false);
    }

    public function test_connect_start_rejects_private_site_url(): void
    {
        $this->get('/connect/start?'.http_build_query([
            'site_url' => 'https://127.0.0.1',
            'site_name' => 'Local Shop',
            'return_url' => 'https://127.0.0.1/wp-admin/admin-post.php',
            'state' => 'wp-state',
        ]))->assertSessionHasErrors('site_url');
    }

    public function test_connect_start_rejects_return_url_on_another_host(): void
    {
        $this->get('/connect/start?'.http_build_query([
            'site_url' => 'https://shop.example.com',
            'site_name' => 'Shop',
            'return_url' => 'https://evil.example.com/wp-admin/admin-post.php',
            'state' => 'wp-state',
        ]))->assertSessionHasErrors('return_url');
    }

    public function test_connect_code_can_be_exchanged_once_for_key_and_site_registration(): void
    {
        $connection = $this->approveConnection();

        $response = $this->postJson('/eurocross/connect/exchange', [
            'code' => $connection['code'],
            'site_url' => $connection['site_url'],
            'state' => $connection['state'],
        ])->assertOk()->assertJson(['success' => true]);

        $token = (string) $response->json('data.api_key');

        $this->assertNotSame('', $token);
        $response->assertJsonPath('data.plan', 'starter')
            ->assertJsonPath('data.allowed_languages.0.code', 'en')
            ->assertJsonCount(3, 'data.allowed_languages');

        $this->assertDatabaseHas('eurocross_api_keys', [
            'provider' => 'eurocross',
            'key_hash' => hash('sha256', $token),
            'active' => 1,
        ]);
        $this->assertDatabaseHas('eurocross_connected_sites', [
            'site_url' => $connection['site_url'],
        ]);
    }

    public function test_connect_code_cannot_be_replayed(): void
    {
        $connection = $this->approveConnection();
        $payload = [
            'code' => $connection['code'],
            'site_url' => $connection['site_url'],
            'state' => $connection['state'],
        ];

        $this->postJson('/eurocross/connect/exchange', $payload)->assertOk();

        $this->postJson('/eurocross/connect/exchange', $payload)
            ->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    public function test_connect_code_cannot_be_exchanged_by_another_site(): void
    {
        $connection = $this->approveConnection();

        $this->postJson('/eurocross/connect/exchange', [
            'code' => $connection['code'],
            'site_url' => 'https://evil.example.com',
            'state' => $connection['state'],
        ])->assertStatus(422)->assertJson(['success' => false]);

        $this->assertDatabaseCount('eurocross_connected_sites', 0);
        $this->assertDatabaseCount('eurocross_api_keys', 0);
    }

    public function test_unauthenticated_connect_request_redirects_to_login_and_resumes_after_sign_in(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $this->get('/connect/start?'.http_build_query([
            'site_url' => 'https://shop.example.com',
            'site_name' => 'Shop',
            'return_url' => 'https://shop.example.com/wp-admin/admin-post.php',
            'state' => 'wp-state',
        ]))->assertRedirect(route('connect.confirm'));

        $this->get(route('connect.confirm'))->assertRedirect(route('login'));

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('connect.confirm'));

        $this->get(route('connect.confirm'))->assertOk()->assertSee('Connect this store');
    }

    /**
     * @return array{code: string, state: string, site_url: string}
     */
    private function approveConnection(string $siteUrl = 'https://shop.example.com', string $state = 'wp-state'): array
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/connect/start?'.http_build_query([
            'site_url' => $siteUrl,
            'site_name' => 'Shop',
            'return_url' => $siteUrl.'/wp-admin/admin-post.php',
            'state' => $state,
        ]))->assertRedirect(route('connect.confirm'));

        $this->get(route('connect.confirm'))->assertOk()->assertSee($siteUrl);

        $response = $this->post(route('connect.approve'), ['confirm' => '1']);
        $response->assertRedirect();

        parse_str((string) parse_url((string) $response->headers->get('Location'), PHP_URL_QUERY), $query);

        $this->assertSame($siteUrl, (string) ($query['site_url'] ?? ''));

        return [
            'code' => (string) ($query['code'] ?? ''),
            'state' => (string) ($query['state'] ?? ''),
            'site_url' => (string) ($query['site_url'] ?? $siteUrl),
        ];
    }
}
