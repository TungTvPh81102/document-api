<?php

namespace App\Support;

final class Memo
{
    public static function memoize(callable $fn): callable
    {
        $cache = [];
        return function (...$args) use ($fn, &$cache) {
            $key = self::keyFor($args);
            if (array_key_exists($key, $cache)) {
                return $cache[$key];
            }
            return $cache[$key] = $fn(...$args);
        };
    }

    private static function keyFor(array $args): string
    {
        try {
            return md5(serialize($args));
        } catch (\Throwable) {
            // Fallback: stringify via json; not perfect but avoids fatal on closures/resources
            return md5(json_encode($args, JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_UNESCAPED_UNICODE) ?: '');
        }
    }
}
