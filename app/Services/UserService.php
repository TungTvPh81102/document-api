<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserService
{
    public function __construct(private LoggerService $logger) {}

    public function getAllUsers(int $page = 1, int $perPage = 15): LengthAwarePaginator
    {
        $start = microtime(true);
        $corrId = (string) Str::orderedUuid();

        try {
            $paginator = User::query()
                ->orderBy('created_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);

            $duration = microtime(true) - $start;

            $this->logger->logDatabaseOperation(__FUNCTION__, User::class, Str::uuid(), $duration);
            $this->logger->logAction('list', null, [
                'page' => $page,
                'per_page' => $perPage,
                'result_count' => $paginator->count(),
                'total' => $paginator->total(),
                'duration_ms' => round($duration * 1000, 2),
            ]);

            return $paginator;
        } catch (\Throwable $e) {
            $duration = microtime(true) - $start;
            $durationMs = round($duration * 1000, 2);

            $this->logger->logServiceError(
                __CLASS__ . '@' . __FUNCTION__, 
                'GET',                         
                $e,
                [
                    'page'        => $page,
                    'per_page'    => $perPage,
                    'duration_ms' => $durationMs,
                    'correlation_id' => $corrId,
                ]
            );

            throw $e;
        }
    }

    /**
     * Get user by ID
     */
    public function getUserById(int $id): ?User
    {
        return User::find($id);
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

            $user = User::create($data);

            $duration = microtime(true) - $start;

            $this->logger->logDatabaseOperation('create', 'User', $user->id, $duration);
            $this->logger->logUserAction('created', $user, [
                'email' => $user->email,
                'name' => $user->name,
                'duration_ms' => round($duration * 1000, 2),
            ]);

            return $user;
        } catch (\Throwable $e) {
            $duration = microtime(true) - $start;
            \Log::error('createUser failed', [
                'exception' => $e->getMessage(),
                'email' => $data['email'] ?? null,
                'duration_ms' => round($duration * 1000, 2),
            ]);
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

            // Hash password if provided
            if (isset($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            }

            $user->update($data);

            $duration = microtime(true) - $start;

            $this->logger->logDatabaseOperation('update', 'User', $user->id, $duration);
            $this->logger->logUserAction('updated', $user, [
                'changes' => $this->getChanges($originalData, $data),
                'duration_ms' => round($duration * 1000, 2),
            ]);

            return $user;
        } catch (\Throwable $e) {
            $duration = microtime(true) - $start;
            \Log::error('updateUser failed', [
                'exception' => $e->getMessage(),
                'user_id' => $user->id,
                'duration_ms' => round($duration * 1000, 2),
            ]);
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

            $this->logger->logDatabaseOperation('delete', 'User', $user->id, $duration);
            $this->logger->logUserAction('deleted', $user, [
                'type' => 'soft_delete',
                'duration_ms' => round($duration * 1000, 2),
            ]);

            return $result;
        } catch (\Throwable $e) {
            $duration = microtime(true) - $start;
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
        return $user->restore();
    }

    /**
     * Permanently delete user
     */
    public function forceDeleteUser(User $user): bool
    {
        return $user->forceDelete();
    }

    /**
     * Lock user account
     */
    public function lockUser(User $user, int $lockDuration = 3600): User
    {
        $user->update([
            'locked_at' => now()->addSeconds($lockDuration),
            'lock_count' => ($user->lock_count ?? 0) + 1,
        ]);

        return $user;
    }

    /**
     * Unlock user account
     */
    public function unlockUser(User $user): User
    {
        $user->update([
            'locked_at' => null,
            'lock_count' => 0,
        ]);

        return $user;
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
        $user->update(['enable' => true]);
        return $user;
    }

    /**
     * Disable user
     */
    public function disableUser(User $user): User
    {
        $user->update(['enable' => false]);
        return $user;
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

            $this->logger->logDatabaseOperation('search', 'User', null, $duration);
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

            $this->logger->logDatabaseOperation('statistics', 'User', null, $duration);
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
}
