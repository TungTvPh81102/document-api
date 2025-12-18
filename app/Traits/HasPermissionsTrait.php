<?php

namespace App\Traits;

use App\Models\Permission;

trait HasPermissionsTrait
{
    /**
     * Check if user has permission on a resource
     */
    public function hasPermission(string $resource, string $action, mixed $resourceId = null): bool
    {
        // Super admin check
        if ($this->is_admin ?? false) {
            return true;
        }

        // If no resourceId provided, check global permission
        if (is_null($resourceId)) {
            return $this->permissions()
                ->where('resource', $resource)
                ->where('action', $action)
                ->exists();
        }

        // Check specific resource permission
        return $this->permissions()
            ->where('resource', $resource)
            ->where('action', $action)
            ->where('resource_id', $resourceId)
            ->exists();
    }

    /**
     * Check if user can view a resource
     */
    public function canView(string $resource, mixed $resourceId = null): bool
    {
        return $this->hasPermission($resource, 'view', $resourceId);
    }

    /**
     * Check if user can create a resource
     */
    public function canCreate(string $resource): bool
    {
        return $this->hasPermission($resource, 'create');
    }

    /**
     * Check if user can edit a resource
     */
    public function canEdit(string $resource, mixed $resourceId = null): bool
    {
        return $this->hasPermission($resource, 'edit', $resourceId);
    }

    /**
     * Check if user can delete a resource
     */
    public function canDelete(string $resource, mixed $resourceId = null): bool
    {
        return $this->hasPermission($resource, 'delete', $resourceId);
    }

    /**
     * Get all permissions
     */
    public function permissions()
    {
        return $this->hasMany(Permission::class, 'user_id', 'id');
    }

    /**
     * Grant permission to user
     */
    public function grantPermission(string $resource, string $action, mixed $resourceId = null): Permission
    {
        return $this->permissions()->create([
            'resource' => $resource,
            'action' => $action,
            'resource_id' => $resourceId,
        ]);
    }

    /**
     * Revoke permission from user
     */
    public function revokePermission(string $resource, string $action, mixed $resourceId = null): int
    {
        $query = $this->permissions()
            ->where('resource', $resource)
            ->where('action', $action);

        if (!is_null($resourceId)) {
            $query->where('resource_id', $resourceId);
        }

        return $query->delete();
    }

    /**
     * Check if user has any of the given permissions
     */
    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (is_array($permission)) {
                if ($this->hasPermission($permission['resource'], $permission['action'], $permission['resource_id'] ?? null)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Check if user has all of the given permissions
     */
    public function hasAllPermissions(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (is_array($permission)) {
                if (!$this->hasPermission($permission['resource'], $permission['action'], $permission['resource_id'] ?? null)) {
                    return false;
                }
            }
        }
        return true;
    }
}
