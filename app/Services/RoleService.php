<?php

namespace App\Services;

use App\Models\Role;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class RoleService
{
    /**
     * Get all roles paginated (with permissions eager loaded).
     */
    public function getAllRoles(int $page = 1, int $perPage = 15): LengthAwarePaginator
    {
        return Role::query()
            ->with(['permissions'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Search roles.
     */
    public function searchRoles(string $query, int $page = 1, int $perPage = 15): LengthAwarePaginator
    {
        return Role::query()
            ->with(['permissions'])
            ->where('name', 'like', "%{$query}%")
            ->orWhere('description', 'like', "%{$query}%")
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Get role by ID (with permissions).
     */
    public function getRoleById(string $id): ?Role
    {
        return Role::query()->with(['permissions'])->find($id);
    }

    /**
     * Create a new role.
     */
    public function createRole(array $data): Role
    {
        $data['guard_name'] = $data['guard_name'] ?? 'web';
        $data['created_by'] = Auth::id() ?? 'system';

        $permissionIds = $data['permission_ids'] ?? [];
        unset($data['permission_ids']);

        $role = Role::query()->create($data);

        if (!empty($permissionIds)) {
            $role->syncPermissions($permissionIds);
        }

        return $role->load('permissions');
    }

    /**
     * Update an existing role.
     */
    public function updateRole(Role $role, array $data): Role
    {
        $data['updated_by'] = Auth::id() ?? 'system';

        $permissionIds = $data['permission_ids'] ?? null;
        unset($data['permission_ids']);

        $role->update($data);

        if (!is_null($permissionIds)) {
            $role->syncPermissions($permissionIds);
        }

        return $role->fresh('permissions');
    }

    /**
     * Sync permissions for a role.
     */
    public function syncPermissions(Role $role, array $permissionIds): Role
    {
        $role->syncPermissions($permissionIds);
        return $role->fresh('permissions');
    }

    /**
     * Delete a role (soft delete).
     */
    public function deleteRole(Role $role): bool
    {
        $role->deleted_by = Auth::id() ?? 'system';
        $role->save();

        return $role->delete();
    }
}
