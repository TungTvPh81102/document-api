<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserService
{
    /**
     * Get all users paginated.
     */
    public function getAllUsers(int $page = 1, int $perPage = 15): LengthAwarePaginator
    {
        return User::query()
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Get user by code.
     */
    public function getUserByCode(string $code): ?User
    {
        return User::query()
            ->where('code', $code)
            ->first();
    }

    /**
     * Get user by email.
     */
    public function getUserByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    /**
     * Create new user.
     */
    public function createUser(array $data): User
    {
        $data['password']          = Hash::make($data['password']);
        $data['email_verified_at'] = now();
        $data['enable']            = true;
        $data['code']              = $this->makeUserCode(now() ?? Str::random(20));

        return User::query()->create($data);
    }

    /**
     * Update user.
     */
    public function updateUser(User $user, array $data): User
    {
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        $user->update($data);

        return $user;
    }

    /**
     * Delete user (soft delete).
     */
    public function deleteUser(User $user): bool
    {
        return $user->delete();
    }

    /**
     * Restore deleted user.
     */
    public function restoreUser(User $user): bool
    {
        return $user->restore();
    }

    /**
     * Permanently delete user.
     */
    public function forceDeleteUser(User $user): bool
    {
        return $user->forceDelete();
    }

    /**
     * Lock user account.
     */
    public function lockUser(User $user, int $lockDuration = 3600): User
    {
        $user->update([
            'locked_at'  => now()->addSeconds($lockDuration),
            'lock_count' => ($user->lock_count ?? 0) + 1,
        ]);

        return $user;
    }

    /**
     * Unlock user account.
     */
    public function unlockUser(User $user): User
    {
        $user->update([
            'locked_at'  => null,
            'lock_count' => 0,
        ]);

        return $user;
    }

    /**
     * Check if user is locked.
     */
    public function isUserLocked(User $user): bool
    {
        return $user->locked_at && $user->locked_at > now();
    }

    /**
     * Enable user.
     */
    public function enableUser(User $user): User
    {
        $user->update(['enable' => true]);
        return $user;
    }

    /**
     * Disable user.
     */
    public function disableUser(User $user): User
    {
        $user->update(['enable' => false]);
        return $user;
    }

    /**
     * Search users.
     */
    public function searchUsers(string $query, int $page = 1, int $perPage = 15): LengthAwarePaginator
    {
        return User::query()
            ->where('name', 'like', "%{$query}%")
            ->orWhere('email', 'like', "%{$query}%")
            ->orWhere('phone', 'like', "%{$query}%")
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Get user statistics.
     */
    public function getUserStatistics(): array
    {
        return [
            'total_users'    => User::count(),
            'active_users'   => User::where('enable', true)->count(),
            'disabled_users' => User::where('enable', false)->count(),
            'locked_users'   => User::whereNotNull('locked_at')->where('locked_at', '>', now())->count(),
            'verified_users' => User::whereNotNull('email_verified_at')->count(),
        ];
    }

    /* ═══════════════════════════════════════════════════════
     *  Private helpers
     * ═══════════════════════════════════════════════════════ */

    private function makeUserCode(?string $incomingCode): string
    {
        $base   = Carbon::now()->format('YmdHis');
        $maxLen = 20;

        if (!empty($incomingCode)) {
            $normalized = preg_replace('/\D+/', '', $incomingCode);
            if (empty($normalized)) {
                $normalized = $base;
            }
            $code = substr($normalized, 0, $maxLen);
            if (strlen($code) < 14 || !str_starts_with($code, $base)) {
                $code = $base;
            }
        } else {
            $code = $base;
        }

        if (strlen($code) < $maxLen) {
            $need = $maxLen - strlen($code);
            $code .= $this->randomDigits($need);
        }

        $tries = 0;
        while ($this->codeExists($code) && $tries < 5) {
            $suffixLen = max(1, min(6, $maxLen - 14));
            $code      = $base . $this->randomDigits($suffixLen);
            $tries++;
        }

        if ($this->codeExists($code)) {
            $remaining = $maxLen - strlen($code);
            if ($remaining > 0) {
                $code .= $this->randomDigits($remaining);
            } else {
                $code = substr($code, 0, $maxLen - 2) . $this->randomDigits(2);
            }
        }

        return $code;
    }

    private function randomDigits(int $length): string
    {
        $digits = '';
        for ($i = 0; $i < $length; $i++) {
            $digits .= random_int(0, 9);
        }
        return $digits;
    }

    private function codeExists(string $code): bool
    {
        return DB::table('users')->where('code', $code)->exists();
    }
}
