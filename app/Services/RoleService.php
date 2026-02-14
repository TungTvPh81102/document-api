<?php

namespace App\Services;

use App\Models\Role;
use Illuminate\Container\Attributes\Auth;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class RoleService {
    protected function __construct(
        private LoggerService $logger
    )
    {
    }

    public function getAllUsers(int $page = 1, int $perPage = 15): LengthAwarePaginator
    {
        $start = microtime(true);
        $corrId = (string)Str::orderedUuid();

        try {
            $paginator = Role::query()
                ->orderBy('created_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);

            $duration = microtime(true) - $start;

            $this->logger->logDatabaseOperation(__FUNCTION__, Role::class, Str::orderedUuid(), $duration);

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

    public function searchRoles(string $query, int $page = 1, int $perPage = 15): LengthAwarePaginator
    {
        $start = microtime(true);

        try {
            $paginator = Role::query()
                ->where('name', 'like', "%{$query}%")
                ->orWhere('slug', 'like', "%{$query}%")
                ->orWhere('description', 'like', "%{$query}%")
                ->orderBy('created_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);

            $duration = microtime(true) - $start;

            $this->logger->logDatabaseOperation('SEARCH', 'Role', null, $duration);
            $this->logger->logUserAction('SEARCH', null, [
                'query' => $query,
                'page' => $page,
                'per_page' => $perPage,
                'result_count' => $paginator->count(),
                'duration_ms' => round($duration * 1000, 2),
            ]);

            return $paginator;
        } catch (\Throwable $e) {

            return $e;
        }
    }

    public function getRoleById(string $id): ?Role {
        try {
            return Role::query()->find($id);
        }catch (\Exception $e) {
            return $e;
        }
    }

    public function createRole(array $data): Role
    {
        try {
            $data['slug'] = $data['slug'] ?? Str::slug($data['name']);
            $data['created_by'] = Auth::id() ?? 'System' ;

            $role = Role::query()->create($data);

            return $role;
        } catch (\Throwable $e) {

            throw $e;
        }
    }

    public function updateRole(Role $role, array $data) {

        try {
            $originalData = $role->only(array_keys($data));

            $data['updated_by'] = Auth::id() ?? 'System';
            $role->update($data);

            return $role;
        } catch (\Throwable $e) {
            throw $e;
        }
    }
}
