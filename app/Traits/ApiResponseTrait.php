<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

trait ApiResponseTrait
{
    /**
     * Response metadata
     */
    private ?string $correlationId = null;
    private ?array $debugInfo = null;
    private ?array $links = null;
    private ?array $meta = null;

    /**
     * Set correlation ID for request tracking
     */
    protected function setCorrelationId(string $id): self
    {
        $this->correlationId = $id;
        return $this;
    }

    /**
     * Add debug information (only in non-production)
     */
    protected function withDebug(array $debug): self
    {
        if (!app()->isProduction()) {
            $this->debugInfo = $debug;
        }
        return $this;
    }

    /**
     * Add HATEOAS links
     */
    protected function withLinks(array $links): self
    {
        $this->links = $links;
        return $this;
    }

    /**
     * Add custom metadata
     */
    protected function withMeta(array $meta): self
    {
        $this->meta = $meta;
        return $this;
    }

    /**
     * Format response envelope with enhanced features
     */
    private function formatResponse(
        bool $success,
        mixed $data = null,
        string $message = '',
        int $code = 200,
        mixed $errors = null
    ): array {
        $response = [
            'success' => $success,
            'message' => $message,
            'code' => $code,
        ];

        // Add data if present
        if ($data !== null) {
            $response['data'] = $data;
        }

        // Add errors if present
        if ($errors !== null) {
            $response['errors'] = $this->formatErrors($errors);
        }

        // Add correlation ID for request tracking
        if ($this->correlationId) {
            $response['correlation_id'] = $this->correlationId;
        }

        // Add links (HATEOAS)
        if ($this->links) {
            $response['links'] = $this->links;
        }

        // Add custom metadata
        if ($this->meta) {
            $response['meta'] = $this->meta;
        }

        // Add debug info (only in non-production)
        if ($this->debugInfo) {
            $response['debug'] = $this->debugInfo;
        }

        // Add timestamp
        $response['timestamp'] = now()->toISOString();

        // Add request ID from request if available
        if ($requestId = request()->header('X-Request-ID')) {
            $response['request_id'] = $requestId;
        }

        // Reset metadata after response
        $this->resetMetadata();

        return $response;
    }

    /**
     * Format errors consistently
     */
    private function formatErrors(mixed $errors): array
    {
        if (is_string($errors)) {
            return [['message' => $errors]];
        }

        if ($errors instanceof \Illuminate\Support\MessageBag) {
            return collect($errors->getMessages())
                ->map(fn($messages, $field) => [
                    'field' => $field,
                    'messages' => $messages
                ])
                ->values()
                ->toArray();
        }

        if (is_array($errors)) {
            // Check if it's validation errors format
            if (array_keys($errors) !== range(0, count($errors) - 1)) {
                return collect($errors)
                    ->map(fn($messages, $field) => [
                        'field' => $field,
                        'messages' => is_array($messages) ? $messages : [$messages]
                    ])
                    ->values()
                    ->toArray();
            }
        }

        return $errors;
    }

    /**
     * Reset metadata after each response
     */
    private function resetMetadata(): void
    {
        $this->correlationId = null;
        $this->debugInfo = null;
        $this->links = null;
        $this->meta = null;
    }

    /**
     * Success response
     */
    protected function successResponse(
        mixed $data = null,
        string $message = 'Success',
        int $code = 200
    ): JsonResponse {
        $response = response()->json(
            $this->formatResponse(true, $data, $message, $code),
            $code
        );

        $this->addSecurityHeaders($response);
        return $response;
    }

    /**
     * Error response with optional exception logging
     */
    protected function errorResponse(
        string $message = 'Error',
        int $code = 400,
        mixed $errors = null,
        ?\Throwable $exception = null
    ): JsonResponse {
        // Log exception if provided
        if ($exception) {
            Log::error("API Error: {$message}", [
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
                'code' => $code,
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ]);

            // Add exception info in non-production
            if (!app()->isProduction()) {
                $this->withDebug([
                    'exception' => get_class($exception),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                ]);
            }
        }

        $response = response()->json(
            $this->formatResponse(false, null, $message, $code, $errors),
            $code
        );

        $this->addSecurityHeaders($response);
        return $response;
    }

