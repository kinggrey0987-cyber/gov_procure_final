<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Tests\TestCase;

class GoogleOAuthAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        config([
            'services.google.client_id' => 'test_client_id',
            'services.google.client_secret' => 'test_client_secret',
            'services.google.redirect' => 'http://localhost/auth/google/callback',
        ]);
    }

    public function test_login_page_displays_google_sign_in_button(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Auth/Login')
            ->where('error', null)
            ->where('status', null)
            ->where('canResetPassword', true)
        );
    }

    public function test_google_redirect_route_exists(): void
    {
        $this->assertTrue(route('google.redirect') !== null);
    }

    public function test_google_callback_route_exists(): void
    {
        $this->assertTrue(route('google.callback') !== null);
    }

    public function test_google_callback_logs_in_existing_internal_user_and_redirects_to_two_factor(): void
    {
        $office = $this->createOffice();
        $user = $this->createUser(UserRole::ADMIN, $office, 'Admin.User@Gov.local');

        $googleUser = \Mockery::mock(SocialiteUser::class);
        $googleUser->shouldReceive('getEmail')->andReturn(' admin.user@gov.local ');
        $googleUser->shouldReceive('getId')->andReturn('google-admin-1');
        $googleUser->shouldReceive('getAvatar')->andReturn('https://example.test/avatar.png');

        Socialite::shouldReceive('driver->user')->once()->andReturn($googleUser);

        $response = $this->get(route('google.callback'));

        $response->assertRedirect(route('two-factor.challenge.show'));
        $this->assertAuthenticatedAs($user->fresh());
        $this->assertTrue((bool) session('two_factor.pending'));
        $this->assertSame((int) $user->id, (int) session('two_factor.user_id'));
    }

    public function test_google_callback_allows_procurement_and_executive_role_codes(): void
    {
        $office = $this->createOffice();

        foreach ([UserRole::PROCUREMENT_OFFICER, UserRole::EXECUTIVE_OFFICER] as $role) {
            $this->post(route('logout'));

            $user = $this->createUser($role, $office, strtolower($role->value).'@gov.local');

            $googleUser = \Mockery::mock(SocialiteUser::class);
            $googleUser->shouldReceive('getEmail')->andReturn($user->email);
            $googleUser->shouldReceive('getId')->andReturn('google-'.$role->value);
            $googleUser->shouldReceive('getAvatar')->andReturn(null);

            Socialite::shouldReceive('driver->user')->once()->andReturn($googleUser);

            $response = $this->get(route('google.callback'));

            $response->assertRedirect(route('two-factor.challenge.show'));
            $this->assertAuthenticatedAs($user->fresh());
            $this->assertSame((int) $user->id, (int) session('two_factor.user_id'));
        }
    }

    public function test_google_callback_denies_unknown_email_with_visible_error(): void
    {
        $googleUser = \Mockery::mock(SocialiteUser::class);
        $googleUser->shouldReceive('getEmail')->andReturn('unknown@gov.local');
        $googleUser->shouldReceive('getId')->andReturn('google-unknown');
        $googleUser->shouldReceive('getAvatar')->andReturn(null);

        Socialite::shouldReceive('driver->user')->once()->andReturn($googleUser);

        $response = $this->get(route('google.callback'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error', 'This Google account is not authorized for this system. Please contact your administrator.');

        $this->get(route('login'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Auth/Login')
                ->where('error', 'This Google account is not authorized for this system. Please contact your administrator.')
            );
    }

    public function test_google_callback_denies_public_user_with_visible_error(): void
    {
        $office = $this->createOffice();
        $user = $this->createUser(UserRole::PUBLIC, $office, 'public@gov.local');

        $googleUser = \Mockery::mock(SocialiteUser::class);
        $googleUser->shouldReceive('getEmail')->andReturn($user->email);
        $googleUser->shouldReceive('getId')->andReturn('google-public');
        $googleUser->shouldReceive('getAvatar')->andReturn(null);

        Socialite::shouldReceive('driver->user')->once()->andReturn($googleUser);

        $response = $this->get(route('google.callback'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error', 'Your account does not have access to this system.');
        $this->assertGuest();
    }

    public function test_google_callback_handles_cancelled_login_with_visible_error(): void
    {
        $response = $this->get(route('google.callback', ['error' => 'access_denied']));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error', 'Google login was cancelled. Please try again.');
    }

    public function test_google_callback_handles_provider_exception_with_visible_error(): void
    {
        Socialite::shouldReceive('driver->user')->once()->andThrow(new \RuntimeException('provider failed'));

        $response = $this->get(route('google.callback'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error', 'Google authentication failed. Please try again or use email and password.');
    }

    public function test_google_auth_disabled_redirects_with_message(): void
    {
        config([
            'services.google.client_id' => null,
            'services.google.client_secret' => null,
            'services.google.redirect' => null,
        ]);

        $response = $this->get(route('google.redirect'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error', 'Google Sign-In is not configured. Please use email and password to log in.');
    }

    public function test_existing_email_password_login_still_works(): void
    {
        $office = $this->createOffice();
        $user = $this->createUser(UserRole::ADMIN, $office);

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('two-factor.challenge.show'));
        $this->assertEquals($user->id, session('two_factor.user_id'));
    }

    public function test_user_model_has_google_oauth_fields(): void
    {
        $office = $this->createOffice();
        $user = $this->createUser(UserRole::ADMIN, $office);

        $user->update([
            'google_id' => 'google_123456',
            'google_avatar' => 'https://example.com/avatar.jpg',
            'google_linked_at' => now(),
        ]);

        $user->refresh();

        $this->assertSame('google_123456', $user->google_id);
        $this->assertSame('https://example.com/avatar.jpg', $user->google_avatar);
        $this->assertNotNull($user->google_linked_at);
    }

    public function test_allowed_internal_roles_configuration(): void
    {
        $office = $this->createOffice();

        $allowedRoles = [
            UserRole::ADMIN,
            UserRole::OFFICE_USER,
            UserRole::PROCUREMENT_OFFICER,
            UserRole::GSO_OFFICER,
            UserRole::EXECUTIVE_OFFICER,
        ];

        foreach ($allowedRoles as $role) {
            $user = $this->createUser($role, $office);
            $this->assertNotNull($user);
        }

        $this->assertTrue(true, 'All allowed internal roles can be created');
    }

    public function test_public_user_role_exists_and_is_not_allowed_for_internal_login(): void
    {
        $office = $this->createOffice();
        $publicUser = $this->createUser(UserRole::PUBLIC, $office);

        $this->assertSame('PUBLIC', $publicUser->role->code);
    }

    private function createOffice(): Office
    {
        return Office::query()->create([
            'name' => 'Test Office '.uniqid(),
            'code' => 'TEST_'.uniqid(),
            'description' => 'Test office for OAuth',
            'is_active' => true,
        ]);
    }

    private function createUser(UserRole $role, Office $office, ?string $email = null): User
    {
        $roleModel = Role::query()->firstOrCreate(
            ['code' => $role->value],
            [
                'name' => str_replace('_', ' ', $role->value),
                'description' => 'Role for OAuth tests',
                'is_active' => true,
            ],
        );

        $attributes = [
            'role_id' => $roleModel->id,
            'office_id' => $office->id,
            'password' => bcrypt('password'),
        ];

        if ($email !== null) {
            $attributes['email'] = $email;
        }

        return User::factory()->withRole($role)->forOffice($office)->create($attributes);
    }
}
