<?php

namespace App\Http\Controllers;

use App\Services\Auth\TwoFactorChallengeService;
use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function __construct(private readonly TwoFactorChallengeService $twoFactorService)
    {
    }

    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
            'twoFactor' => [
                'required' => $this->twoFactorService->requiresTwoFactor($request->user()),
                'policy' => 'mandatory_internal_roles',
            ],
            'pendingEmailChange' => $request->session()->get('profile.email_change.pending'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        if (strtolower((string) $validated['email']) !== strtolower((string) $user->email)) {
            $user->forceFill([
                'name' => $validated['name'],
            ])->save();

            $challengeResult = $this->twoFactorService->issueChallenge(
                user: $user,
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
                purpose: 'email_change',
            );

            $request->session()->put('profile.email_change.pending', [
                'email' => $validated['email'],
                'challenge_id' => (int) $challengeResult['challenge']->id,
                'expires_at' => optional($challengeResult['challenge']->expires_at)?->toIso8601String(),
            ]);

            return Redirect::route('profile.edit')->with('status', 'email-change-code-sent');
        }

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return Redirect::route('profile.edit');
    }

    public function confirmEmailChange(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        $pending = $request->session()->get('profile.email_change.pending');
        if (! is_array($pending) || empty($pending['email'])) {
            throw ValidationException::withMessages([
                'code' => 'No pending email change request was found. Please update your email again.',
            ]);
        }

        validator(
            ['email' => (string) $pending['email']],
            ['email' => ['required', 'email', 'max:255', 'unique:users,email,'.$request->user()->id]],
        )->validate();

        $result = $this->twoFactorService->verifyCode($request->user(), (string) $validated['code']);
        if (! (bool) $result['ok']) {
            throw ValidationException::withMessages([
                'code' => match ((string) $result['reason']) {
                    'expired' => 'The verification code has expired. Please save the new email again to request a fresh code.',
                    'locked' => 'Too many invalid verification attempts. Please save the new email again to request a fresh code.',
                    default => 'The verification code is invalid.',
                },
            ]);
        }

        $user = $request->user();
        $user->forceFill([
            'email' => (string) $pending['email'],
            'email_verified_at' => null,
        ])->save();

        $request->session()->forget('profile.email_change.pending');

        return Redirect::route('profile.edit')->with('status', 'email-change-confirmed');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
