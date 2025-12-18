<?php

namespace App\Services;

use App\Models\SqlLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Throwable;

class LoggerService
{
    private const SENSITIVE_FIELDS = ['password', 'token', 'secret', 'api_key', 'credit_card', 'cvv', 'pin'];
    private const SLOW_QUERY_THRESHOLD = 1000; // ms

    /**
     * Log SQL query to database
     */
    public function logSqlQuery(
        string $sqlText,
        ?array $params = null,
        ?string $operation = null,
        ?float $duration = null,
        ?string $module = null,
        bool $isError = false,
        ?string $errorMessage = null
    ): void {
        try {
            $request = request();

            DB::table('cim_sql_log')->insert([
                'id' => DB::raw('uuid_generate_v4()'),
                'sql_text' => $sqlText,
                'sql_params' => $params ? json_encode($this->sanitizeParams($params)) : null,
                'operation' => $operation ?? $this->detectOperation($sqlText),
                'duration_ms' => $duration ? round($duration * 1000, 2) : null,
                'executed_by' => auth()->user()?->name ?? 'system',
                'user_id' => auth()->id(),
                'module' => $module,
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
                'is_error' => $isError,
                'error_message' => $errorMessage,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Log slow queries
            if ($duration && ($duration * 1000) > self::SLOW_QUERY_THRESHOLD) {
                $this->logPerformanceIssue(
                    'sql_query',
                    "Slow query detected: {$operation}",
                    $duration,
                    self::SLOW_QUERY_THRESHOLD / 1000,
                    [
                        'sql' => substr($sqlText, 0, 200),
                        'module' => $module
                    ]
                );
            }
        } catch (\Throwable $e) {
            // Fallback to file log if database logging fails
            Log::error('Failed to log SQL query to database', [
                'error' => $e->getMessage(),
                'sql' => substr($sqlText, 0, 200)
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
        ?array $metadata = []
    ): void {
        $context = [
            'operation' => $operation,
            'model' => $model,
            'id' => $id,
            'duration_ms' => $duration ? round($duration * 1000, 2) : null,
            'user_id' => auth()->id(),
            'timestamp' => now()->toISOString(),
        ];

        if ($metadata) {
            $context['metadata'] = $metadata;
        }

        Log::channel('database')->info("Database Operation: $operation on $model", $context);

        // Optionally log to SQL log table
        if ($duration) {
            $this->logSqlQuery(
                "Database operation: $operation",
                ['model' => $model, 'id' => $id],
                $operation,
                $duration,
                $this->getCallerModule()
            );
        }
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
        $this->logSqlQuery(
            "Service error in $service::$method",
            ['error' => $e->getMessage()],
            'ERROR',
            null,
            $service,
            true,
            $e->getMessage()
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
        foreach (self::SENSITIVE_FIELDS as $field) {
            if (isset($input[$field])) {
                $input[$field] = '***REDACTED***';
            }
        }

        return $input;
    }

    /**
     * Sanitize SQL parameters
     */
    private function sanitizeParams(array $params): array
    {
        return $this->sanitizeInput($params);
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
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 4);

        foreach ($trace as $frame) {
            if (isset($frame['class']) && $frame['class'] !== self::class) {
                return $frame['class'];
            }
        }

        return null;
    }

    /**
     * Batch log multiple SQL queries (for optimization)
     */
    public function logSqlBatch(array $queries): void
    {
        try {
            $records = [];
            $request = request();

            foreach ($queries as $query) {
                $records[] = [
                    'id' => DB::raw('uuid_generate_v4()'),
                    'sql_text' => $query['sql'] ?? '',
                    'sql_params' => isset($query['params']) ? json_encode($this->sanitizeParams($query['params'])) : null,
                    'operation' => $query['operation'] ?? $this->detectOperation($query['sql'] ?? ''),
                    'duration_ms' => isset($query['duration']) ? round($query['duration'] * 1000, 2) : null,
                    'executed_by' => auth()->user()?->name ?? 'system',
                    'user_id' => auth()->id(),
                    'module' => $query['module'] ?? null,
                    'ip_address' => $request?->ip(),
                    'user_agent' => $request?->userAgent(),
                    'is_error' => $query['is_error'] ?? false,
                    'error_message' => $query['error_message'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            DB::table('cim_sql_log')->insert($records);
        } catch (\Throwable $e) {
            Log::error('Failed to batch log SQL queries', [
                'error' => $e->getMessage(),
                'count' => count($queries)
            ]);
        }
    }
}
