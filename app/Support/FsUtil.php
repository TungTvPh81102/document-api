<?php

namespace App\Support;

use RuntimeException;

final class FsUtil
{
    public static function ensureDir(string $dir, int $mode = 0775): void
    {
        if (is_dir($dir)) {
            return;
        }
        if (!@mkdir($dir, $mode, true) && !is_dir($dir)) {
            throw new RuntimeException("Failed to create directory: {$dir}");
        }
    }

    public static function temp(string $prefix = 'app_', ?string $dir = null): string
    {
        $dir = $dir ?? sys_get_temp_dir();
        self::ensureDir($dir);
        $file = tempnam($dir, $prefix);
        if ($file === false) {
            throw new RuntimeException('Failed to create temporary file');
        }
        return $file;
    }

    public static function atomicWrite(string $path, string $contents): void
    {
        $dir = dirname($path);
        self::ensureDir($dir);
        $temp = tempnam($dir, 'aw_');
        if ($temp === false) {
            throw new RuntimeException('Failed to create temp file for atomic write');
        }
        $bytes = file_put_contents($temp, $contents, LOCK_EX);
        if ($bytes === false) {
            @unlink($temp);
            throw new RuntimeException("Failed to write temp file: {$temp}");
        }
        // Flush to disk if possible
        $fp = fopen($temp, 'c');
        if ($fp) { fflush($fp); fclose($fp); }

        // On Windows, rename fails if target exists; unlink first
        if (PHP_OS_FAMILY === 'Windows' && file_exists($path)) {
            @unlink($path);
        }
        if (!@rename($temp, $path)) {
            @unlink($temp);
            throw new RuntimeException("Failed to move temp file into place: {$path}");
        }
    }
}
