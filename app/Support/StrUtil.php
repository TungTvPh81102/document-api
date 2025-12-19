<?php

namespace App\Support;

final class StrUtil
{
    public static function mask(string $value, int $visibleStart = 1, int $visibleEnd = 1, string $mask = '*'): string
    {
        $len = mb_strlen($value, 'UTF-8');
        if ($len === 0) {
            return $value;
        }
        $vs = max(0, min($visibleStart, $len));
        $ve = max(0, min($visibleEnd, max(0, $len - $vs)));
        $maskLen = max(0, $len - $vs - $ve);
        if ($maskLen <= 0) {
            return $value;
        }

        $start = mb_substr($value, 0, $vs, 'UTF-8');
        $end = $ve > 0 ? mb_substr($value, $len - $ve, $ve, 'UTF-8') : '';
        return $start . str_repeat($mask, $maskLen) . $end;
    }

    public static function normalizeWhitespace(string $value): string
    {
        // Replace all Unicode whitespace (including NBSP, line separators) with single spaces
        $value = preg_replace('/\s+/u', ' ', $value ?? '') ?? '';
        return trim($value);
    }

    public static function excerptAround(string $haystack, string $needle, int $radius = 20, string $ellipsis = '…'): string
    {
        if ($needle === '' || mb_stripos($haystack, $needle, 0, 'UTF-8') === false) {
            return mb_substr(self::normalizeWhitespace($haystack), 0, $radius * 2, 'UTF-8');
        }

        $pos = mb_stripos($haystack, $needle, 0, 'UTF-8');
        $start = max(0, $pos - $radius);
        $end = min(mb_strlen($haystack, 'UTF-8'), $pos + mb_strlen($needle, 'UTF-8') + $radius);
        $snippet = mb_substr($haystack, $start, $end - $start, 'UTF-8');

        if ($start > 0) {
            $snippet = $ellipsis . ltrim($snippet);
        }
        if ($end < mb_strlen($haystack, 'UTF-8')) {
            $snippet = rtrim($snippet) . $ellipsis;
        }
        return $snippet;
    }
}
