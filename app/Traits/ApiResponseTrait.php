<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

trait ApiResponseTrait
{
    /**
     * Format response envelope
     */
    private function formatResponse(bool $success, mixed $data = null, string $message = '', int $code = 200, mixed $errors = null): array
    {
        $response = [
            'success' => $success,
            'message' => $message,
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        $response['timestamp'] = now()->toISOString();

        return $response;
    }

    /**
     * Success response
     */
    protected function successResponse(mixed $data = null, string $message = 'Success', int $code = 200): JsonResponse
    {
        return response()->json(
            $this->formatResponse(true, $data, $message, $code),
            $code
        );
    }

    /**
     * Error response
     */
    protected function errorResponse(string $message = 'Error', int $code = 400, mixed $errors = null): JsonResponse
    {
        return response()->json(
            $this->formatResponse(false, null, $message, $code, $errors),
            $code
        );
    }

    /**
     * Created response
     */
    protected function createdResponse(mixed $data = null, string $message = 'Created successfully'): JsonResponse
    {
        return $this->successResponse($data, $message, 201);
    }

    /**
     * No content response
     */
    protected function noContentResponse(): JsonResponse
    {
        return response()->json(null, 204);
    }

    /**
     * Not found response
     */
    protected function notFoundResponse(string $message = 'Resource not found'): JsonResponse
    {
        return $this->errorResponse($message, 404);
    }

    /**
     * Validation error response
     */
    protected function validationErrorResponse(mixed $errors, string $message = 'Validation failed'): JsonResponse
    {
        return $this->errorResponse($message, 422, $errors);
    }

    /**
     * Unauthorized response
     */
    protected function unauthorizedResponse(string $message = 'Unauthorized'): JsonResponse
    {
        return $this->errorResponse($message, 401);
    }

    /**
     * Forbidden response
     */
    protected function forbiddenResponse(string $message = 'Forbidden'): JsonResponse
    {
        return $this->errorResponse($message, 403);
    }

    /**
     * Server error response
     */
    protected function serverErrorResponse(string $message = 'Internal server error'): JsonResponse
    {
        return $this->errorResponse($message, 500);
    }

    /**
     * Paginated response
     */
    protected function paginatedResponse(LengthAwarePaginator $paginator, string $message = 'Success'): JsonResponse
    {
        return $this->successResponse([
            'items' => $paginator->items(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ]
        ], $message);
    }

    /**
     * Resource response
     */
    protected function resourceResponse(JsonResource $resource, string $message = 'Success'): JsonResponse
    {
        return $this->successResponse($resource->resolve(), $message);
    }

    /**
     * Resource collection response
     */
    protected function resourceCollectionResponse(ResourceCollection $collection, string $message = 'Success'): JsonResponse
    {
        return $this->successResponse($collection->resolve(), $message);
    }
}
