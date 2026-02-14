<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;
use App\Support\SecurityUtil;

class LoggerService
{
    private const SENSITIVE_FIELDS = ['password', 'token', 'secret', 'api_key', 'credit_card', 'cvv', 'pin'];
    private const SLOW_QUERY_THRESHOLD = 1000;

    /**
     * Unified logging to cim_sql_log table.
     * Supports HTTP Requests, Single SQL queries, and Batch SQL queries.
     *
     * @param mixed $input Can be Illuminate\Http\Request, string (SQL), or array (Batch SQL)
     */
    public function logFullRequestToSqlLog(
        mixed   $input = null,
        ?int    $statusCode = null,
        ?float  $duration = null,
        bool    $isError = false,
        ?string $message = null,
        ?array  $params = null,
        ?string $operation = null,
        ?string $module = null
    ): void {
        try {
            $records = [];
            $request = request();

            if ($input instanceof Request) {
                // Case 1: HTTP Request
                $headers = [];
                foreach ($input->headers->all() as $key => $values) {
                    $headers[$key] = is_array($values) ? implode(',', $values) : $values;
                }

                $payload = [
                    'status_code' => $statusCode ?? 200,
                    'headers' => $this->sanitizeParams($headers),
                    'query' => $this->sanitizeParams($input->query() ?? []),
                    'body' => $this->sanitizeParams($input->all() ?? []),
                ];

                $records[] = [
                    'id' => Str::orderedUuid(),
                    'sql_text' => sprintf('HTTP %s %s', $input->getMethod(), $input->getPathInfo()),
                    'sql_params' => json_encode($payload),
                    'operation' => 'HTTP_REQUEST',
                    'duration_ms' => $duration ? round($duration * 1000, 2) : 0,
                    'executed_by' => auth()->user()?->name ?? 'system',
                    'user_id' => auth()->id(),
                    'module' => $input?->route()?->getActionName() ?? 'unknown',
                    'ip_address' => $input?->ip() ?? 'unknown',
                    'user_agent' => $input?->userAgent() ?? 'unknown',
                    'is_error' => $isError,
                    'message' => $message ?? ($isError ? 'Unknown Error' : 'Success'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            } elseif (is_string($input)) {
                // Case 2: Single SQL Query
                $records[] = [
                    'id' => Str::orderedUuid(),
                    'sql_text' => $input,
                    'sql_params' => $params ? json_encode($this->sanitizeParams($params)) : json_encode([]),
                    'operation' => $operation ?? $this->detectOperation($input),
                    'duration_ms' => $duration ? round($duration * 1000, 2) : 0,
                    'executed_by' => auth()->user()?->name ?? 'system',
                    'user_id' => auth()->id(),
                    'module' => $module ?? $this->getCallerModule() ?? 'unknown',
                    'ip_address' => $request?->ip() ?? 'unknown',
                    'user_agent' => $request?->userAgent() ?? 'unknown',
                    'is_error' => $isError,
                    'message' => $message ?? ($isError ? 'Query Failed' : 'Success'),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                // Log slow queries
                if ($duration && ($duration * 1000) > self::SLOW_QUERY_THRESHOLD) {
                    $this->logPerformanceIssue(
                        'sql_query',
                        "Slow query detected: {$operation}",
                        $duration,
                        self::SLOW_QUERY_THRESHOLD / 1000,
                        ['sql' => substr($input, 0, 200), 'module' => $module]
                    );
                }
            } elseif (is_array($input)) {
                // Case 3: Batch SQL Queries
                foreach ($input as $query) {
                    $sql = $query['sql'] ?? 'UNKNOWN SQL';
                    $records[] = [
                        'id' => Str::orderedUuid(),
                        'sql_text' => $sql,
                        'sql_params' => isset($query['params']) ? json_encode($this->sanitizeParams($query['params'])) : json_encode([]),
                        'operation' => $query['operation'] ?? $this->detectOperation($sql),
                        'duration_ms' => isset($query['duration']) ? round($query['duration'] * 1000, 2) : 0,
                        'executed_by' => auth()->user()?->name ?? 'system',
                        'user_id' => auth()->id(),
                        'module' => $query['module'] ?? $this->getCallerModule() ?? 'unknown',
                        'ip_address' => $request?->ip() ?? 'unknown',
                        'user_agent' => $request?->userAgent() ?? 'unknown',
                        'is_error' => $query['is_error'] ?? false,
                        'message' => $query['error_message'] ?? $message ?? ($isError ? 'Batch Query Failed' : 'Success'),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            if (!empty($records)) {
                DB::table('cim_sql_log')->insert($records);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to log to cim_sql_log', [
                'error' => $e->getMessage(),
                'type' => gettype($input)
            ]);
        }
    }


    /**
     * Log API errors with structured context
     */
    public function logApiError(Throwable $e, ?Request $request = null): void
    {
        $request = $request ?? request();
        $context = $this->buildContext($e, $request);

        Log::channel('api')->error($e->getMessage(), $context);
    }

    /**
     * Log API request
     */
    public function logApiRequest(?Request $request = null, ?int $statusCode = null, ?float $duration = null): void
    {
        $request = $request ?? request();

        $context = [
            'method' => $request->method(),
            'path' => $request->path(),
            'status_code' => $statusCode,
            'duration_ms' => $duration ? round($duration * 1000, 2) : null,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'user_id' => auth()->id(),
        ];

        $logLevel = ($statusCode && $statusCode >= 400) ? 'warning' : 'info';
        $message = ($statusCode && $statusCode >= 400)
            ? "API request failed: {$request->method()} {$request->path()}"
            : "API request: {$request->method()} {$request->path()}";

        Log::channel('api')->{$logLevel}($message, $context);
    }

    /**
     * Log API success response
     */
    public function logApiSuccess(?Request $request = null, string $message = 'Success'): void
    {
        $request = $request ?? request();

        $context = [
            'method' => $request->method(),
            'path' => $request->path(),
            'ip' => $request->ip(),
            'user_id' => auth()->id(),
        ];

        Log::info("API Success: $message", $context);
    }

    /**
     * Log authentication events
     */
    public function logAuthEvent(string $event, ?string $userId = null, ?array $data = []): void
    {
        $context = [
            'event' => $event,
            'user_id' => $userId ?? auth()->id(),
            'ip' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'timestamp' => now()->toISOString(),
        ];

        if ($data) {
            $context = array_merge($context, $data);
        }

        Log::info("Auth Event: $event", $context);
    }

    /**
     * Log database operations
     */
    public function logDatabaseOperation(
        string $operation,
        string $model,
        ?string $id = null,
        ?float $duration = null,
        ?array $metadata = [],
        bool $isError = false,
        ?string $message = null
    ): void {
        $context = [
            'operation' => $operation,
            'model' => $model,
            'id' => $id,
            'duration_ms' => $duration ? round($duration * 1000, 2) : 0,
            'user_id' => auth()->id(),
            'timestamp' => now()->toISOString(),
        ];

        if ($metadata) {
            $context['metadata'] = $metadata;
        }

        Log::channel('database')->info("Database Operation: $operation on $model", $context);

        // Map model name to table name if possible, or just use model name
        $tableName = strtolower(class_basename($model)) . 's'; // Simple pluralization
        $sqlText = sprintf('%s %s', strtoupper($operation), strtoupper($tableName));
        if ($isError) {
            $sqlText .= ' FAILED';
        }

        $this->logFullRequestToSqlLog(
            $sqlText,
            null,
            $duration,
            $isError,
            $message,
            $metadata,
            strtoupper($operation),
            $this->getCallerModule()
        );
    }

    /**
     * Log performance issues
     */
    public function logPerformanceIssue(
        string $operation,
        string $message,
        float $duration,
        float $threshold,
        array $metadata = []
    ): void {
        $context = [
            'operation' => $operation,
            'duration_ms' => round($duration * 1000, 2),
            'threshold_ms' => round($threshold * 1000, 2),
            'exceeded_by_ms' => round(($duration - $threshold) * 1000, 2),
            'metadata' => $metadata,
            'user_id' => auth()->id(),
            'timestamp' => now()->toISOString(),
        ];

        Log::channel('performance')->warning($message, $context);
    }

    /**
     * Log user actions (business logic)
     */
    public function logAction(string $action, ?object $user = null, ?array $data = []): void
    {
        $user = $user ?? auth()->user();

        $context = [
            'action' => $action,
            'user_id' => $user?->id,
            'user_email' => $user?->email,
            'ip' => request()?->ip(),
            'timestamp' => now()->toISOString(),
        ];

        if ($data) {
            $context = array_merge($context, $data);
        }

        $level = in_array($action, ['deleted', 'force_deleted', 'disabled', 'locked', 'banned'])
            ? 'warning'
            : 'info';

        Log::{$level}("User Action: $action", $context);
    }

    /**
     * Log service errors with business context
     */
    public function logServiceError(
        string $service,
        string $method,
        Throwable $e,
        ?array $context = []
    ): void {
        $errorContext = [
            'service' => $service,
            'method' => $method,
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'user_id' => auth()->id(),
            'ip' => request()?->ip(),
            'timestamp' => now()->toISOString(),
        ];

        if ($context) {
            $errorContext = array_merge($errorContext, $context);
        }

        Log::channel('service_errors')->error("Service Error: $service::$method", $errorContext);

        // Also log to SQL log table for critical errors
        $this->logFullRequestToSqlLog(
            "Service error in $service::$method",
            null,
            null,
            true,
            $e->getMessage(),
            ['error' => $e->getMessage()],
            'ERROR',
            $service
        );
    }

    /**
     * Build context for error logging
     */
    private function buildContext(Throwable $e, Request $request): array
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
            'input' => $this->sanitizeInput($request->input()),
        ];
    }

    /**
     * Sanitize sensitive input data
     */
    private function sanitizeInput(array $input): array
    {
        return SecurityUtil::redact($input);
    }

    /**
     * Sanitize SQL parameters
     */
    private function sanitizeParams(array $params): array
    {
        return SecurityUtil::redact($params);
    }

    /**
     * Detect SQL operation type from query
     */
    private function detectOperation(string $sql): string
    {
        $sql = strtoupper(trim($sql));

        if (str_starts_with($sql, 'SELECT')) return 'SELECT';
        if (str_starts_with($sql, 'INSERT')) return 'INSERT';
        if (str_starts_with($sql, 'UPDATE')) return 'UPDATE';
        if (str_starts_with($sql, 'DELETE')) return 'DELETE';
        if (str_starts_with($sql, 'CREATE')) return 'CREATE';
        if (str_starts_with($sql, 'ALTER')) return 'ALTER';
        if (str_starts_with($sql, 'DROP')) return 'DROP';

        return 'UNKNOWN';
    }

    /**
     * Get the module/class that called the logger
     */
    private function getCallerModule(): ?string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);

        foreach ($trace as $frame) {
            if (isset($frame['class']) && !in_array($frame['class'], [self::class, 'App\Http\Middleware\LogHttpRequestsMiddleware', 'App\Http\Middleware\LogSqlQueriesMiddleware'])) {
                return $frame['class'];
            }
        }

        return 'unknown';
    }

}
