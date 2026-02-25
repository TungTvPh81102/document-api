<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Queue job để ghi log request không đồng bộ
 */
class LogRequestJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 30;
    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        private array $logData,
        private string $traceId
    ) {
        $this->onQueue('logs');
    }

    public function handle(): void
    {
        try {
            DB::table('cim_sql_log')->insert([
                'id'            => Str::orderedUuid(),
                'request_id'    => $this->traceId,
                'level'         => $this->logData['level'],
                'service'       => $this->logData['service'],
                'method'        => $this->logData['method'],
                'url'           => Str::limit($this->logData['route'], 500),
                'route'         => Str::limit($this->logData['route'], 255),
                'status_code'   => $this->logData['status_code'],
                'function_name' => $this->logData['function_name'],
                'logic_name'    => $this->logData['logic_name'],
                'parameters'    => json_encode($this->logData['parameters'], JSON_UNESCAPED_UNICODE),
                'response_data' => Str::limit($this->logData['response_summary'] ?? '', 2000),
                'fail_result'   => isset($this->logData['fail_result']) ? Str::limit($this->logData['fail_result'], 4000) : null,
                'start_time'    => $this->logData['start_time'],
                'end_time'      => $this->logData['end_time'],
                'duration_ms'   => $this->logData['duration_ms'],
                'user_id'       => $this->logData['user_id'],
                'ip_address'    => $this->logData['ip'],
                'user_agent'    => $this->logData['user_agent'],
                'is_error'      => in_array($this->logData['level'], ['error', 'critical']),
                'message'       => Str::limit($this->logData['response_summary'] ?? 'OK', 500),
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('LogRequestJob failed', [
                'error'   => $e->getMessage(),
                'traceId' => $this->traceId,
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        // Log to file as ultimate fallback
        @error_log('LogRequestJob failed permanently: ' . $exception->getMessage() . ' (traceId: ' . $this->traceId . ')');

        // Also attempt to log to the configured logger
        Log::error('LogRequestJob failed permanently', [
            'error'   => $exception->getMessage(),
            'traceId' => $this->traceId,
        ]);
    }
}
