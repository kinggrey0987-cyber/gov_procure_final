<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Mail\TwoFactorCodeMail;
use App\Models\Office;
use App\Models\Role;
use App\Models\TwoFactorChallenge;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['auth.two_factor.testing_bypass' => false]);
    }

    public function test_login_triggers_two_factor_challenge_for_internal_users(): void
    {
        Mail::fake();

        $user = $this->createUser(UserRole::OFFICE_USER, 'office.user@gsc.local');

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('two-factor.challenge.show', absolute: false));
        $response->assertSessionHas('status', 'A verification code was sent to your email address.');
        $response->assertSessionMissing('error');
        $response->assertSessionHas('two_factor.challenge_id');
        $response->assertSessionHas('two_factor.pending_expires_at');

        $this->assertDatabaseCount('two_factor_challenges', 1);
        $this->assertAuthenticatedAs($user);

        Mail::assertSent(TwoFactorCodeMail::class, 1);
    }

    public function test_valid_code_completes_login(): void
    {
        Mail::fake();

        $user = $this->createUser(UserRole::ADMIN, 'admin.user@gsc.local');

        $rawCode = null;

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        Mail::assertSent(TwoFactorCodeMail::class, function (TwoFactorCodeMail $mail) use (&$rawCode): bool {
            $rawCode = $mail->code;

            return true;
        });

        $this->assertNotNull($rawCode);

        $response = $this->post(route('two-factor.challenge.verify'), [
            'code' => $rawCode,
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));

        $challenge = TwoFactorChallenge::query()->latest('id')->firstOrFail();
        $this->assertNotNull($challenge->consumed_at);

        $this->get('/dashboard')->assertRedirect(route('admin-tech.dashboard', absolute: false));
    }

    public function test_invalid_code_is_rejected(): void
    {
        Mail::fake();

        $user = $this->createUser(UserRole::GSO_OFFICER, 'gso.user@gsc.local');

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response = $this->post(route('two-factor.challenge.verify'), [
            'code' => '000001',
        ]);

        $response->assertSessionHasErrors('code');

        $challenge = TwoFactorChallenge::query()->latest('id')->firstOrFail();
        $this->assertSame(1, $challenge->attempts);
        $this->assertNull($challenge->consumed_at);
    }

    public function test_expired_code_is_rejected(): void
    {
        $user = $this->createUser(UserRole::PROCUREMENT_OFFICER, 'proc.user@gsc.local');

        $this->actingAs($user)->withSession([
            'two_factor.pending' => true,
            'two_factor.verified' => false,
            'two_factor.user_id' => $user->id,
        ]);

        TwoFactorChallenge::query()->create([
            'user_id' => $user->id,
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->subMinute(),
            'consumed_at' => null,
            'attempts' => 0,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
        ]);

        $response = $this->post(route('two-factor.challenge.verify'), [
            'code' => '123456',
        ]);

        $response->assertRedirect(route('login', absolute: false));
        $response->assertSessionHas('error', 'Your verification session expired. Please sign in again.');
        $this->assertGuest();
    }

    public function test_reused_code_is_rejected(): void
    {
        $user = $this->createUser(UserRole::EXECUTIVE_OFFICER, 'exec.user@gsc.local');

        $challenge = TwoFactorChallenge::query()->create([
            'user_id' => $user->id,
            'code_hash' => Hash::make('654321'),
            'expires_at' => now()->addMinutes(10),
            'consumed_at' => null,
            'attempts' => 0,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
        ]);

        $this->actingAs($user)->withSession([
            'two_factor.pending' => true,
            'two_factor.verified' => false,
            'two_factor.user_id' => $user->id,
        ]);

        $this->post(route('two-factor.challenge.verify'), [
            'code' => '654321',
        ])->assertRedirect(route('dashboard', absolute: false));

        $challenge->refresh();
        $this->assertNotNull($challenge->consumed_at);

        $this->withSession([
            'two_factor.pending' => true,
            'two_factor.verified' => false,
            'two_factor.user_id' => $user->id,
        ]);

        $response = $this->post(route('two-factor.challenge.verify'), [
            'code' => '654321',
        ]);

        $response->assertRedirect(route('login', absolute: false));
        $response->assertSessionHas('error', 'Your verification session expired. Please sign in again.');
        $this->assertGuest();
    }

    public function test_too_many_attempts_are_blocked(): void
    {
        $user = $this->createUser(UserRole::OFFICE_USER, 'attempt.user@gsc.local');

        $this->actingAs($user)->withSession([
            'two_factor.pending' => true,
            'two_factor.verified' => false,
            'two_factor.user_id' => $user->id,
        ]);

        TwoFactorChallenge::query()->create([
            'user_id' => $user->id,
            'code_hash' => Hash::make('222222'),
            'expires_at' => now()->addMinutes(10),
            'consumed_at' => null,
            'attempts' => 5,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
        ]);

        $this->post(route('two-factor.challenge.verify'), ['code' => '000000'])
            ->assertRedirect(route('login', absolute: false))
            ->assertSessionHas('error', 'Your verification session expired. Please sign in again.');

        $this->post(route('two-factor.challenge.verify'), ['code' => '000000'])
            ->assertRedirect(route('login', absolute: false))
            ->assertSessionHas('error', 'Your verification session expired. Please sign in again.');

        $latest = TwoFactorChallenge::query()->latest('id')->firstOrFail();
        $this->assertNotNull($latest->consumed_at);
    }

    public function test_verification_submission_rate_limit_is_enforced(): void
    {
        config([
            'auth.two_factor.rate_limit.verify_max_attempts' => 1,
            'auth.two_factor.rate_limit.verify_decay_seconds' => 120,
        ]);

        $user = $this->createUser(UserRole::OFFICE_USER, 'verify.rate@gsc.local');

        TwoFactorChallenge::query()->create([
            'user_id' => $user->id,
            'code_hash' => Hash::make('999999'),
            'expires_at' => now()->addMinutes(10),
            'consumed_at' => null,
            'attempts' => 0,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
        ]);

        $this->actingAs($user)->withSession([
            'two_factor.pending' => true,
            'two_factor.verified' => false,
            'two_factor.user_id' => $user->id,
        ]);

        $this->post(route('two-factor.challenge.verify'), ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->post(route('two-factor.challenge.verify'), ['code' => '000000'])
            ->assertSessionHasErrors('code');
    }

    public function test_five_invalid_two_factor_codes_trigger_short_lockout(): void
    {
        config([
            'auth.two_factor.rate_limit.failed_code_max_attempts' => 5,
            'auth.two_factor.rate_limit.failed_code_decay_seconds' => 300,
            'auth.two_factor.rate_limit.verify_max_attempts' => 20,
        ]);

        $user = $this->createUser(UserRole::OFFICE_USER, 'failed.codes@gsc.local');

        TwoFactorChallenge::query()->create([
            'user_id' => $user->id,
            'code_hash' => Hash::make('999999'),
            'expires_at' => now()->addMinutes(10),
            'consumed_at' => null,
            'attempts' => 0,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
        ]);

        $this->actingAs($user)->withSession([
            'two_factor.pending' => true,
            'two_factor.verified' => false,
            'two_factor.user_id' => $user->id,
        ]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('two-factor.challenge.verify'), ['code' => '000000'])
                ->assertSessionHasErrors('code');
        }

        $this->post(route('two-factor.challenge.verify'), ['code' => '999999'])
            ->assertSessionHasErrors('code');

        $this->assertAuthenticatedAs($user);
    }

    public function test_resend_works_within_allowed_rules(): void
    {
        Mail::fake();

        $user = $this->createUser(UserRole::ADMIN, 'resend.user@gsc.local');

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->post(route('two-factor.challenge.resend'))
            ->assertSessionHas('status', 'A new verification code has been sent to your email address.')
            ->assertSessionMissing('error');

        Mail::assertSent(TwoFactorCodeMail::class, 2);

        $activeCount = TwoFactorChallenge::query()
            ->where('user_id', $user->id)
            ->whereNull('consumed_at')
            ->count();

        $this->assertSame(1, $activeCount);
    }

    public function test_resend_rate_limit_is_enforced(): void
    {
        Mail::fake();

        config([
            'auth.two_factor.rate_limit.resend_max_attempts' => 1,
            'auth.two_factor.rate_limit.resend_decay_seconds' => 120,
        ]);

        $user = $this->createUser(UserRole::ADMIN, 'resend.rate@gsc.local');

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->post(route('two-factor.challenge.resend'))
            ->assertSessionHas('status');

        $this->post(route('two-factor.challenge.resend'))
            ->assertSessionHasErrors('code');
    }

    public function test_protected_routes_remain_blocked_before_verification(): void
    {
        Mail::fake();

        $user = $this->createUser(UserRole::OFFICE_USER, 'blocked.user@gsc.local');

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->get('/dashboard')
            ->assertRedirect(route('two-factor.challenge.show', absolute: false));
    }

    public function test_protected_routes_open_after_successful_verification(): void
    {
        $user = $this->createUser(UserRole::OFFICE_USER, 'open.user@gsc.local');

        TwoFactorChallenge::query()->create([
            'user_id' => $user->id,
            'code_hash' => Hash::make('112233'),
            'expires_at' => now()->addMinutes(10),
            'consumed_at' => null,
            'attempts' => 0,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
        ]);

        $this->actingAs($user)->withSession([
            'two_factor.pending' => true,
            'two_factor.verified' => false,
            'two_factor.user_id' => $user->id,
        ]);

        $this->post(route('two-factor.challenge.verify'), [
            'code' => '112233',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->get('/dashboard')->assertRedirect(route('office.dashboard', absolute: false));
    }

    public function test_two_factor_challenge_page_redirects_to_login_when_pending_session_is_missing(): void
    {
        Mail::fake();

        $user = $this->createUser(UserRole::OFFICE_USER, 'missing.pending@gsc.local');

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('two-factor.challenge.show', absolute: false));

        $this->flushTwoFactorSessionState();

        $this->get(route('two-factor.challenge.show'))
            ->assertRedirect(route('login', absolute: false))
            ->assertSessionHas('error', 'Your verification session expired. Please sign in again.');

        $this->assertGuest();
    }

    public function test_expired_challenge_redirects_cleanly_instead_of_forbidden(): void
    {
        $user = $this->createUser(UserRole::PROCUREMENT_OFFICER, 'expired.challenge@gsc.local');

        $challenge = TwoFactorChallenge::query()->create([
            'user_id' => $user->id,
            'code_hash' => Hash::make('123456'),
            'expires_at' => now()->subMinute(),
            'consumed_at' => null,
            'attempts' => 0,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
        ]);

        $this->actingAs($user)->withSession([
            'two_factor.pending' => true,
            'two_factor.verified' => false,
            'two_factor.user_id' => $user->id,
            'two_factor.challenge_id' => $challenge->id,
            'two_factor.pending_expires_at' => $challenge->expires_at?->toIso8601String(),
        ]);

        $this->post(route('two-factor.challenge.verify'), [
            'code' => '123456',
        ])
            ->assertRedirect(route('login', absolute: false))
            ->assertSessionHas('error', 'Your verification session expired. Please sign in again.');

        $this->assertGuest();
    }

    public function test_logout_clears_two_factor_pending_session_state(): void
    {
        $user = $this->createUser(UserRole::ADMIN, 'logout.clear@gsc.local');

        $this->actingAs($user)->withSession([
            'two_factor.pending' => true,
            'two_factor.verified' => false,
            'two_factor.user_id' => $user->id,
            'two_factor.challenge_id' => 123,
            'two_factor.pending_expires_at' => now()->addMinutes(10)->toIso8601String(),
            'two_factor.remember' => true,
        ]);

        $this->post('/logout')->assertRedirect('/');

        $this->assertGuest();
        $this->assertFalse((bool) session('two_factor.pending', false));
        $this->assertNull(session('two_factor.user_id'));
        $this->assertNull(session('two_factor.challenge_id'));
        $this->assertNull(session('two_factor.pending_expires_at'));
    }

    public function test_public_role_login_does_not_require_internal_two_factor_flow(): void
    {
        Mail::fake();

        $user = $this->createUser(UserRole::PUBLIC, 'public.user@gsc.local');

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));

        Mail::assertNothingSent();
    }

    public function test_security_audit_events_are_logged_for_two_factor_issue_and_failure(): void
    {
        Mail::fake();

        $user = $this->createUser(UserRole::OFFICE_USER, 'audit.user@gsc.local');

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('two-factor.challenge.show', absolute: false));

        $this->post(route('two-factor.challenge.verify'), [
            'code' => '000000',
        ])->assertSessionHasErrors('code');

        $this->assertDatabaseHas('audit_logs', [
            'module_name' => 'Auth.TwoFactor',
            'action_type' => '2FA_CODE_ISSUED',
            'reference_type' => User::class,
            'reference_id' => $user->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'module_name' => 'Auth.TwoFactor',
            'action_type' => '2FA_VERIFY_FAILED_INVALID',
            'reference_type' => User::class,
            'reference_id' => $user->id,
        ]);
    }

    public function test_login_does_not_crash_when_two_factor_mail_delivery_fails(): void
    {
        $user = $this->createUser(UserRole::OFFICE_USER, 'mail.fail@gsc.local');

        Mail::shouldReceive('to')->once()->andReturnSelf();
        Mail::shouldReceive('send')->once()->andThrow(new \RuntimeException('smtp timeout'));

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('two-factor.challenge.show', absolute: false));
        $response->assertSessionHas('error', 'We could not send the verification email. Please try again.');
        $response->assertSessionMissing('status');
        $this->assertAuthenticatedAs($user);

        $this->assertDatabaseCount('two_factor_challenges', 1);

        $activeChallenges = TwoFactorChallenge::query()
            ->where('user_id', $user->id)
            ->whereNull('consumed_at')
            ->count();

        $this->assertSame(1, $activeChallenges);

        $this->assertDatabaseHas('audit_logs', [
            'module_name' => 'Auth.TwoFactor',
            'action_type' => '2FA_CODE_DELIVERY_FAILED',
            'reference_type' => User::class,
            'reference_id' => $user->id,
        ]);
    }

    public function test_resend_delivery_failure_does_not_show_false_success_message(): void
    {
        $user = $this->createUser(UserRole::ADMIN, 'resend.fail@gsc.local');

        Mail::shouldReceive('to')->andReturnSelf();
        Mail::shouldReceive('send')->once()->andThrow(new \RuntimeException('smtp unavailable'));

        $this->actingAs($user)->withSession([
            'two_factor.pending' => true,
            'two_factor.verified' => false,
            'two_factor.user_id' => $user->id,
        ]);

        $response = $this->post(route('two-factor.challenge.resend'));

        $response->assertRedirect();
        $response->assertSessionHas('error', 'We could not send the verification email. Please try again.');
        $response->assertSessionMissing('status');

        $activeChallenges = TwoFactorChallenge::query()
            ->where('user_id', $user->id)
            ->whereNull('consumed_at')
            ->count();

        $this->assertSame(1, $activeChallenges);
    }

    public function test_direct_login_when_two_factor_is_disabled_via_config(): void
    {
        Mail::fake();
        config(['auth.two_factor.enabled' => false]);

        $user = $this->createUser(UserRole::OFFICE_USER, 'office.direct@gsc.local');

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseCount('two_factor_challenges', 0);
        Mail::assertNothingSent();
    }

    private function createUser(UserRole $role, string $email): User
    {
        $office = Office::query()->firstOrCreate([
            'code' => 'OFF_2FA',
        ], [
            'name' => '2FA Office',
            'description' => '2FA test office',
            'is_active' => true,
        ]);

        $roleModel = Role::query()->firstOrCreate([
            'code' => $role->value,
        ], [
            'name' => str_replace('_', ' ', $role->value),
            'description' => 'Role for 2FA tests',
            'is_active' => true,
        ]);

        return User::factory()
            ->withRole($role)
            ->forOffice($office)
            ->create([
                'email' => $email,
                'role_id' => $roleModel->id,
                'office_id' => $office->id,
            ]);
    }

    private function flushTwoFactorSessionState(): void
    {
        session()->forget([
            'two_factor.pending',
            'two_factor.verified',
            'two_factor.user_id',
            'two_factor.verified_at',
            'two_factor.remember',
            'two_factor.challenge_id',
            'two_factor.pending_expires_at',
        ]);
    }
}
