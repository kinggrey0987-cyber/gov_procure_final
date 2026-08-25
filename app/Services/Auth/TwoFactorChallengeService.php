<?php

namespace App\Services\Auth;

use App\Enums\UserRole;
use App\Mail\TwoFactorCodeMail;
use App\Models\TwoFactorChallenge;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class TwoFactorChallengeService
{
    public function __construct(private readonly AuditLogService $auditLogService)
    {
    }

    public function requiresTwoFactor(User $user): bool
    {
        if (! (bool) config('auth.two_factor.enabled', true)) {
            return false;
        }

        return in_array($user->roleCode(), [
            UserRole::ADMIN->value,
            UserRole::OFFICE_USER->value,
            UserRole::PROCUREMENT_OFFICER->value,
            UserRole::GSO_OFFICER->value,
            UserRole::EXECUTIVE_OFFICER->value,
        ], true);
    }

    /**
     * @return array{challenge: TwoFactorChallenge, delivery_ok: bool, delivery_error: string|null, delivery_duration_ms: int}
     */
    public function issueChallenge(User $user, ?string $ipAddress, ?string $userAgent, bool $isResend = false, string $purpose = 'login'): array
    {
        TwoFactorChallenge::query()
            ->where('user_id', $user->id)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $code = $this->generateCode();
        $expiresInMinutes = (int) config('auth.two_factor.code_expiration_minutes', 10);

        $challenge = TwoFactorChallenge::query()->create([
            'user_id' => $user->id,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes($expiresInMinutes),
            'consumed_at' => null,
            'attempts' => 0,
            'ip_address' => $ipAddress,
            'user_agent' => mb_substr((string) $userAgent, 0, 512),
        ]);

        $deliveryFailed = false;
        $deliveryError = null;
        $mailStartedAt = microtime(true);

        try {
            Mail::to($user->email)->send(new TwoFactorCodeMail($user, $code, $expiresInMinutes, $purpose));
        } catch (Throwable $throwable) {
            $deliveryFailed = true;
            $deliveryError = mb_substr($throwable->getMessage(), 0, 500);

            $deliveryDurationMs = (int) round((microtime(true) - $mailStartedAt) * 1000);

            report($throwable);

            Log::warning('auth.two_factor.delivery_failed', [
                'user_id' => (int) $user->id,
                'challenge_id' => (int) $challenge->id,
                'is_resend' => $isResend,
                'duration_ms' => $deliveryDurationMs,
                'exception_class' => $throwable::class,
                'exception_message' => $deliveryError,
            ]);
        }

        $deliveryDurationMs = (int) round((microtime(true) - $mailStartedAt) * 1000);

        if ($deliveryDurationMs >= 1500) {
            Log::warning('auth.two_factor.delivery_slow', [
                'user_id' => (int) $user->id,
                'challenge_id' => (int) $challenge->id,
                'is_resend' => $isResend,
                'duration_ms' => $deliveryDurationMs,
            ]);
        }

        $this->auditLogService->log(
            actor: $user,
            moduleName: 'Auth.TwoFactor',
            actionType: $isResend ? '2FA_CODE_RESENT' : '2FA_CODE_ISSUED',
            referenceType: User::class,
            referenceId: (int) $user->id,
            details: [
                'challenge_id' => (int) $challenge->id,
                'expires_at' => $challenge->expires_at?->toIso8601String(),
                'delivery_failed' => $deliveryFailed,
                'delivery_duration_ms' => $deliveryDurationMs,
                'purpose' => $purpose,
            ],
            logDomain: 'TECHNICAL',
            officeId: $user->office_id,
        );

        if ($deliveryFailed) {
            $this->auditLogService->log(
                actor: $user,
                moduleName: 'Auth.TwoFactor',
                actionType: '2FA_CODE_DELIVERY_FAILED',
                referenceType: User::class,
                referenceId: (int) $user->id,
                details: [
                    'challenge_id' => (int) $challenge->id,
                    'error' => $deliveryError,
                ],
                logDomain: 'TECHNICAL',
                officeId: $user->office_id,
            );
        }

        return [
            'challenge' => $challenge,
            'delivery_ok' => ! $deliveryFailed,
            'delivery_error' => $deliveryError,
            'delivery_duration_ms' => $deliveryDurationMs,
        ];
    }

    public function currentActiveChallenge(User $user): ?TwoFactorChallenge
    {
        return TwoFactorChallenge::query()
            ->where('user_id', $user->id)
            ->active()
            ->latest('id')
            ->first();
    }

    public function verifyCode(User $user, string $code): array
    {
        $maxAttempts = (int) config('auth.two_factor.max_attempts', 5);

        /** @var TwoFactorChallenge|null $challenge */
        $challenge = TwoFactorChallenge::query()
            ->where('user_id', $user->id)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if (! $challenge) {
            $this->logFailure($user, '2FA_VERIFY_FAILED_NO_CHALLENGE', null);

            return ['ok' => false, 'reason' => 'missing'];
        }

        if ($challenge->expires_at instanceof Carbon && $challenge->expires_at->isPast()) {
            $challenge->forceFill(['consumed_at' => now()])->save();
            $this->logFailure($user, '2FA_VERIFY_FAILED_EXPIRED', (int) $challenge->id);

            return ['ok' => false, 'reason' => 'expired'];
        }

        if ($challenge->attempts > $maxAttempts) {
            $challenge->forceFill(['consumed_at' => now()])->save();
            $this->logFailure($user, '2FA_VERIFY_FAILED_LOCKED', (int) $challenge->id);

            return ['ok' => false, 'reason' => 'locked'];
        }

        if (! Hash::check($code, $challenge->code_hash)) {
            $challenge->increment('attempts');

            $challenge->refresh();

            if ($challenge->attempts > $maxAttempts) {
                $challenge->forceFill(['consumed_at' => now()])->save();
                $this->logFailure($user, '2FA_VERIFY_FAILED_LOCKED', (int) $challenge->id);

                return ['ok' => false, 'reason' => 'locked'];
            }

            $this->logFailure($user, '2FA_VERIFY_FAILED_INVALID', (int) $challenge->id, [
                'attempts' => (int) $challenge->attempts,
            ]);

            return ['ok' => false, 'reason' => 'invalid'];
        }

        $challenge->forceFill([
            'consumed_at' => now(),
        ])->save();

        $this->auditLogService->log(
            actor: $user,
            moduleName: 'Auth.TwoFactor',
            actionType: '2FA_VERIFY_SUCCESS',
            referenceType: User::class,
            referenceId: (int) $user->id,
            details: [
                'challenge_id' => (int) $challenge->id,
            ],
            logDomain: 'TECHNICAL',
            officeId: $user->office_id,
        );

        return ['ok' => true, 'reason' => 'verified'];
    }

    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function logFailure(User $user, string $actionType, ?int $challengeId, array $details = []): void
    {
        $this->auditLogService->log(
            actor: $user,
            moduleName: 'Auth.TwoFactor',
            actionType: $actionType,
            referenceType: User::class,
            referenceId: (int) $user->id,
            details: array_merge($details, ['challenge_id' => $challengeId]),
            logDomain: 'TECHNICAL',
            officeId: $user->office_id,
        );
    }
}
