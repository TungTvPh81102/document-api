<?php

namespace App\Exceptions;

use Exception;

/**
 * ApiException for structured API error responses.
 * Uses ErrorCode enum for consistent error handling.
 */
class ApiException extends Exception
{
    public function __construct(
        public readonly ErrorCode $errorCode,
        public readonly ?string $fieldName = null,
        public readonly ?array $details = null,
        string $customMessage = '',
    ) {
        $message = $customMessage ?: $errorCode->message();
        parent::__construct($message, $errorCode->httpStatus());
    }

    /**
     * Get structured error response for API.
     */
    public function toResponse(): array
    {
        return [
            'success' => false,
            'error' => [
                'code' => $this->errorCode->value,
                'message' => $this->message,
                'category' => $this->errorCode->category(),
                'field' => $this->fieldName,
                'details' => $this->details,
                'retryable' => $this->errorCode->isRetryable(),
            ],
        ];
    }

    /**
     * Get HTTP status code.
     */
    public function getStatusCode(): int
    {
        return $this->errorCode->httpStatus();
    }

    /**
     * Is this a client error (4xx)?
     */
    public function isClientError(): bool
    {
        return $this->getStatusCode() >= 400 && $this->getStatusCode() < 500;
    }

    /**
     * Is this a server error (5xx)?
     */
    public function isServerError(): bool
    {
        return $this->getStatusCode() >= 500;
    }
}
