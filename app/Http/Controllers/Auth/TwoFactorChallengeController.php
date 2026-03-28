<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\VerifyTwoFactorCodeRequest;
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
            return redirect()->route('login');
        }

        if (! $this->twoFactorService->requiresTwoFactor($user)) {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        $pendingUserId = (int) $request->session()->get('two_factor.user_id', 0);
        $pending = (bool) $request->session()->get('two_factor.pending', false);
        $verified = (bool) $request->session()->get('two_factor.verified', false);

        if ($verified && $pendingUserId === (int) $user->id) {
            return redirect()->intended(route('dashboard', absolute: false));
        }

        if (! $pending || $pendingUserId !== (int) $user->id) {
            $this->twoFactorService->issueChallenge(
                user: $user,
                ipAddress: $request->ip(),
                userAgent: (string) $request->userAgent(),
                isResend: false,
            );

            $request->session()->put([
                'two_factor.pending' => true,
                'two_factor.verified' => false,
                'two_factor.user_id' => (int) $user->id,
            ]);
        }

        $activeChallenge = $this->twoFactorService->currentActiveChallenge($user);

        return Inertia::render('Auth/TwoFactorChallenge', [
            'email' => $user->email,
            'expiresInSeconds' => $activeChallenge
                ? max(0, now()->diffInSeconds($activeChallenge->expires_at, false))
                : 0,
            'status' => session('status'),
        ]);
    }

    public function verify(VerifyTwoFactorCodeRequest $request): RedirectResponse
    {
        $this->ensureNotRateLimited($request, 'verify', (int) config('auth.two_factor.rate_limit.verify_max_attempts', 10), (int) config('auth.two_factor.rate_limit.verify_decay_seconds', 60));
        RateLimiter::hit($this->rateLimitKey($request, 'verify'), (int) config('auth.two_factor.rate_limit.verify_decay_seconds', 60));

        $user = $request->user();

        if (! $user || ! $this->twoFactorService->requiresTwoFactor($user)) {
            return redirect()->route('login');
        }

        $pendingUserId = (int) $request->session()->get('two_factor.user_id', 0);
        $pending = (bool) $request->session()->get('two_factor.pending', false);

        if (! $pending || $pendingUserId !== (int) $user->id) {
            return redirect()->route('two-factor.challenge.show');
        }

        $result = $this->twoFactorService->verifyCode($user, (string) $request->validated('code'));

        if (! $result['ok']) {
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
        $request->session()->forget('two_factor.remember');

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function resend(Request $request): RedirectResponse
    {
        $this->ensureNotRateLimited($request, 'resend', (int) config('auth.two_factor.rate_limit.resend_max_attempts', 3), (int) config('auth.two_factor.rate_limit.resend_decay_seconds', 300));
        RateLimiter::hit($this->rateLimitKey($request, 'resend'), (int) config('auth.two_factor.rate_limit.resend_decay_seconds', 300));

        $user = $request->user();

        if (! $user || ! $this->twoFactorService->requiresTwoFactor($user)) {
            return redirect()->route('login');
        }

        $this->twoFactorService->issueChallenge(
            user: $user,
            ipAddress: $request->ip(),
            userAgent: (string) $request->userAgent(),
            isResend: true,
        );

        $request->session()->put([
            'two_factor.pending' => true,
            'two_factor.verified' => false,
            'two_factor.user_id' => (int) $user->id,
        ]);

        return back()->with('status', 'A new verification code has been sent to your email address.');
    }

    public function cancel(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Sign in was cancelled.');
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
