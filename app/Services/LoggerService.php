<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class LoggerService
{
    /**
     * Log API errors with structured context
     */
    public static function logApiError(Throwable $e, Request $request): void
    {
        $context = self::buildContext($e, $request);

        Log::channel('api')->error($e->getMessage(), $context);
    }

    /**
     * Log API request
     */
    public static function logApiRequest(Request $request, ?int $statusCode, ?float $duration): void
    {
        $context = [
            'method' => $request->method(),
            'path' => $request->path(),
            'status_code' => $statusCode,
            'duration_ms' => round($duration * 1000, 2),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];

        if ($statusCode >= 400) {
            Log::channel('api')->info("API request failed: {$request->method()} {$request->path()}", $context);
        } else {
            Log::channel('api')->info("API request: {$request->method()} {$request->path()}", $context);
        }
    }

    /**
     * Log API success response
     */
    public static function logApiSuccess(Request $request, string $message = 'Success'): void
    {
        $context = [
            'method' => $request->method(),
            'path' => $request->path(),
            'ip' => $request->ip(),
        ];

        Log::info("API Success: $message", $context);
    }

    /**
     * Log authentication events
     */
    public static function logAuthEvent(string $event, ?string $userId = null, ?array $data = null): void
    {
        $context = [
            'event' => $event,
            'user_id' => $userId,
        ];

        if ($data) {
            $context = array_merge($context, $data);
        }

        Log::info("Auth Event: $event", $context);
    }

    /**
     * Log database operations
     */
    public static function logDatabaseOperation(string $operation, string $table, ?string $id = null, ?float $duration = null): void
    {
        $context = [
            'operation' => $operation,
            'table' => $table,
            'id' => $id,
            'timestamp' => now()->toISOString(),
        ];

        if ($duration) {
            $context['duration_ms'] = round($duration * 1000, 2);
        }

        Log::channel('database')->info("Database Operation: $operation $table", $context);
    }

    /**
     * Log performance issues
     */
    public static function logPerformanceIssue(string $operation, string $message, float $duration, float $threshold,array $metadata = []): void
    {
        $context = [
             'operation' => $operation,
            'duration_ms' => round($duration * 1000, 2),
            'threshold_ms' => round($threshold * 1000, 2),
            'metadata' => $metadata,
            'timestamp' => now()->toISOString(),
        ];

        Log::channel('performance')->warning($message, $context);
    }

    /**
     * Log user actions (business logic)
     */
    public function logAction(string $action, ?object $user = null, ?array $data = null): void
    {
        $context = [
            'action' => $action,
            'timestamp' => now()->toISOString(),
        ];

        if ($user) {
            $context['user_id'] = $user->id ?? null;
            $context['user_email'] = $user->email ?? null;
        }

        if ($data) {
            $context = array_merge($context, $data);
        }

        $level = in_array($action, ['deleted', 'force_deleted', 'disabled', 'locked']) ? 'warning' : 'info';
        Log::{$level}("User Action: $action", $context);
    }

    /**
     * Log service errors with business context
     */
    public function logServiceError(string $service, string $method, \Throwable $e, ?array $context = null): void
    {
        $errorContext = [
            'service' => $service,
            'method' => $method,
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'timestamp' => now()->toISOString(),
        ];

        if ($context) {
            $errorContext = array_merge($errorContext, $context);
        }

        Log::channel('service_errors')->error("Service Error: $service::$method", $errorContext);
    }

    /**
     * Build context for error logging
     */
    private static function buildContext(Throwable $e, Request $request): array
    {
        return [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'request' => [
                'method' => $request->method(),
                'path' => $request->path(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'user_id' => $request->user()?->id,
            ],
            'query' => $request->query(),
            'input' => self::sanitizeInput($request->input()),
        ];
    }

    /**
     * Sanitize sensitive input data
     */
    private static function sanitizeInput(array $input): array
    {
        $sensitive = ['password', 'token', 'secret', 'api_key', 'credit_card'];

        foreach ($sensitive as $field) {
            if (isset($input[$field])) {
                $input[$field] = '***REDACTED***';
            }
        }

        return $input;
    }
}
