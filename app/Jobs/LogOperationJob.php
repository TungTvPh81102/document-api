<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LogOperationJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 30;
    public int $tries = 3;

    public function __construct(private array $logData)
    {
        $this->onQueue('logs');
    }

    public function handle(): void
    {
        try {
            DB::table('operation_logs')->insert($this->logData);
        } catch (\Exception $e) {
            Log::error('LogOperationJob failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        // Log to file as ultimate fallback
        @error_log('LogOperationJob failed permanently: ' . $exception->getMessage());

        // Also attempt to log to the configured logger
        Log::error('LogOperationJob failed permanently', [
            'error'   => $exception->getMessage(),
            'logData' => $this->logData,
        ]);
    }
}
