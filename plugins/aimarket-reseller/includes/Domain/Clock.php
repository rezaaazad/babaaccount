<?php
/**
 * UTC timestamps stored as SQL datetimes.
 *
 * @package HooshMarket\Reseller
 */

declare(strict_types=1);

namespace HooshMarket\Reseller\Domain;

final class Clock
{
    public static function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }

    public static function iso(string $sqlDateTime): string
    {
        $ts = strtotime($sqlDateTime . ' UTC');
        if ($ts === false) {
            return $sqlDateTime;
        }
        return gmdate('Y-m-d\TH:i:s\Z', $ts);
    }

    public static function plusMinutes(string $sqlDateTime, int $minutes): string
    {
        $ts = strtotime($sqlDateTime . ' UTC');
        if ($ts === false) {
            $ts = time();
        }
        return gmdate('Y-m-d H:i:s', $ts + ($minutes * 60));
    }
}
