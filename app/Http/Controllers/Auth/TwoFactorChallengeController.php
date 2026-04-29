<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\VerifyTwoFactorCodeRequest;
use App\Models\TwoFactorChallenge;
use App\Services\Auth\TwoFactorChallengeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TwoFactorChallengeController extends Controller
{
    public function __construct(private readonly TwoFactorChallengeService $twoFactorService)
    {
    }

    public function show(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login')
                ->with('error', 'Your verification session expired. Please sign in again.');
        }

        if (! $this->twoFactorService->requiresTwoFactor($user)) {
            return redirect()->route('dashboard');
        }

        $pendingUserId = (int) $request->session()->get('two_factor.user_id', 0);
        $pending = (bool) $request->session()->get('two_factor.pending', false);
        $verified = (bool) $request->session()->get('two_factor.verified', false);

        if ($verified && $pendingUserId === (int) $user->id) {
            return redirect()->route('dashboard');
        }

        if (! $pending || $pendingUserId !== (int) $user->id) {
            return $this->expirePendingSession($request);
        }

        $pendingChallengeId = (int) $request->session()->get('two_factor.challenge_id', 0);
        if ($pendingChallengeId <= 0) {
            return $this->expirePendingSession($request);
        }

        $activeChallenge = $this->twoFactorService->currentActiveChallenge($user);

        if (! $activeChallenge instanceof TwoFactorChallenge) {
            return $this->expirePendingSession($request);
        }

        if ((int) $activeChallenge->id !== $pendingChallengeId || $activeChallenge->expires_at?->isPast()) {
            return $this->expirePendingSession($request);
        }

        return Inertia::render('Auth/TwoFactorChallenge', [
            'email' => $user->email,
            'expiresInSeconds' => $activeChallenge
                ? max(0, now()->diffInSeconds($activeChallenge->expires_at, false))
                : 0,
            'status' => session('error') ?: session('status'),
            'statusType' => session()->has('error') ? 'error' : 'success',
        ]);
    }

    public function verify(VerifyTwoFactorCodeRequest $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user || ! $this->twoFactorService->requiresTwoFactor($user)) {
            return $this->expirePendingSession($request);
        }

        $this->ensureNotRateLimited($request, 'verify', (int) config('auth.two_factor.rate_limit.verify_max_attempts', 10), (int) config('auth.two_factor.rate_limit.verify_decay_seconds', 60));
        RateLimiter::hit($this->rateLimitKey($request, 'verify'), (int) config('auth.two_factor.rate_limit.verify_decay_seconds', 60));

        $pendingUserId = (int) $request->session()->get('two_factor.user_id', 0);
        $pending = (bool) $request->session()->get('two_factor.pending', false);

        if (! $pending || $pendingUserId !== (int) $user->id) {
            return $this->expirePendingSession($request);
        }

        $result = $this->twoFactorService->verifyCode($user, (string) $request->validated('code'));

        if (! $result['ok']) {
            if (in_array((string) $result['reason'], ['expired', 'locked', 'missing'], true)) {
                return $this->expirePendingSession($request);
            }

            $message = match ($result['reason']) {
                'expired' => 'Your verification code has expired. Please request a new code.',
                'locked' => 'Maximum verification attempts reached. Please request a new code.',
                'missing' => 'No active verification challenge was found. Please request a new code.',
                default => 'Invalid verification code. Please try again.',
            };

            throw ValidationException::withMessages([
                'code' => $message,
            ]);
        }

        RateLimiter::clear($this->rateLimitKey($request, 'verify'));

        $remember = (bool) $request->session()->get('two_factor.remember', false);

        Auth::login($user, $remember);

        $request->session()->regenerate();
        $request->session()->put([
            'two_factor.pending' => false,
            'two_factor.verified' => true,
            'two_factor.user_id' => (int) $user->id,
            'two_factor.verified_at' => now()->toIso8601String(),
        ]);
        $request->session()->forget([
            'two_factor.remember',
            'two_factor.challenge_id',
            'two_factor.pending_expires_at',
        ]);

        return redirect()->route('dashboard');
    }

    public function resend(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user || ! $this->twoFactorService->requiresTwoFactor($user)) {
            return $this->expirePendingSession($request);
        }

        $this->ensureNotRateLimited($request, 'resend', (int) config('auth.two_factor.rate_limit.resend_max_attempts', 3), (int) config('auth.two_factor.rate_limit.resend_decay_seconds', 300));
        RateLimiter::hit($this->rateLimitKey($request, 'resend'), (int) config('auth.two_factor.rate_limit.resend_decay_seconds', 300));

        $pending = (bool) $request->session()->get('two_factor.pending', false);
        $pendingUserId = (int) $request->session()->get('two_factor.user_id', 0);

        if (! $pending || $pendingUserId !== (int) $user->id) {
            return $this->expirePendingSession($request);
        }

        $challengeResult = $this->twoFactorService->issueChallenge(
            user: $user,
            ipAddress: $request->ip(),
            userAgent: (string) $request->userAgent(),
            isResend: true,
        );

        $request->session()->put([
            'two_factor.pending' => true,
            'two_factor.verified' => false,
            'two_factor.user_id' => (int) $user->id,
            'two_factor.challenge_id' => (int) $challengeResult['challenge']->id,
            'two_factor.pending_expires_at' => optional($challengeResult['challenge']->expires_at)?->toIso8601String(),
        ]);

        if (! $challengeResult['delivery_ok']) {
            return back()->with('error', 'We could not send the verification email. Please try again.');
        }

        return back()->with('status', 'A new verification code has been sent to your email address.');
    }

    public function cancel(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->forget([
            'two_factor.pending',
            'two_factor.verified',
            'two_factor.user_id',
            'two_factor.verified_at',
            'two_factor.remember',
            'two_factor.challenge_id',
            'two_factor.pending_expires_at',
        ]);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Sign in was cancelled.');
    }

    private function expirePendingSession(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->forget([
            'two_factor.pending',
            'two_factor.verified',
            'two_factor.user_id',
            'two_factor.verified_at',
            'two_factor.remember',
            'two_factor.challenge_id',
            'two_factor.pending_expires_at',
        ]);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('error', 'Your verification session expired. Please sign in again.');
    }

    private function ensureNotRateLimited(Request $request, string $operation, int $maxAttempts, int $decaySeconds): void
    {
        $key = $this->rateLimitKey($request, $operation);

        if (! RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return;
        }

        $seconds = RateLimiter::availableIn($key);

        throw ValidationException::withMessages([
            'code' => "Too many {$operation} requests. Please try again in {$seconds} seconds.",
        ]);
    }

    private function rateLimitKey(Request $request, string $operation): string
    {
        $userId = (int) ($request->user()?->id ?? 0);

        return 'two-factor:'.$operation.':'.$userId.'|'.$request->ip();
    }
}
