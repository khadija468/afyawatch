<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * ISO 8601 weeks (Monday to Sunday). A week is stored as the date of its Monday.
 */
final class Week
{
    public static function start(CarbonInterface|string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date)->startOfWeek(CarbonInterface::MONDAY)->startOfDay();
    }

    public static function label(CarbonInterface|string $date): string
    {
        $date = CarbonImmutable::parse($date);

        return sprintf('%d-W%02d', $date->isoWeekYear, $date->isoWeek);
    }

    /** Accepts "2026-W47" or any date inside the week. */
    public static function parse(string $value): CarbonImmutable
    {
        if (preg_match('/^(\d{4})-W(\d{2})$/', $value, $m)) {
            return CarbonImmutable::now()->setISODate((int) $m[1], (int) $m[2])->startOfWeek(CarbonInterface::MONDAY)->startOfDay();
        }

        if (strtotime($value) === false) {
            throw new InvalidArgumentException("Not a week: {$value}");
        }

        return self::start($value);
    }

    /** @return list<CarbonImmutable> */
    public static function range(CarbonInterface $from, CarbonInterface $to): array
    {
        $weeks = [];
        for ($week = self::start($from); $week->lte($to); $week = $week->addWeek()) {
            $weeks[] = $week;
        }

        return $weeks;
    }
}
