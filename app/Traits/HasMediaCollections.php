<?php

namespace App\Traits;

use App\Media\MediaCollectionConfig;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * HasMediaCollections — Trait dùng chung cho tất cả models có media
 * 
 * Thay vì mỗi model phải implement InteractsWithMedia + registerMediaCollections()
 * Thì chỉ cần use HasMediaCollections; → tự động setup từ config
 * 
 * Cách dùng:
 *   class User extends Model {
 *       use HasMediaCollections;
 *   }
 * 
 * Tự động đọc collections từ config/media.php theo model name
 * 
 * Note: InteractsWithMedia đã implement HasMedia, nên không cần use cả hai
 */
trait HasMediaCollections
{
    use InteractsWithMedia;

    /**
     * Boot trait — đăng ký collections khi model được khởi tạo
     */
    protected static function bootHasMediaCollections()
    {
        // Không cần gì đặc biệt ở đây—logic chính ở registerMediaCollections()
    }

    /**
     * Register media collections
     * 
     * Gọi tự động bởi Spatie Media Library khi model được khởi tạo
     */
    public function registerMediaCollections(): void
    {
        // Lấy collections cho model này từ config
        $collectionsToRegister = $this->getMediaCollectionsFromConfig();

        // Đăng ký từng collection
        foreach ($collectionsToRegister as $collection) {
            MediaCollectionConfig::registerCollections($this, [$collection]);
        }
    }

    /**
     * Get media collections cho model này từ config/media.php
     * 
     * @return array Collection names (ví dụ: ['avatar', 'gallery'])
     */
    protected function getMediaCollectionsFromConfig(): array
    {
        $modelName = $this->getMediaCollectionModelName();
        $modelConfig = config("media.models.{$modelName}");

        if (!$modelConfig) {
            return [];
        }

        return $modelConfig['collections'] ?? [];
    }

    /**
     * Get model name dùng trong config/media.php
     * 
     * Override method này nếu model name trong config khác tên class
     * 
     * @return string
     */
    protected function getMediaCollectionModelName(): string
    {
        // Mặc định: class name thành snake_case (Post → post, UserRole → user_role)
        $className = class_basename(static::class);
        return strtolower(
            preg_replace('/(?<!^)(?=[A-Z])/', '_', $className)
        );
    }

    /**
     * Get media theo collection name
     * 
     * Bao wrapper của getMedia() từ Spatie
     * 
     * @param string $collection Collection name
     * @return \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection
     */
    public function getMediaByCollection(string $collection)
    {
        return $this->getMedia($collection);
    }

    /**
     * Get first media từ một collection (dùng cho single file collections)
     * 
     * @param string $collection Collection name
     * @param string|null $conversion Tên conversion (nếu có)
     * @return \Spatie\MediaLibrary\MediaCollections\Models\Media|null
     */
    public function getFirstMedia(string $collection, ?string $conversion = null)
    {
        $media = $this->getMedia($collection)->first();

        if (!$media) {
            return null;
        }

        if ($conversion && $media->hasGeneratedConversion($conversion)) {
            return $media;
        }

        return $media;
    }

    /**
     * Get URL của first media từ collection
     * 
     * @param string $collection Collection name
     * @param string|null $conversion Tên conversion
     * @return string|null
     */
    public function getMediaUrl(string $collection, ?string $conversion = null): ?string
    {
        $media = $this->getFirstMedia($collection, $conversion);

        if (!$media) {
            return null;
        }

        return $conversion && $media->hasGeneratedConversion($conversion)
            ? $media->getUrl($conversion)
            : $media->getUrl();
    }
}
