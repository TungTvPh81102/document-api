<?php

namespace App\Media;

use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * CloudinaryPathGenerator — Tạo path structure trên Cloudinary
 * 
 * Path structure: {model_type}/{model_id}/{collection}/{filename}
 * Example:
 *   posts/42/gallery/photo.jpg
 *   users/7/avatar/profile.png
 *   products/15/documents/spec.pdf
 */
class CloudinaryPathGenerator implements PathGenerator
{
    /**
     * Get the path for the given media, relative to the root of disk.
     * 
     * @param Media $media
     * @return string
     */
    public function getPath(Media $media): string
    {
        // Model type ở dạng snake_case
        $modelType = $this->getModelType($media->model_type);
        $modelId = $media->model_id;
        $collection = $media->collection_name;

        return "{$modelType}/{$modelId}/{$collection}";
    }

    /**
     * Get the path for conversions of the given media, relative to the root of disk.
     * 
     * Conversions là những biến thể của file gốc (resize, format khác, v.v)
     * 
     * @param Media $media
     * @return string
     */
    public function getPathForConversions(Media $media): string
    {
        // Cùng structure với path chính
        return $this->getPath($media);
    }

    /**
     * Get the path for responsive images for the given media, relative to the root of disk.
     * 
     * @param Media $media
     * @return string
     */
    public function getPathForResponsiveImages(Media $media): string
    {
        // Cùng structure
        return $this->getPath($media);
    }

    /**
     * Convert FQCN model type thành snake_case
     * 
     * @param string $modelType FQCN (ví dụ: App\Models\Post)
     * @return string snake_case (ví dụ: post)
     */
    protected function getModelType(string $modelType): string
    {
        // Lấy class name cuối cùng
        $className = class_basename($modelType);

        // Convert thành snake_case (Post → post, UserRole → user_role)
        $snakeCase = strtolower(
            preg_replace('/(?<!^)(?=[A-Z])/', '_', $className)
        );

        return $snakeCase;
    }
}
