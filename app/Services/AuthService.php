<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\ValidationException;
use Throwable;

class AuthService
{
    public function __construct(
        private LoggerService $logger
    ) {}

    public function login(array $credentials): array
    {
        $loginField = !empty($credentials['email']) ? 'email' : 'employee_id';
        $identifier  = $credentials[$loginField] ?? null;
        $password    = $credentials['password'] ?? null;
        $remember    = (bool) ($credentials['remember'] ?? false);

        if (!$identifier || !$password) {
            throw ValidationException::withMessages([
                $loginField => ['Email or Employee ID and password are required.'],
            ]);
        }

        $throttleKey = $this->throttleKey($loginField, $identifier, request()->ip());

        $this->ensureIsNotRateLimited($throttleKey);

        try {
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

            $user = Auth::user();

            $token = null;
            if (method_exists($user, 'createToken')) {
                $token = $user->createToken('auth_token')->plainTextToken;
            }

            return [
                'user'  => $user,
                'token' => $token,
            ];
        } catch (Throwable $e) {
            throw $e;
        }
    }

    public function logout(): void
    {
        $user = auth()->user();

        if ($user && method_exists($user, 'currentAccessToken') && $user->currentAccessToken()) {
            $user->currentAccessToken()->delete();

            $this->logger->info('auth.logout.token_revoked', [
                'user_id' => $user->id,
                'ip' => request()->ip(),
            ]);
        } else {
            Auth::logout();
            request()->session()->invalidate();
            request()->session()->regenerateToken();

            $this->logger->info('auth.logout.session', [
                'ip' => request()->ip(),
            ]);
        }
    }

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
