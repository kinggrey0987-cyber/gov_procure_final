<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\TwoFactorChallengeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleOAuthController extends Controller
{
    public function __construct(private readonly TwoFactorChallengeService $twoFactorService)
    {
    }

    /**
     * Redirect user to Google for authentication.
     */
    public function redirect(): RedirectResponse
    {
        if (! $this->isConfigured()) {
            return redirect()->route('login')
                ->with('error', 'Google Sign-In is not configured. Please use email and password to log in.');
        }

        try {
            return \Laravel\Socialite\Facades\Socialite::driver('google')
                ->redirect();
        } catch (\Exception $e) {
            Log::error('Google OAuth redirect failed', ['error' => $e->getMessage()]);

            return redirect()->route('login')
                ->with('error', 'Google Sign-In is not configured. Please use email and password to log in.');
        }
    }

    /**
     * Handle Google OAuth callback.
     * Only allows existing internal users (not PUBLIC).
     */
    public function callback(Request $request): RedirectResponse
    {
        if (! $this->isConfigured()) {
            return redirect()->route('login')
                ->with('error', 'Google Sign-In is not configured. Please use email and password to log in.');
        }

        if ((string) $request->query('error') === 'access_denied') {
            return redirect()->route('login')
                ->with('error', 'Google login was cancelled. Please try again.');
        }

        try {
            $googleUser = \Laravel\Socialite\Facades\Socialite::driver('google')->user();
        } catch (\Exception $e) {
            Log::error('Google OAuth callback failed', ['error' => $e->getMessage()]);

            return redirect()->route('login')
                ->with('error', 'Google authentication failed. Please try again or use email and password.');
        }

        if (! $googleUser->getEmail()) {
            return redirect()->route('login')
                ->with('error', 'Could not retrieve your email from Google. Please try again.');
        }

        $normalizedEmail = Str::lower(trim((string) $googleUser->getEmail()));

        $user = User::query()
            ->with('role')
            ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
            ->first();

        if (! $user) {
            return redirect()->route('login')
                ->with('error', 'This Google account is not authorized for this system. Please contact your administrator.');
        }

        if (! $this->isAllowedRole($user)) {
            return redirect()->route('login')
                ->with('error', 'Your account does not have access to this system.');
        }

        $userRole = optional($user->role)->code;
        if ($userRole === 'PUBLIC') {
            return redirect()->route('login')
                ->with('error', 'Public accounts cannot access this system. Please log in with your internal email.');
        }

        $this->updateGoogleLinkage($user, $googleUser);

        // Mirror the existing password login flow: authenticate first, then continue to 2FA.
        Auth::login($user, false);

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
                'two_factor.remember' => false,
            ]);

            if (! $challengeResult['delivery_ok']) {
                return redirect()->route('two-factor.challenge.show')
                    ->with('error', 'We could not send the verification email. Please try again.');
            }

            return redirect()->route('two-factor.challenge.show')
                ->with('status', 'A verification code was sent to your email address.');
        }

        Auth::login($user, remember: false);

        $request->session()->put([
            'two_factor.pending' => false,
            'two_factor.verified' => true,
            'two_factor.user_id' => (int) $user->id,
        ]);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Check if Google OAuth is configured.
     */
    private function isConfigured(): bool
    {
        return ! empty(config('services.google.client_id'))
            && ! empty(config('services.google.client_secret'))
            && ! empty(config('services.google.redirect'));
    }

    /**
     * Check if user's role is allowed for internal login.
     */
    private function isAllowedRole(User $user): bool
    {
        $role = optional($user->role)->code;

        $allowedRoles = [
            'ADMIN',
            'OFFICE_USER',
            'PROCUREMENT_OFFICER',
            'GSO_OFFICER',
            'EXECUTIVE_OFFICER',
        ];

        return in_array($role, $allowedRoles, true);
    }

    /**
     * Update user's Google linkage information.
     */
    private function updateGoogleLinkage(User $user, $googleUser): void
    {
        $user->forceFill([
            'google_id' => $googleUser->getId(),
            'google_avatar' => $googleUser->getAvatar(),
            'google_linked_at' => now(),
        ])->save();
    }
}

