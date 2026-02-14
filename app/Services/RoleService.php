<?php

namespace App\Services;

use App\Models\Role;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class RoleService
{
    /**
     * Get all roles paginated.
     */
    public function getAllRoles(int $page = 1, int $perPage = 15): LengthAwarePaginator
    {
        return Role::query()
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Search roles.
     */
    public function searchRoles(string $query, int $page = 1, int $perPage = 15): LengthAwarePaginator
    {
        return Role::query()
            ->where('name', 'like', "%{$query}%")
            ->orWhere('slug', 'like', "%{$query}%")
            ->orWhere('description', 'like', "%{$query}%")
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Get role by ID.
     */
    public function getRoleById(string $id): ?Role
    {
        return Role::query()->find($id);
    }

    /**
     * Create a new role.
     */
    public function createRole(array $data): Role
    {
        $data['slug']       = $data['slug'] ?? Str::slug($data['name']);
        $data['created_by'] = Auth::id() ?? 'system';

        return Role::query()->create($data);
    }

    /**
     * Update an existing role.
     */
    public function updateRole(Role $role, array $data): Role
    {
        $data['updated_by'] = Auth::id() ?? 'system';
        $role->update($data);

        return $role;
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
