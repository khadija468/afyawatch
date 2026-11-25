<?php

namespace App\Support;

/**
 * Rules for small counts. Counts from 1 to 4 are flagged on screen and replaced with "<5"
 * in exported files, because small counts in small areas can point to individual people.
 */
final class SmallNumbers
{
    public const THRESHOLD = 5;

    /** Case fatality and other rates are not shown below this many cases. */
    public const MIN_CASES_FOR_RATE = 20;

    public static function isSmall(?int $count): bool
    {
        return $count !== null && $count > 0 && $count < self::THRESHOLD;
    }

    public static function forExport(?int $count): string
    {
        if ($count === null) {
            return '';
        }

        return self::isSmall($count) ? '<'.self::THRESHOLD : (string) $count;
    }
}
