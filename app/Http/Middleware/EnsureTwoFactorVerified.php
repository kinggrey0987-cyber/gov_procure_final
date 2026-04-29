<?php

namespace App\Http\Middleware;

use App\Services\Auth\TwoFactorChallengeService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorVerified
{
    public function __construct(private readonly TwoFactorChallengeService $twoFactorService)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (app()->runningUnitTests() && (bool) config('auth.two_factor.testing_bypass', true)) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if (! $this->twoFactorService->requiresTwoFactor($user)) {
            return $next($request);
        }

        $pending = (bool) $request->session()->get('two_factor.pending', false);
        $verified = (bool) $request->session()->get('two_factor.verified', false);
        $sessionUserId = (int) $request->session()->get('two_factor.user_id', 0);

        if ($verified && $sessionUserId === (int) $user->id) {
            return $next($request);
        }

        if (! $pending || $sessionUserId !== (int) $user->id) {
            $challengeResult = $this->twoFactorService->issueChallenge(
                user: $user,
                ipAddress: $request->ip(),
                userAgent: (string) $request->userAgent(),
                isResend: false,
            );

            $request->session()->put([
                'two_factor.pending' => true,
                'two_factor.verified' => false,
                'two_factor.user_id' => (int) $user->id,
                'two_factor.challenge_id' => (int) $challengeResult['challenge']->id,
                'two_factor.pending_expires_at' => optional($challengeResult['challenge']->expires_at)?->toIso8601String(),
            ]);

            if (! $challengeResult['delivery_ok']) {
                $request->session()->flash('error', 'We could not send the verification email. Please try again.');
            } else {
                $request->session()->flash('status', 'A verification code was sent to your email address.');
            }
        }

        return redirect()->route('two-factor.challenge.show');
    }
}
