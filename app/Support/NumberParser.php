<?php

namespace App\Support;

final class NumberParser
{
    /**
     * Normalize an Indonesian-formatted rupiah amount (e.g. "1.500.000" or "1.500.000,50").
     * Thousand separators use dots, the decimal separator uses a comma.
     * Values that are already plain decimal numbers (e.g. "1000000.00") are kept as-is.
     *
     * @param  mixed  $value
     */
    public static function rupiah(mixed $value): ?string
    {
        $s = self::asString($value);

        if ($s === null || $s === '') {
            return null;
        }

        $s = str_replace([' ', "\u{00A0}"], '', $s);

        if (preg_match('/^[+-]?\d+(?:\.\d{1,2})?$/', $s) === 1) {
            return $s;
        }

        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);

        return $s;
    }

    /**
     * Normalize a decimal quantity (e.g. "2,5" or "10"). A single dot is kept as the decimal separator.
     *
     * @param  mixed  $value
     */
    public static function decimal(mixed $value): ?string
    {
        $s = self::asString($value);

        if ($s === null || $s === '') {
            return null;
        }

        $s = str_replace([' ', "\u{00A0}"], '', $s);
        $s = str_replace(',', '.', $s);

        return $s;
    }

    private static function asString(mixed $value): ?string
    {
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_string($value)) {
            return trim($value);
        }

        return null;
    }
}
