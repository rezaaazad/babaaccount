<?php
/**
 * Integer money. Amounts are minor units so a wallet can never drift on floats.
 *
 * @package HooshMarket\Reseller
 */

declare(strict_types=1);

namespace HooshMarket\Reseller\Domain;

final class Money
{
    public static function majorToMinor(string $major, int $decimals): int
    {
        $major = trim(str_replace(',', '', $major));
        if ($major === '' || !preg_match('/^-?\d+(\.\d+)?$/', $major)) {
            throw new ApiException('invalid_amount', 'مبلغ وارد شده معتبر نیست.', 422);
        }
        $negative = str_starts_with($major, '-');
        if ($negative) {
            $major = substr($major, 1);
        }
        [$whole, $frac] = array_pad(explode('.', $major, 2), 2, '');
        $decimals = max(0, $decimals);
        $fracDigits = str_split($frac);
        $keep = substr(str_pad($frac, $decimals + 1, '0'), 0, $decimals);
        $roundUp = isset($fracDigits[$decimals]) && $fracDigits[$decimals] >= '5';
        $factor = 10 ** $decimals;
        $minor = ((int) $whole) * $factor + (int) ($keep === '' ? '0' : $keep);
        if ($roundUp) {
            $minor++;
        }
        return $negative ? -$minor : $minor;
    }

    public static function format(int $minor, int $decimals, string $currency): string
    {
        $negative = $minor < 0;
        $minor = abs($minor);
        $decimals = max(0, $decimals);
        $factor = 10 ** $decimals;
        $whole = intdiv($minor, $factor);
        $frac = $minor % $factor;
        $number = number_format($whole);
        if ($decimals > 0) {
            $number .= '.' . str_pad((string) $frac, $decimals, '0', STR_PAD_LEFT);
        }
        if ($negative) {
            $number = '-' . $number;
        }
        return $number . ' ' . self::label($currency);
    }

    public static function label(string $currency): string
    {
        return match (strtoupper($currency)) {
            'IRT' => 'تومان',
            'IRR' => 'ریال',
            default => strtoupper($currency),
        };
    }
}