    /**
     * Created response with optional location header
     */
    protected function createdResponse(
        mixed $data = null,
        string $message = 'Created successfully',
        ?string $location = null
    ): JsonResponse {
        $response = $this->successResponse($data, $message, Response::HTTP_CREATED);

        if ($location) {
            $response->header('Location', $location);
        }

        return $response;
    }

    /**
     * Accepted response (for async operations)
     */
    protected function acceptedResponse(
        mixed $data = null,
        string $message = 'Request accepted for processing'
    ): JsonResponse {
        return $this->successResponse($data, $message, Response::HTTP_ACCEPTED);
    }

    /**
     * No content response
     */
    protected function noContentResponse(): JsonResponse
    {
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Not found response
     */
    protected function notFoundResponse(
        string $message = 'Resource not found',
        ?string $resourceType = null
    ): JsonResponse {
        if ($resourceType) {
            $message = "{$resourceType} not found";
        }
        return $this->errorResponse($message, Response::HTTP_NOT_FOUND);
    }

    /**
     * Validation error response with formatted errors
     */
    protected function validationErrorResponse(
        mixed $errors,
        string $message = 'Validation failed'
    ): JsonResponse {
        return $this->errorResponse(
            $message,
            Response::HTTP_UNPROCESSABLE_ENTITY,
            $errors
        );
    }

    /**
     * Unauthorized response
     */
    protected function unauthorizedResponse(
        string $message = 'Unauthorized',
        ?string $realm = null
    ): JsonResponse {
        $response = $this->errorResponse($message, Response::HTTP_UNAUTHORIZED);

        if ($realm) {
            $response->header('WWW-Authenticate', "Bearer realm=\"{$realm}\"");
        }

        return $response;
    }

    /**
     * Forbidden response
     */
    protected function forbiddenResponse(
        string $message = 'Forbidden',
        ?string $reason = null
    ): JsonResponse {
        if ($reason) {
            $this->withMeta(['reason' => $reason]);
        }
        return $this->errorResponse($message, Response::HTTP_FORBIDDEN);
    }

    /**
     * Server error response
     */
    protected function serverErrorResponse(
        string $message = 'Internal server error',
        ?\Throwable $exception = null
    ): JsonResponse {
        return $this->errorResponse(
            $message,
            Response::HTTP_INTERNAL_SERVER_ERROR,
            null,
            $exception
        );
    }

    /**
     * Conflict response (for duplicate resources)
     */
    protected function conflictResponse(
        string $message = 'Resource already exists',
        mixed $conflicts = null
    ): JsonResponse {
        return $this->errorResponse($message, Response::HTTP_CONFLICT, $conflicts);
    }

    /**
     * Too many requests response (rate limiting)
     */
    protected function tooManyRequestsResponse(
        string $message = 'Too many requests',
        ?int $retryAfter = null
    ): JsonResponse {
        $response = $this->errorResponse($message, Response::HTTP_TOO_MANY_REQUESTS);

        if ($retryAfter) {
            $response->header('Retry-After', $retryAfter);
            $this->withMeta(['retry_after_seconds' => $retryAfter]);
        }

        return $response;
    }

    /**
     * Bad request response
     */
    protected function badRequestResponse(
        string $message = 'Bad request',
        mixed $errors = null
    ): JsonResponse {
        return $this->errorResponse($message, Response::HTTP_BAD_REQUEST, $errors);
    }

    /**
     * Service unavailable response
     */
    protected function serviceUnavailableResponse(
        string $message = 'Service temporarily unavailable',
        ?int $retryAfter = null
    ): JsonResponse {
        $response = $this->errorResponse($message, Response::HTTP_SERVICE_UNAVAILABLE);

        if ($retryAfter) {
            $response->header('Retry-After', $retryAfter);
        }

        return $response;
    }

    /**
     * Enhanced paginated response with HATEOAS links
     */
    protected function paginatedResponse(
        LengthAwarePaginator $paginator,
        string $message = 'Success',
        bool $withLinks = true
    ): JsonResponse {
        $data = [
            'items' => $paginator->items(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'has_more_pages' => $paginator->hasMorePages(),
            ]
        ];

        // Add HATEOAS links for pagination
        if ($withLinks) {
            $links = [
                'self' => $paginator->url($paginator->currentPage()),
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
            ];

            if ($paginator->currentPage() > 1) {
                $links['prev'] = $paginator->previousPageUrl();
            }

            if ($paginator->hasMorePages()) {
                $links['next'] = $paginator->nextPageUrl();
            }

            $this->withLinks($links);
        }

        return $this->successResponse($data, $message);
    }

    /**
     * Collection response with count
     */
    protected function collectionResponse(
        Collection|array $collection,
        string $message = 'Success'
    ): JsonResponse {
        $items = $collection instanceof Collection ? $collection->all() : $collection;
        
        $this->withMeta([
            'count' => count($items),
        ]);

        return $this->successResponse(['items' => $items], $message);
    }

    /**
     * Resource response
     */
    protected function resourceResponse(
        JsonResource $resource,
        string $message = 'Success',
        int $code = 200
    ): JsonResponse {
        return $this->successResponse($resource->resolve(), $message, $code);
    }

    /**
     * Resource collection response
     */
    protected function resourceCollectionResponse(
        ResourceCollection $collection,
        string $message = 'Success'
    ): JsonResponse {
        return $this->successResponse($collection->resolve(), $message);
    }

    /**
     * Bulk operation response
     */
    protected function bulkOperationResponse(
        int $successful,
        int $failed,
        array $results = [],
        string $operation = 'operation'
    ): JsonResponse {
        $data = [
            'summary' => [
                'total' => $successful + $failed,
                'successful' => $successful,
                'failed' => $failed,
            ]
        ];

        if (!empty($results)) {
            $data['results'] = $results;
        }

        $message = "Bulk {$operation} completed: {$successful} successful, {$failed} failed";
        
        return $this->successResponse($data, $message);
    }

    /**
     * Health check response
     */
    protected function healthCheckResponse(
        bool $healthy = true,
        array $services = []
    ): JsonResponse {
        $data = [
            'status' => $healthy ? 'healthy' : 'unhealthy',
            'services' => $services,
            'timestamp' => now()->toISOString(),
        ];

        return response()->json($data, $healthy ? 200 : 503);
    }

    /**
     * Add security headers to response
     */
    private function addSecurityHeaders(JsonResponse $response): void
    {
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        
        // Add correlation ID if exists
        if ($this->correlationId) {
            $response->headers->set('X-Correlation-ID', $this->correlationId);
        }
    }

    /**
     * Response with custom headers
     */
    protected function responseWithHeaders(
        mixed $data,
        array $headers,
        string $message = 'Success',
        int $code = 200
    ): JsonResponse {
        $response = $this->successResponse($data, $message, $code);

        foreach ($headers as $key => $value) {
            $response->header($key, $value);
        }

        return $response;
    }

    /**
     * Partial content response (for range requests)
     */
    protected function partialContentResponse(
        mixed $data,
        int $from,
        int $to,
        int $total,
        string $message = 'Partial content'
    ): JsonResponse {
        $response = $this->successResponse($data, $message, Response::HTTP_PARTIAL_CONTENT);
        $response->header('Content-Range', "items {$from}-{$to}/{$total}");
        
        $this->withMeta([
            'range' => [
                'from' => $from,
                'to' => $to,
                'total' => $total,
            ]
        ]);

        return $response;
    }
}