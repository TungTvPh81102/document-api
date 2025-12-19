<?php

namespace App\Support;

use Throwable;

final class RetryUtil
{
    /**
     * Exponential backoff with optional jitter. Attempts are 1-indexed for the callable.
     *
     * @param callable $fn function(int $attempt): mixed
     * @param int $attempts Total attempts (>=1)
     * @param int|callable $sleepMs Base sleep in ms or function(int $attempt): int
     * @param float $jitter Jitter factor (0..1)
     * @param callable|null $shouldRetry function(Throwable $e): bool
     * @return mixed
     * @throws Throwable
     */
    public static function backoff(callable $fn, int $attempts = 3, int|callable $sleepMs = 100, float $jitter = 0.1, ?callable $shouldRetry = null): mixed
    {
        if ($attempts < 1) {
            $attempts = 1;
        }
        $lastEx = null;
        for ($i = 1; $i <= $attempts; $i++) {
            try {
                return $fn($i);
            } catch (Throwable $e) {
                $lastEx = $e;
                if ($i >= $attempts) {
                    break;
                }
                if ($shouldRetry && $shouldRetry($e) === false) {
                    throw $e;
                }
                $base = is_callable($sleepMs) ? (int) $sleepMs($i) : (int) $sleepMs * (2 ** ($i - 1));
                $j = (int) round($base * $jitter * (mt_rand() / mt_getrandmax() - 0.5) * 2);
                usleep(max(0, $base + $j) * 1000);
            }
        }
        throw $lastEx ?? new \RuntimeException('Retry attempts exhausted');
    }
}
