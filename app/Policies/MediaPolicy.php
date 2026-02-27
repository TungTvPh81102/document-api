<?php

namespace App\Policies;

use App\Services\ModelResolver;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use App\Models\User;

/**
 * MediaPolicy — Authorization để upload/delete media
 * 
 * Methods:
 *   - upload(User, Model, string): Check có thể upload vào model + collection
 *   - delete(User, Media): Check có thể xóa media
 * 
 * Logic ownership:
 *   - Nếu owner_field != null: kiểm tra $model->{owner_field} === $user->id
 *   - Nếu owner_field = null: chỉ admin (is_admin hoặc hasPermission)
 *   - Super admin bypass tất cả
 */
class MediaPolicy
{
    /**
     * Check if user có quyền upload media vào model + collection
     * 
     * @param User $user
     * @param Model $model
     * @param string $collection
     * @return bool
     */
    public function upload(User $user, Model $model, string $collection): bool
    {
        // Super admin bypass tất cả
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        try {
            $resolver = new ModelResolver();
            $modelType = $this->getModelType($model);
            $ownerField = $resolver->getOwnerField($modelType);

            // Nếu có owner_field → check ownership
            if ($ownerField && isset($model->$ownerField)) {
                // owner_field được set → check owner
                $ownerId = $model->$ownerField;

                // Nếu ownerId là user ID → user là owner
                if ($ownerId === $user->id) {
                    return true;
                }

                // Nếu ownerId là model ID nhưng model này thuộc về user hiện tại
                // (ví dụ: Post model có user_id) → check $model->user_id
                if ($model->relationLoaded('user') && $model->user?->id === $user->id) {
                    return true;
                }

                return false;
            }

            // Nếu không có owner_field → chỉ admin
            return $this->isAdmin($user);
        } catch (\Throwable $e) {
            // Nếu error → deny by default
            return false;
        }
    }

    /**
     * Check if user có quyền xóa media
     * 
     * @param User $user
     * @param Media $media
     * @return bool
     */
    public function delete(User $user, Media $media): bool
    {
        // Super admin bypass
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        try {
            // Get model của media này
            $model = $media->model;

            if (!$model) {
                return false;
            }

            // Dùng upload policy để check ownership
            // (cách làm này đảm bảo logic ownership unified)
            return $this->upload($user, $model, $media->collection_name);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Check user là super admin
     * 
     * @param User $user
     * @return bool
     */
    protected function isSuperAdmin(User $user): bool
    {
        // Assume user có is_admin hoặc super_admin attribute
        // Hoặc dùng Spatie Permission: hasPermission('super-admin')

        // Method 1: Direct attribute
        if (isset($user->is_admin) && $user->is_admin) {
            return true;
        }

        if (isset($user->is_super_admin) && $user->is_super_admin) {
            return true;
        }

        // Method 2: Spatie Permission
        try {
            if ($user->hasPermissionTo('super-admin')) {
                return true;
            }

            // Hoặc check role
            if ($user->hasRole('super-admin') || $user->hasRole('admin')) {
                return true;
            }
        } catch (\Throwable $e) {
            // Permission middleware không available
        }

        return false;
    }

    /**
     * Check user là admin
     * 
     * @param User $user
     * @return bool
     */
    protected function isAdmin(User $user): bool
    {
        // Super admin cũng là admin
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        // Check admin role/permission
        try {
            return $user->hasRole('admin') || $user->hasPermissionTo('manage-media');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Get model type (snake_case)
     */
    protected function getModelType(Model $model): string
    {
        $className = class_basename($model::class);
        return strtolower(
            preg_replace('/(?<!^)(?=[A-Z])/', '_', $className)
        );
    }
}
