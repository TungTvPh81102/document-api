<?php

namespace App\Support;

final class NumUtil
{
    public static function humanBytes(int|float $bytes, int $precision = 2, bool $si = false): string
    {
        $units = $si ? ['B','kB','MB','GB','TB','PB'] : ['B','KiB','MiB','GiB','TiB','PiB'];
        $base = $si ? 1000 : 1024;

        $neg = $bytes < 0;
        $bytes = abs((float) $bytes);
        $i = 0;
        while ($bytes >= $base && $i < count($units) - 1) {
            $bytes /= $base;
            $i++;
        }
        return ($neg ? '-' : '') . round($bytes, $precision) . ' ' . $units[$i];
    }

    public static function clamp(float|int $value, float|int $min, float|int $max): float|int
    {
        return max($min, min($max, $value));
    }

    public static function mapRange(float $value, float $inMin, float $inMax, float $outMin, float $outMax, bool $clamp = false): float
    {
        if ($inMax === $inMin) {
            return $outMin; // avoid division by zero
        }
        $t = ($value - $inMin) / ($inMax - $inMin);
        if ($clamp) {
            $t = self::clamp($t, 0.0, 1.0);
        }
        return $outMin + $t * ($outMax - $outMin);
    }

    public static function safeDiv(float|int $numerator, float|int $denominator, float|int $fallback = 0): float|int
    {
        return $denominator == 0 ? $fallback : $numerator / $denominator;
    }
}
