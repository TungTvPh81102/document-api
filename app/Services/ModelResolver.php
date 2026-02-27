<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;

/**
 * ModelResolver — Map modelType string → Model class
 * 
 * Không dùng if/switch để phân biệt model
 * Thay vào đó, đọc từ config/media.php
 * 
 * Ví dụ:
 *   resolve('post')  → App\Models\Post::class
 *   resolve('user')  → App\Models\User::class
 *   resolve('product') → App\Models\Product::class
 */
class ModelResolver
{
    /**
     * Resolve model type string → FQCN (Fully Qualified Class Name)
     * 
     * @param string $modelType snake_case model name (ví dụ: 'post', 'user')
     * @return string FQCN (ví dụ: 'App\Models\Post')
     * @throws \InvalidArgumentException Nếu model type không tồn tại
     */
    public function resolve(string $modelType): string
    {
        $modelType = strtolower(trim($modelType));
        $modelConfig = config("media.models.{$modelType}");

        if (!$modelConfig) {
            throw new \InvalidArgumentException("Model type '{$modelType}' không được tìm thấy trong config");
        }

        $modelClass = $modelConfig['class'] ?? null;

        if (!$modelClass) {
            throw new \InvalidArgumentException("Model class cho '{$modelType}' chưa được cấu hình");
        }

        // Validate nó là một valid class
        if (!class_exists($modelClass)) {
            throw new \InvalidArgumentException("Model class '{$modelClass}' không tồn tại");
        }

        return $modelClass;
    }

    /**
     * Get Model instance từ modelType + ID
     * 
     * @param string $modelType snake_case model name
     * @param int $modelId
     * @return Model
     * @throws \InvalidArgumentException
     */
    public function getModel(string $modelType, int $modelId): Model
    {
        $modelClass = $this->resolve($modelType);
        $model = $modelClass::find($modelId);

        if (!$model) {
            throw new \InvalidArgumentException("{$modelType} với ID {$modelId} không tồn tại");
        }

        return $model;
    }

    /**
     * Get all supported model types
     * 
     * @return array Danh sách snake_case model names
     */
    public function getSupportedModelTypes(): array
    {
        return array_keys(config('media.models', []));
    }

    /**
     * Check if model type được hỗ trợ
     * 
     * @param string $modelType
     * @return bool
     */
    public function isSupported(string $modelType): bool
    {
        return in_array(strtolower(trim($modelType)), $this->getSupportedModelTypes());
    }

    /**
     * Get collections cho model type
     * 
     * @param string $modelType
     * @return array
     * @throws \InvalidArgumentException
     */
    public function getCollections(string $modelType): array
    {
        $modelConfig = config("media.models." . strtolower(trim($modelType)));

        if (!$modelConfig) {
            throw new \InvalidArgumentException("Model type '{$modelType}' không được tìm thấy");
        }

        return $modelConfig['collections'] ?? [];
    }

    /**
     * Validate model + collection combination
     * 
     * @param string $modelType
     * @param string $collection
     * @return bool
     */
    public function isValidCollectionForModel(string $modelType, string $collection): bool
    {
        try {
            $collections = $this->getCollections($modelType);
            return in_array($collection, $collections);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Get owner field cho model type
     * 
     * Dùng để check ownership (ví dụ: user_id, id)
     * 
     * @param string $modelType
     * @return string
     * @throws \InvalidArgumentException
     */
    public function getOwnerField(string $modelType): string
    {
        $modelConfig = config("media.models." . strtolower(trim($modelType)));

        if (!$modelConfig) {
            throw new \InvalidArgumentException("Model type '{$modelType}' không được tìm thấy");
        }

        return $modelConfig['owner_field'] ?? 'id';
    }
}
