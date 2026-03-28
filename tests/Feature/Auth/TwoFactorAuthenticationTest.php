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

        $response->assertSessionHasErrors('code');
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

        $response->assertSessionHasErrors('code');
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
            'attempts' => 4,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
        ]);

        $this->post(route('two-factor.challenge.verify'), ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->post(route('two-factor.challenge.verify'), ['code' => '000000'])
            ->assertSessionHasErrors('code');

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

    public function test_resend_works_within_allowed_rules(): void
    {
        Mail::fake();

        $user = $this->createUser(UserRole::ADMIN, 'resend.user@gsc.local');

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->post(route('two-factor.challenge.resend'))
            ->assertSessionHas('status');

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
}
