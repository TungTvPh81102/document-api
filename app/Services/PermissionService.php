<?php

namespace App\Services;

use App\Models\Permission;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class PermissionService
{
    /**
     * Get all permissions paginated.
     */
    public function getAllPermissions(int $page = 1, int $perPage = 15): LengthAwarePaginator
    {
        return Permission::query()
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Search permissions.
     */
    public function searchPermissions(string $query, int $page = 1, int $perPage = 15): LengthAwarePaginator
    {
        return Permission::query()
            ->where('name', 'like', "%{$query}%")
            ->orWhere('slug', 'like', "%{$query}%")
            ->orWhere('resource', 'like', "%{$query}%")
            ->orWhere('action', 'like', "%{$query}%")
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Get permission by ID.
     */
    public function getPermissionById(string $id): ?Permission
    {
        return Permission::query()->find($id);
    }

    /**
     * Create a new permission.
     */
    public function createPermission(array $data): Permission
    {
        $data['slug']       = $data['slug'] ?? Str::slug($data['name'] . '-' . $data['action']);
        $data['created_by'] = Auth::id() ?? 'system';

        return Permission::query()->create($data);
    }

    /**
     * Update an existing permission.
     */
    public function updatePermission(Permission $permission, array $data): Permission
    {
        $data['updated_by'] = Auth::id() ?? 'system';
        $permission->update($data);

        return $permission;
    }

    /**
     * Delete a permission.
     */
    public function deletePermission(Permission $permission): bool
    {
        return $permission->delete();
    }
}
