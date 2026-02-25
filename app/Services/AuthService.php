<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Validation\ValidationException;

class AuthService
{
    /**
     * Authenticate user and return token.
     */
    public function login(array $credentials): array
    {
        $loginField = !empty($credentials['email']) ? 'email' : 'employee_id';
        $identifier = $credentials[$loginField] ?? null;
        $password   = $credentials['password'] ?? null;
        $remember   = (bool) ($credentials['remember'] ?? false);

        if (!$identifier || !$password) {
            throw ValidationException::withMessages([
                $loginField => ['Email or Employee ID and password are required.'],
            ]);
        }

        $throttleKey = $this->throttleKey($loginField, $identifier, request()->ip());

        $this->ensureIsNotRateLimited($throttleKey);

        $attemptCredentials = [
            $loginField => $identifier,
            'password'  => $password,
        ];

        if (!Auth::attempt($attemptCredentials, $remember)) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'password' => ['Invalid credentials.'],
            ]);
        }

        RateLimiter::clear($throttleKey);

        $user  = Auth::user();
        $token = null;

        if (method_exists($user, 'createToken')) {
            $token = $user->createToken('auth_token')->plainTextToken;
        }

        return [
            'user'  => $user,
            'token' => $token,
        ];
    }

    /**
     * Logout the current user.
     */
    public function logout(): void
    {
        $user = auth()->user();

        if ($user && method_exists($user, 'currentAccessToken') && $user->currentAccessToken()) {
            // Sanctum token-based logout
            $user->currentAccessToken()->delete();
        } else {
            // Session-based logout
            Auth::logout();

            if (request()->hasSession()) {
                request()->session()->invalidate();
                request()->session()->regenerateToken();
            }
        }
    }

    /* ─── Private helpers ─── */

    protected function throttleKey(string $field, string $identifier, ?string $ip): string
    {
        $identifier = Str::lower(trim($identifier));
        return $field . '|' . $identifier . '|' . ($ip ?: 'unknown');
    }

    protected function ensureIsNotRateLimited(string $throttleKey): void
    {
        if (!RateLimiter::tooManyAttempts($throttleKey, 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($throttleKey);

        throw ValidationException::withMessages([
            'throttle' => [trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ])],
        ]);
    }
}
