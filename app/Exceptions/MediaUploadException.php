<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * MediaUploadException — Exception cho media upload failures
 * 
 * Được throw khi:
 * - Cloudinary upload fail
 * - File validation fail
 * - DB record creation fail
 */
class MediaUploadException extends RuntimeException
{
    /**
     * Model class yang fail
     */
    protected ?string $modelType = null;

    /**
     * Model ID
     */
    protected ?int $modelId = null;

    /**
     * Collection name
     */
    protected ?string $collection = null;

    /**
     * Original exception (nếu ada)
     */
    protected ?\Throwable $originalException = null;

    public function __construct(
        string $message = 'Media upload failed',
        int $code = 0,
        ?\Throwable $previous = null,
        ?string $modelType = null,
        ?int $modelId = null,
        ?string $collection = null,
    ) {
        parent::__construct($message, $code, $previous);

        $this->modelType = $modelType;
        $this->modelId = $modelId;
        $this->collection = $collection;
        $this->originalException = $previous;
    }

    /**
     * Get model type
     */
    public function getModelType(): ?string
    {
        return $this->modelType;
    }

    /**
     * Get model ID
     */
    public function getModelId(): ?int
    {
        return $this->modelId;
    }

    /**
     * Get collection name
     */
    public function getCollection(): ?string
    {
        return $this->collection;
    }

    /**
     * Get context array cho logging
     */
    public function getContext(): array
    {
        return [
            'message' => $this->getMessage(),
            'model_type' => $this->modelType,
            'model_id' => $this->modelId,
            'collection' => $this->collection,
            'original_error' => $this->originalException?->getMessage(),
        ];
    }
}
