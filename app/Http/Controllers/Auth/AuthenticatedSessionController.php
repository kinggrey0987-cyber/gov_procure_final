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
                'two_factor.remember' => $request->boolean('remember'),
            ]);

            return redirect()->route('two-factor.challenge.show')
                ->with('status', 'A verification code was sent to your email address.');
        }

        Auth::login($user, $request->boolean('remember'));

        $request->session()->put([
            'two_factor.pending' => false,
            'two_factor.verified' => true,
            'two_factor.user_id' => (int) $user->id,
        ]);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
