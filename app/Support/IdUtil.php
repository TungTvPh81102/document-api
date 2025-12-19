<?php

namespace App\Support;

use Illuminate\Support\Str;

final class IdUtil
{
    public static function uuid(): string
    {
        return (string) Str::uuid(); // v4
    }

    public static function ulid(): string
    {
        return (string) Str::ulid();
    }

    public static function isUuid(string $v): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $v);
    }

    public static function isUlid(string $v): bool
    {
        // Crockford's base32, 26 chars
        return (bool) preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', strtoupper($v));
    }
}
