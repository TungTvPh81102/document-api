<?php

namespace App\Media;

use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * MediaCollectionConfig — Single source of truth cho collection rules
 * 
 * Định nghĩa validation rules, mimes, max sizes cho từng collection type.
 * Sửa ở đây → áp dụng toàn app, không cần sửa lại ở từng model/controller.
 */
class MediaCollectionConfig
{
    /**
     * Collection rules từ config/media.php
     */
    protected static ?array $rules = null;

    /**
     * Get all collection rules
     */
    public static function getRules(): array
    {
        if (static::$rules === null) {
            static::$rules = config('media.collection_rules', []);
        }
        return static::$rules;
    }

    /**
     * Get rule cho một collection type
     * 
     * @param string $collection Collection name (avatar, gallery, documents, etc)
     * @return array|null
     */
    public static function getRule(string $collection): ?array
    {
        return static::getRules()[$collection] ?? null;
    }

    /**
     * Kiểm tra collection type có hợp lệ không
     */
    public static function isValidCollection(string $collection): bool
    {
        return array_key_exists($collection, static::getRules());
    }

    /**
     * Get allowed MIME types cho collection
     */
    public static function getAllowedMimes(string $collection): array
    {
        $rule = static::getRule($collection);
        return $rule['mimes'] ?? [];
    }

    /**
     * Get allowed extensions cho collection
     */
    public static function getAllowedExtensions(string $collection): array
    {
        $rule = static::getRule($collection);
        return $rule['extensions'] ?? [];
    }

    /**
     * Get max file size (bytes) cho collection
     */
    public static function getMaxSize(string $collection): int
    {
        $rule = static::getRule($collection);
        return $rule['max_size'] ?? config('media-library.max_file_size', 100 * 1024 * 1024);
    }

    /**
     * Get collection type (single hoặc multiple)
     */
    public static function getCollectionType(string $collection): string
    {
        $rule = static::getRule($collection);
        return $rule['type'] ?? 'multiple';
    }

    /**
     * Kiểm tra collection type là single file không
     */
    public static function isSingleFile(string $collection): bool
    {
        return static::getCollectionType($collection) === 'single';
    }

    /**
     * Get disk cho collection
     */
    public static function getDisk(string $collection): string
    {
        $rule = static::getRule($collection);
        return $rule['disk'] ?? config('media.default_disk', 'cloudinary');
    }

    /**
     * Get validation rules cho một collection
     * Dùng trong FormRequest validation
     */
    public static function getValidationRules(string $collection, string $fieldName = 'file'): array
    {
        $mimes = implode(',', static::getAllowedMimes($collection));
        $maxSizeKb = (int)(static::getMaxSize($collection) / 1024);

        if (static::isSingleFile($collection)) {
            return [
                $fieldName => "required|file|mimes:{$mimes}|max:{$maxSizeKb}",
            ];
        }

        // Multiple files
        return [
            $fieldName => "required|array",
            "{$fieldName}.*" => "file|mimes:{$mimes}|max:{$maxSizeKb}",
        ];
    }

    /**
     * Get validation messages
     */
    public static function getValidationMessages(string $fieldName = 'file'): array
    {
        return [
            "{$fieldName}.required" => 'Vui lòng chọn file',
            "{$fieldName}.file" => 'File phải là file hợp lệ',
            "{$fieldName}.mimes" => 'Định dạng file không được hỗ trợ',
            "{$fieldName}.max" => 'Kích thước file vượt quá giới hạn',
            "{$fieldName}.array" => 'Files phải là mảng',
            "{$fieldName}.*.file" => 'Mỗi item phải là file hợp lệ',
            "{$fieldName}.*.mimes" => 'Định dạng file không được hỗ trợ',
            "{$fieldName}.*.max" => 'Kích thước file vượt quá giới hạn',
        ];
    }

    /**
     * Register collections cho một model
     * 
     * Gọi từ HasMediaCollections trait
     * 
     * @param object $model Model instance
     * @param array $collectionsToRegister Array collection names được cấp phép
     */
    public static function registerCollections(object $model, array $collectionsToRegister): void
    {
        foreach ($collectionsToRegister as $collection) {
            if (!static::isValidCollection($collection)) {
                continue; // Bỏ qua collection không được định nghĩa
            }

            $rule = static::getRule($collection);
            $isSingleFile = $rule['type'] === 'single';

            // Tạo media collection dùng Spatie API
            $mediaCollection = $model->addMediaCollection($collection);

            if ($isSingleFile) {
                $mediaCollection->singleFile();
            }

            // Đăng ký collection
            $mediaCollection->registerMediaConversions(function () {
                // Có thể thêm conversions ở đây nếu cần
            });
        }
    }
}
