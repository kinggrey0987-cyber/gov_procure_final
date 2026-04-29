<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Auth\TwoFactorChallengeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function __construct(private readonly TwoFactorChallengeService $twoFactorService)
    {
    }

    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
            'error' => session('error'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate(false);

        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        $request->session()->regenerate();

        if ($this->twoFactorService->requiresTwoFactor($user)) {
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
                'two_factor.remember' => $request->boolean('remember'),
            ]);

            if (! $challengeResult['delivery_ok']) {
                return redirect()->route('two-factor.challenge.show')
                    ->with('error', 'We could not send the verification email. Please try again.');
            }

            return redirect()->route('two-factor.challenge.show')
                ->with('status', 'A verification code was sent to your email address.');
        }

        Auth::login($user, $request->boolean('remember'));

        $request->session()->put([
            'two_factor.pending' => false,
            'two_factor.verified' => true,
            'two_factor.user_id' => (int) $user->id,
        ]);
        $request->session()->forget([
            'two_factor.challenge_id',
            'two_factor.pending_expires_at',
            'two_factor.remember',
        ]);

        return redirect()->route('dashboard');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
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

        return redirect('/');
    }
}
