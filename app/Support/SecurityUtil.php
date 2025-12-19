<?php

namespace App\Support;

final class SecurityUtil
{
    private const DEFAULT_KEYS = [
        'password', 'pwd', 'secret', 'token', 'access_token', 'refresh_token',
        'authorization', 'api_key', 'apikey', 'x-api-key', 'client_secret',
        'key', 'private_key', 'auth', 'signature',
    ];

    public static function redact(array|string|null $data, array $keys = self::DEFAULT_KEYS, string $mask = '***REDACTED***'): array|string|null
    {
        if (is_string($data) || $data === null) {
            return self::redactString($data, $keys, $mask);
        }
        return self::redactArray($data, array_map('strtolower', $keys), $mask);
    }

    public static function constantTimeEquals(string $a, string $b): bool
    {
        return hash_equals($a, $b);
    }

    public static function randomToken(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }

    private static function redactArray(array $data, array $keys, string $mask): array
    {
        $out = [];
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $out[$k] = self::redactArray($v, $keys, $mask);
            } elseif (is_string($v) && in_array(strtolower((string) $k), $keys, true)) {
                $out[$k] = $mask;
            } else {
                $out[$k] = $v;
            }
        }
        return $out;
    }

    private static function redactString(?string $s, array $keys, string $mask): ?string
    {
        if ($s === null || $s === '') {
            return $s;
        }
        // Basic header-like redaction: Authorization: Bearer ...
        $pattern = '/\b(' . implode('|', array_map(fn($k) => preg_quote($k, '/'), $keys)) . ')\s*[:=]\s*([^\s,;]+)/i';
        return preg_replace($pattern, '$1: ' . $mask, $s);
    }
}
