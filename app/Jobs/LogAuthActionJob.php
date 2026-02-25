<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LogAuthActionJob implements ShouldQueue
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
            DB::table('auth_logs')->insert($this->logData);
        } catch (\Exception $e) {
            Log::error('LogAuthActionJob failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        // Log to file as ultimate fallback
        @error_log('LogAuthActionJob failed permanently: ' . $exception->getMessage());

        // Also attempt to log to the configured logger
        Log::error('LogAuthActionJob failed permanently', [
            'error'   => $exception->getMessage(),
            'logData' => $this->logData,
        ]);
    }
}
