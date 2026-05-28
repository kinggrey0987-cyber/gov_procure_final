<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    private const MAX_FAILED_LOGIN_ATTEMPTS = 5;

    private const FIRST_LOCKOUT_SECONDS = 300;

    private const ESCALATED_LOCKOUT_SECONDS = 900;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(bool $remember = false): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only('email', 'password'), $remember)) {
            RateLimiter::hit($this->throttleKey(), $this->currentLockoutSeconds());

            if (RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_FAILED_LOGIN_ATTEMPTS)) {
                $this->throwLockoutValidationException();
            }

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        Cache::forget($this->lockoutLevelKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_FAILED_LOGIN_ATTEMPTS)) {
            return;
        }

        $this->throwLockoutValidationException();
    }

    /**
     * Throw the login lockout validation exception and escalate future lockouts.
     *
     * @throws ValidationException
     */
    private function throwLockoutValidationException(): never
    {
        event(new Lockout($this));

        $seconds = max(RateLimiter::availableIn($this->throttleKey()), $this->currentLockoutSeconds());

        Cache::put($this->lockoutLevelKey(), 'escalated', now()->addDay());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the lockout duration for the current failed login sequence.
     */
    private function currentLockoutSeconds(): int
    {
        return Cache::has($this->lockoutLevelKey())
            ? self::ESCALATED_LOCKOUT_SECONDS
            : self::FIRST_LOCKOUT_SECONDS;
    }

    /**
     * Get the cache key used to track escalated login lockouts.
     */
    private function lockoutLevelKey(): string
    {
        return $this->throttleKey().'|lockout-level';
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
