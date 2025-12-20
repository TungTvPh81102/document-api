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
    public function __construct(private LoggerService $logger)
    {
    }

    public function getAllUsers(int $page = 1, int $perPage = 15): LengthAwarePaginator
    {
        $start = microtime(true);
        $corrId = (string)Str::orderedUuid();

        try {
            $paginator = User::query()
                ->orderBy('created_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);

            $duration = microtime(true) - $start;

            $this->logger->logDatabaseOperation(__FUNCTION__, User::class, Str::orderedUuid(), $duration);

            return $paginator;
        } catch (\Throwable $e) {
            $duration = microtime(true) - $start;
            $durationMs = round($duration * 1000, 2);

            $this->logger->logServiceError(
                __CLASS__ . '@' . __FUNCTION__,
                'GET',
                $e,
                [
                    'page' => $page,
                    'per_page' => $perPage,
                    'duration_ms' => $durationMs,
                    'correlation_id' => $corrId,
                ]
            );

            throw $e;
        }
    }

    /**
     * Get user by Code
     */
    public function getUserByCode(string $code): ?User
    {
        return User::query()
            ->where('code', $code)
            ->first();
    }

    /**
     * Get user by email
     */
    public function getUserByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    /**
     * Create new user
     */
    public function createUser(array $data): User
    {
        $start = microtime(true);

        try {
            $data['password'] = Hash::make($data['password']);
            $data['email_verified_at'] = now();
            $data['enable'] = true;
            $data['code'] = $this->makeUserCode(now() ?? Str::random(20));

            $user = User::query()->create($data);
            $duration = microtime(true) - $start;
            $this->logger
                ->logDatabaseOperation('INSERT', 'User', $user->id, $duration, $data);

            return $user;
        } catch (\Throwable $e) {
            $duration = microtime(true) - $start;
            $this->logger->logServiceError(self::class, __FUNCTION__, $e,
                ['data' => $data, 'duration_ms' => round($duration * 1000, 2)]);
            throw $e;
        }
    }

    /**
     * Update user
     */
    public function updateUser(User $user, array $data): User
    {
        $start = microtime(true);

        try {
            $originalData = $user->only(array_keys($data));

            if (isset($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            }

            $user->update($data);

            $duration = microtime(true) - $start;

            $this->logger->logDatabaseOperation('UPDATE', 'User', $user->id, $duration, [
                'changes' => $this->getChanges($originalData, $data),
            ]);

            return $user;
        } catch (\Throwable $e) {
            $duration = microtime(true) - $start;

            $this->logger->logDatabaseOperation('UPDATE', 'User', $user->id, $duration, $data, true, $e->getMessage());
            $this->logger->logServiceError(
                self::class,
                __FUNCTION__,
                $e,
                $data
            );

            throw $e;
        }
    }

    /**
     * Delete user (soft delete)
     */
    public function deleteUser(User $user): bool
    {
        $start = microtime(true);

        try {
            $result = $user->delete();
            $duration = microtime(true) - $start;

            $this->logger->logDatabaseOperation('DELETE', 'User', $user->id, $duration);
            $this->logger->logUserAction('deleted', $user, [
                'type' => 'soft_delete',
                'duration_ms' => round($duration * 1000, 2),
            ]);

            return $result;
        } catch (\Throwable $e) {
            $duration = microtime(true) - $start;

            $this->logger->logDatabaseOperation('DELETE', 'User', $user->id, $duration, [], true, $e->getMessage());

            \Log::error('deleteUser failed', [
                'exception' => $e->getMessage(),
                'user_id' => $user->id,
                'duration_ms' => round($duration * 1000, 2),
            ]);
            throw $e;
        }
    }

    /**
     * Restore deleted user
     */
    public function restoreUser(User $user): bool
    {
        $start = microtime(true);
        try {
            $result = $user->restore();
            $duration = microtime(true) - $start;
            $this->logger->logDatabaseOperation('RESTORE', 'User', $user->id, $duration);
            return $result;
        } catch (\Throwable $e) {
            $duration = microtime(true) - $start;
            $this->logger->logDatabaseOperation('RESTORE', 'User', $user->id, $duration, [], true, $e->getMessage());
            throw $e;
        }
    }

    /**
     * Permanently delete user
     */
    public function forceDeleteUser(User $user): bool
    {
        $start = microtime(true);
        try {
            $result = $user->forceDelete();
            $duration = microtime(true) - $start;
            $this->logger->logDatabaseOperation('FORCE_DELETE', 'User', $user->id, $duration);
            return $result;
        } catch (\Throwable $e) {
            $duration = microtime(true) - $start;
            $this->logger->logDatabaseOperation('FORCE_DELETE', 'User', $user->id, $duration, [], true, $e->getMessage());
            throw $e;
        }
    }

    /**
     * Lock user account
     */
    public function lockUser(User $user, int $lockDuration = 3600): User
    {
        $start = microtime(true);
        try {
            $user->update([
                'locked_at' => now()->addSeconds($lockDuration),
                'lock_count' => ($user->lock_count ?? 0) + 1,
            ]);
            $duration = microtime(true) - $start;
            $this->logger->logDatabaseOperation('LOCK', 'User', $user->id, $duration, ['duration' => $lockDuration]);
            return $user;
        } catch (\Throwable $e) {
            $duration = microtime(true) - $start;
            $this->logger->logDatabaseOperation('LOCK', 'User', $user->id, $duration, ['duration' => $lockDuration], true, $e->getMessage());
            throw $e;
        }
    }

    /**
     * Unlock user account
     */
    public function unlockUser(User $user): User
    {
        $start = microtime(true);
        try {
            $user->update([
                'locked_at' => null,
                'lock_count' => 0,
            ]);
            $duration = microtime(true) - $start;
            $this->logger->logDatabaseOperation('UNLOCK', 'User', $user->id, $duration);
            return $user;
        } catch (\Throwable $e) {
            $duration = microtime(true) - $start;
            $this->logger->logDatabaseOperation('UNLOCK', 'User', $user->id, $duration, [], true, $e->getMessage());
            throw $e;
        }
    }

    /**
     * Check if user is locked
     */
    public function isUserLocked(User $user): bool
    {
        return $user->locked_at && $user->locked_at > now();
    }

    /**
     * Enable user
     */
    public function enableUser(User $user): User
    {
        $start = microtime(true);
        try {
            $user->update(['enable' => true]);
            $duration = microtime(true) - $start;
            $this->logger->logDatabaseOperation('ENABLE', 'User', $user->id, $duration);
            return $user;
        } catch (\Throwable $e) {
            $duration = microtime(true) - $start;
            $this->logger->logDatabaseOperation('ENABLE', 'User', $user->id, $duration, [], true, $e->getMessage());
            throw $e;
        }
    }

    /**
     * Disable user
     */
    public function disableUser(User $user): User
    {
        $start = microtime(true);
        try {
            $user->update(['enable' => false]);
            $duration = microtime(true) - $start;
            $this->logger->logDatabaseOperation('DISABLE', 'User', $user->id, $duration);
            return $user;
        } catch (\Throwable $e) {
            $duration = microtime(true) - $start;
            $this->logger->logDatabaseOperation('DISABLE', 'User', $user->id, $duration, [], true, $e->getMessage());
            throw $e;
        }
    }

    /**
     * Search users
     */
    public function searchUsers(string $query, int $page = 1, int $perPage = 15): LengthAwarePaginator
    {
        $start = microtime(true);

        try {
            $paginator = User::query()
                ->where('name', 'like', "%{$query}%")
                ->orWhere('email', 'like', "%{$query}%")
                ->orWhere('phone', 'like', "%{$query}%")
                ->orderBy('created_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);

            $duration = microtime(true) - $start;

            $this->logger->logDatabaseOperation('SEARCH', 'User', null, $duration);
            $this->logger->logUserAction('searched', null, [
                'query' => $query,
                'page' => $page,
                'per_page' => $perPage,
                'result_count' => $paginator->count(),
                'duration_ms' => round($duration * 1000, 2),
            ]);

            return $paginator;
        } catch (\Throwable $e) {
            $duration = microtime(true) - $start;
            \Log::error('searchUsers failed', [
                'exception' => $e->getMessage(),
                'query' => $query,
                'page' => $page,
                'duration_ms' => round($duration * 1000, 2),
            ]);
            throw $e;
        }
    }

    /**
     * Get user statistics
     */
    public function getUserStatistics(): array
    {
        $start = microtime(true);

        try {
            $stats = [
                'total_users' => User::count(),
                'active_users' => User::where('enable', true)->count(),
                'disabled_users' => User::where('enable', false)->count(),
                'locked_users' => User::whereNotNull('locked_at')->where('locked_at', '>', now())->count(),
                'verified_users' => User::whereNotNull('email_verified_at')->count(),
            ];

            $duration = microtime(true) - $start;

            $this->logger->logDatabaseOperation('STATISTICS', 'User', null, $duration);
            $this->logger->logUserAction('statistics_retrieved', null, [
                'duration_ms' => round($duration * 1000, 2),
                'stats' => $stats,
            ]);

            return $stats;
        } catch (\Throwable $e) {
            $duration = microtime(true) - $start;
            \Log::error('getUserStatistics failed', [
                'exception' => $e->getMessage(),
                'duration_ms' => round($duration * 1000, 2),
            ]);
            throw $e;
        }
    }

    /**
     * Get changes between original and updated data
     */
    private function getChanges(array $original, array $updated): array
    {
        $changes = [];
        foreach ($updated as $key => $value) {
            if ($key === 'password') {
                $changes[$key] = 'hashed';
            } elseif (($original[$key] ?? null) !== $value) {
                $changes[$key] = [
                    'from' => $original[$key] ?? null,
                    'to' => $value,
                ];
            }
        }
        return $changes;
    }


    private function makeUserCode(?string $incomingCode): string
    {
        $base = Carbon::now()->format('YmdHis');
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
            $suffixLen = max(1, min(6, $maxLen - 14)); // phần suffix tối đa 6
            $code = $base . $this->randomDigits($suffixLen);
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
