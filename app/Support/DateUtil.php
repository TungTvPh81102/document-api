<?php

namespace App\Support;

use Carbon\Carbon;
use DateTimeInterface;
use DateTimeZone;

final class DateUtil
{
    public static function floor(DateTimeInterface|string $dt, string $unit = 'minute', int $precision = 1, ?DateTimeZone $tz = null): Carbon
    {
        $c = self::toCarbon($dt, $tz);
        return match ($unit) {
            'second' => $c->copy()->seconds((int) (floor($c->second / $precision) * $precision))->micro(0),
            'minute' => $c->copy()->seconds(0)->minutes((int) (floor($c->minute / $precision) * $precision)),
            'hour'   => $c->copy()->seconds(0)->minutes(0)->hours((int) (floor($c->hour / $precision) * $precision)),
            'day'    => $c->copy()->startOfDay(),
            'month'  => $c->copy()->startOfMonth(),
            'year'   => $c->copy()->startOfYear(),
            default  => $c,
        };
    }

    public static function ceil(DateTimeInterface|string $dt, string $unit = 'minute', int $precision = 1, ?DateTimeZone $tz = null): Carbon
    {
        $floor = self::floor($dt, $unit, $precision, $tz);
        $c = self::toCarbon($dt, $tz);
        if ($floor->equalTo($c)) {
            return $floor;
        }
        return match ($unit) {
            'second' => $floor->addSeconds($precision),
            'minute' => $floor->addMinutes($precision),
            'hour'   => $floor->addHours($precision),
            'day'    => $floor->addDay(),
            'month'  => $floor->addMonth(),
            'year'   => $floor->addYear(),
            default  => $floor,
        };
    }

    public static function businessAdd(DateTimeInterface|string $dt, int $days, array $holidays = [], ?DateTimeZone $tz = null): Carbon
    {
        $c = self::toCarbon($dt, $tz);
        $step = $days >= 0 ? 1 : -1;
        $remaining = abs($days);
        $holidaySet = array_flip(array_map(fn ($d) => Carbon::parse($d, $c->timezone)->toDateString(), $holidays));

        while ($remaining > 0) {
            $c->addDays($step);
            if (!self::isWeekend($c) && !isset($holidaySet[$c->toDateString()])) {
                $remaining--;
            }
        }
        return $c;
    }

    private static function toCarbon(DateTimeInterface|string $dt, ?DateTimeZone $tz = null): Carbon
    {
        $tz = $tz ?? new DateTimeZone(config('app.timezone', 'UTC'));
        return $dt instanceof DateTimeInterface ? Carbon::parse($dt->format('c'), $tz) : Carbon::parse($dt, $tz);
    }

    private static function isWeekend(Carbon $c): bool
    {
        $dow = (int) $c->dayOfWeekIso; // 1=Mon..7=Sun
        return $dow >= 6;
    }
}
