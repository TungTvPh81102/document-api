<?php

namespace App\Services;

use App\Exceptions\MediaUploadException;
use App\Media\MediaCollectionConfig;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * MediaService — Master service cho tất cả media operations
 * 
 * Không có method riêng per-model (uploadAvatar, uploadPostGallery...)
 * 
 * Methods:
 *   - upload($model, $collection, $file): Upload file → Cloudinary
 *   - delete($media): Xóa media
 *   - getMediaUrl($media): Lấy URL
 *   - syncSingleFile($model, $collection, $file): Xóa cũ rồi upload mới cho single-file collections
 */
class MediaService
{
    /**
     * Upload file cho model + collection
     * 
     * Flow:
     *   1. Validate file vs collection rules
     *   2. Add media using Spatie (Cloudinary upload xảy ra ở đây)
     *   3. Return Media record
     * 
     * Rollback:
     *   Nếu fail → xóa orphan media record (nếu được tạo)
     * 
     * @param Model $model Model instance (User, Post, Product, etc)
     * @param string $collection Collection name (avatar, gallery, etc)
     * @param UploadedFile $file File cần upload
     * @return Media
     * @throws MediaUploadException
     */
    public function upload(Model $model, string $collection, UploadedFile $file): Media
    {
        $modelType = $this->getModelType($model);

        try {
            // Validate file vs collection rules
            $this->validateFile($file, $collection);

            // Upload file → Cloudinary (Spatie handles this internally)
            $media = $model
                ->addMedia($file)
                ->toMediaCollection($collection);

            Log::info('Media uploaded successfully', [
                'model' => $modelType,
                'model_id' => $model->id,
                'collection' => $collection,
                'media_id' => $media->id,
                'file_name' => $media->file_name,
                'size' => $media->size,
            ]);

            return $media;
        } catch (MediaUploadException $e) {
            // Re-throw - nó là validation error
            throw $e;
        } catch (\Throwable $e) {
            // Rollback: Xóa media record nếu được tạo
            if (isset($media) && $media) {
                try {
                    $media->delete();
                } catch (\Throwable $deleteError) {
                    Log::error('Failed to rollback media record', [
                        'error' => $deleteError->getMessage(),
                        'media_id' => $media->id ?? null,
                    ]);
                }
            }

            // Log đầy đủ error context
            Log::error('Media upload failed', [
                'model' => $modelType,
                'model_id' => $model->id,
                'collection' => $collection,
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Throw custom exception
            throw new MediaUploadException(
                message: 'Upload file thất bại, vui lòng thử lại',
                code: 500,
                previous: $e,
                modelType: $modelType,
                modelId: $model->id,
                collection: $collection,
            );
        }
    }

    /**
     * Sync single file — xóa file cũ trước khi upload file mới
     * 
     * Dùng cho single-file collections (avatar, thumbnail)
     * 
     * @param Model $model
     * @param string $collection Collection name
     * @param UploadedFile $file File mới
     * @return Media Media record của file mới
     * @throws MediaUploadException
     */
    public function syncSingleFile(Model $model, string $collection, UploadedFile $file): Media
    {
        // Validate collection là single-file type
        if (!MediaCollectionConfig::isSingleFile($collection)) {
            throw new MediaUploadException(
                message: "Collection '{$collection}' không phải single-file type",
                code: 400,
            );
        }

        try {
            // Xóa media cũ (nếu có)
            $oldMedia = $model->getFirstMedia($collection);
            if ($oldMedia) {
                $oldMedia->delete();
                Log::info('Old media deleted for sync', [
                    'model' => $this->getModelType($model),
                    'model_id' => $model->id,
                    'collection' => $collection,
                    'old_media_id' => $oldMedia->id,
                ]);
            }

            // Upload file mới
            return $this->upload($model, $collection, $file);
        } catch (\Throwable $e) {
            // Nếu là MediaUploadException thì re-throw
            if ($e instanceof MediaUploadException) {
                throw $e;
            }

            // Không thì wrap vào MediaUploadException
            throw new MediaUploadException(
                message: 'Sync single file thất bại',
                code: 500,
                previous: $e,
                modelType: $this->getModelType($model),
                modelId: $model->id,
                collection: $collection,
            );
        }
    }

    /**
     * Delete media
     * 
     * @param Media $media
     * @return void
     */
    public function delete(Media $media): void
    {
        try {
            $modelType = $this->getModelType($media->model_type);

            $media->delete();

            Log::info('Media deleted', [
                'media_id' => $media->id,
                'model' => $modelType,
                'model_id' => $media->model_id,
                'collection' => $media->collection_name,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to delete media', [
                'media_id' => $media->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get media URL
     * 
     * @param Media $media
     * @param string|null $conversion Conversion name (nếu có)
     * @return string
     */
    public function getMediaUrl(Media $media, ?string $conversion = null): string
    {
        return $conversion && $media->hasGeneratedConversion($conversion)
            ? $media->getUrl($conversion)
            : $media->getUrl();
    }

    /**
     * Validate file vs collection rules
     * 
     * @param UploadedFile $file
     * @param string $collection
     * @throws MediaUploadException
     */
    protected function validateFile(UploadedFile $file, string $collection): void
    {
        // Check collection tồn tại
        if (!MediaCollectionConfig::isValidCollection($collection)) {
            throw new MediaUploadException(
                message: "Collection '{$collection}' không được hỗ trợ",
                code: 422,
            );
        }

        // Check MIME type
        $allowedMimes = MediaCollectionConfig::getAllowedMimes($collection);
        $fileMime = $file->getMimeType();

        if (!in_array($fileMime, $allowedMimes)) {
            throw new MediaUploadException(
                message: "Định dạng file không được hỗ trợ. Chỉ chấp nhận: " . implode(', ', $allowedMimes),
                code: 422,
            );
        }

        // Check file size
        $maxSize = MediaCollectionConfig::getMaxSize($collection);
        if ($file->getSize() > $maxSize) {
            $maxSizeMb = $maxSize / (1024 * 1024);
            throw new MediaUploadException(
                message: "Kích thước file vượt quá giới hạn ({$maxSizeMb}MB)",
                code: 422,
            );
        }
    }

    /**
     * Get model type (snake_case)
     * 
     * @param Model|string $model Model instance hoặc FQCN
     * @return string
     */
    protected function getModelType($model): string
    {
        $modelClass = is_string($model) ? $model : get_class($model);
        $className = class_basename($modelClass);

        return strtolower(
            preg_replace('/(?<!^)(?=[A-Z])/', '_', $className)
        );
    }

    /**
     * Get all media cho model + collection
     * 
     * @param Model $model
     * @param string $collection
     * @return \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection
     */
    public function getCollectionMedia(Model $model, string $collection)
    {
        return $model->getMedia($collection);
    }
}
